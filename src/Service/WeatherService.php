<?php

namespace App\Service;

use App\Entity\Evenement;

class WeatherService
{
    public function getForecastForEvenement(Evenement $evenement): array
    {
        $lieu = $evenement->getLieu();
        $dateDebut = $evenement->getDateDebut();

        if (!$lieu || !$dateDebut) {
            return ['status' => 'unavailable', 'days' => [], 'location' => null];
        }

        // Future: use Open-Meteo with lieu lat/lng when coordinates are stored in Lieu entity
        return ['status' => 'unavailable', 'days' => [], 'location' => $lieu->getNom()];
    }
}
