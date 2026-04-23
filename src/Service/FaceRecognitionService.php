<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FaceRecognitionService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly string $faceAuthApiUrl,
    ) {
    }

    public function compare(string $cameraImageBase64, string $referenceImagePath): array
    {
        try {
            $response = $this->httpClient->request('POST', rtrim($this->faceAuthApiUrl, '/').'/compare-face', [
                'json' => [
                    'image' => $cameraImageBase64,
                    'reference' => $referenceImagePath,
                ],
                'timeout' => 120,
            ]);

            return $response->toArray(false);
        } catch (TransportExceptionInterface $exception) {
            return [
                'success' => false,
                'message' => 'Service de reconnaissance faciale indisponible: '.$exception->getMessage(),
            ];
        }
    }
}
