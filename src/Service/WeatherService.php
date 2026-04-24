<?php

namespace App\Service;

use App\Entity\Evenement;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WeatherService
{
    private const GEOCODING_URL = 'https://geocoding-api.open-meteo.com/v1/search';
    private const FORECAST_URL = 'https://api.open-meteo.com/v1/forecast';

    public function __construct(
        private HttpClientInterface $httpClient,
    ) {}

    public function getForecastForEvenement(Evenement $evenement): array
    {
        $lieu = $evenement->getLieu();
        $dateDebut = $evenement->getDateDebut();

        if (!$lieu || !$dateDebut) {
            return ['status' => 'unavailable', 'days' => [], 'location' => null];
        }

        try {
            [$latitude, $longitude, $locationLabel] = $this->resolveCoordinates($evenement);
            if ($latitude === null || $longitude === null) {
                return ['status' => 'unavailable', 'days' => [], 'location' => $lieu->getNom()];
            }

            $response = $this->httpClient->request('GET', self::FORECAST_URL, [
                'query' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'timezone' => 'auto',
                    'forecast_days' => 16,
                    'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max,wind_speed_10m_max',
                ],
                'timeout' => 15,
            ]);

            $payload = $response->toArray(false);
            $daily = $payload['daily'] ?? null;
            if (!is_array($daily) || !isset($daily['time']) || !is_array($daily['time'])) {
                return ['status' => 'unavailable', 'days' => [], 'location' => $locationLabel];
            }

            $days = [];
            foreach ($daily['time'] as $index => $date) {
                $weatherCode = isset($daily['weather_code'][$index]) ? (int) $daily['weather_code'][$index] : null;
                $tempMin = isset($daily['temperature_2m_min'][$index]) ? (float) $daily['temperature_2m_min'][$index] : null;
                $tempMax = isset($daily['temperature_2m_max'][$index]) ? (float) $daily['temperature_2m_max'][$index] : null;
                $precipProb = isset($daily['precipitation_probability_max'][$index]) ? (int) round((float) $daily['precipitation_probability_max'][$index]) : null;
                $windMax = isset($daily['wind_speed_10m_max'][$index]) ? (float) $daily['wind_speed_10m_max'][$index] : null;

                $days[] = [
                    'date' => (string) $date,
                    'weather_code' => $weatherCode,
                    'label' => $this->labelFromCode($weatherCode),
                    'temp_min' => $tempMin !== null ? round($tempMin, 1) : null,
                    'temp_max' => $tempMax !== null ? round($tempMax, 1) : null,
                    'precip_prob' => $precipProb,
                    'wind_max' => $windMax !== null ? round($windMax, 1) : null,
                    'participation' => $this->estimateParticipation($weatherCode, $precipProb, $windMax, $tempMin, $tempMax),
                ];
            }

            return [
                'status' => $days !== [] ? 'ok' : 'unavailable',
                'days' => $days,
                'location' => $locationLabel,
            ];
        } catch (\Throwable) {
            return ['status' => 'unavailable', 'days' => [], 'location' => $lieu->getNom()];
        }
    }

    /**
     * @return array{0: ?float, 1: ?float, 2: ?string}
     */
    private function resolveCoordinates(Evenement $evenement): array
    {
        $lieu = $evenement->getLieu();
        if (!$lieu) {
            return [null, null, null];
        }

        $latitude = $lieu->getLatitude();
        $longitude = $lieu->getLongitude();
        $locationLabel = $this->buildLocationLabel($lieu->getNom(), $lieu->getVille());

        if ($latitude !== null && $longitude !== null) {
            return [$latitude, $longitude, $locationLabel];
        }

        $queries = array_values(array_unique(array_filter([
            $this->buildLocationLabel($lieu->getNom(), $lieu->getVille(), $lieu->getAdresse()),
            $this->buildLocationLabel($lieu->getNom(), $lieu->getVille()),
            $this->buildLocationLabel($lieu->getVille()),
        ])));

        foreach ($queries as $query) {
            $response = $this->httpClient->request('GET', self::GEOCODING_URL, [
                'query' => [
                    'name' => $query,
                    'count' => 1,
                    'language' => 'fr',
                    'countryCode' => 'TN',
                ],
                'timeout' => 10,
            ]);

            $payload = $response->toArray(false);
            $first = $payload['results'][0] ?? null;
            if (!is_array($first) || !isset($first['latitude'], $first['longitude'])) {
                continue;
            }

            $resolvedLabel = $this->buildLocationLabel(
                isset($first['name']) ? (string) $first['name'] : $lieu->getNom(),
                isset($first['admin1']) ? (string) $first['admin1'] : $lieu->getVille(),
                isset($first['country']) ? (string) $first['country'] : null
            );

            return [
                (float) $first['latitude'],
                (float) $first['longitude'],
                $resolvedLabel,
            ];
        }

        return [null, null, $locationLabel];
    }

    private function buildLocationLabel(?string ...$parts): ?string
    {
        $parts = array_values(array_filter(array_map(
            static fn (?string $value): ?string => ($value !== null && trim($value) !== '') ? trim($value) : null,
            $parts
        )));

        return $parts === [] ? null : implode(', ', array_unique($parts));
    }

    private function labelFromCode(?int $weatherCode): string
    {
        return match (true) {
            $weatherCode === null => 'Inconnu',
            $weatherCode === 0 => 'Ensoleille',
            $weatherCode >= 1 && $weatherCode <= 3 => 'Partiellement nuageux',
            $weatherCode >= 45 && $weatherCode <= 48 => 'Brouillard',
            $weatherCode >= 51 && $weatherCode <= 57 => 'Bruine',
            $weatherCode >= 61 && $weatherCode <= 67 => 'Pluie',
            $weatherCode >= 71 && $weatherCode <= 77 => 'Neige',
            $weatherCode >= 80 && $weatherCode <= 82 => 'Averses',
            $weatherCode >= 95 && $weatherCode <= 99 => 'Orage',
            default => 'Variable',
        };
    }

    private function estimateParticipation(?int $weatherCode, ?int $precipProb, ?float $windMax, ?float $tempMin, ?float $tempMax): ?int
    {
        $score = 85;

        if ($precipProb !== null) {
            $score -= (int) round(min(35, $precipProb * 0.35));
        }

        if ($windMax !== null) {
            $score -= (int) round(min(18, max(0, $windMax - 18) * 0.7));
        }

        if ($tempMin !== null && $tempMax !== null) {
            $avgTemp = ($tempMin + $tempMax) / 2;
            if ($avgTemp < 10 || $avgTemp > 34) {
                $score -= 12;
            } elseif ($avgTemp < 14 || $avgTemp > 30) {
                $score -= 6;
            }
        }

        if ($weatherCode !== null) {
            $score -= match (true) {
                $weatherCode >= 95 && $weatherCode <= 99 => 30,
                $weatherCode >= 80 && $weatherCode <= 82 => 18,
                $weatherCode >= 61 && $weatherCode <= 67 => 20,
                $weatherCode >= 51 && $weatherCode <= 57 => 12,
                $weatherCode >= 45 && $weatherCode <= 48 => 8,
                default => 0,
            };
        }

        return max(5, min(100, $score));
    }
}
