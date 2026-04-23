<?php

namespace App\Service;

use App\Entity\Evenement;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class NotionService
{
    private const NOTION_VERSION = '2022-06-28';
    private const BASE_URL = 'https://api.notion.com/v1';

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $notionApiKey,
        private string $notionDatabaseId,
    ) {}

    /**
     * Creates or updates the Notion page matching this event.
     * Returns true on success, false if sync failed (DB save is unaffected).
     */
    public function syncEvenement(Evenement $evenement): bool
    {
        if ($this->notionApiKey === '' || $this->notionDatabaseId === '') {
            $this->logger->warning('Notion sync skipped: NOTION_API_KEY or NOTION_DATABASE_ID not configured.');
            return false;
        }

        try {
            $pageId = $this->findPageIdBySymfonyId($evenement->getId());

            if ($pageId !== null) {
                $this->updatePage($pageId, $evenement);
            } else {
                $this->createPage($evenement);
            }

            return true;
        } catch (\Throwable $e) {
            $this->logger->error('Notion sync failed', [
                'evenement_id' => $evenement->getId(),
                'error' => $e->getMessage(),
            ]);
            return false;
        }
    }

    private function findPageIdBySymfonyId(int $id): ?string
    {
        $response = $this->httpClient->request(
            'POST',
            self::BASE_URL . '/databases/' . $this->notionDatabaseId . '/query',
            [
                'headers' => $this->getHeaders(),
                'json' => [
                    'filter' => [
                        'property' => 'SymfonyId',
                        'number' => ['equals' => $id],
                    ],
                ],
            ]
        );

        $data = $response->toArray(false);

        return $data['results'][0]['id'] ?? null;
    }

    private function createPage(Evenement $evenement): void
    {
        $this->httpClient->request('POST', self::BASE_URL . '/pages', [
            'headers' => $this->getHeaders(),
            'json' => [
                'parent' => ['database_id' => $this->notionDatabaseId],
                'properties' => $this->buildProperties($evenement),
            ],
        ])->getStatusCode();
    }

    private function updatePage(string $pageId, Evenement $evenement): void
    {
        $this->httpClient->request('PATCH', self::BASE_URL . '/pages/' . $pageId, [
            'headers' => $this->getHeaders(),
            'json' => [
                'properties' => $this->buildProperties($evenement),
            ],
        ])->getStatusCode();
    }

    private function buildProperties(Evenement $evenement): array
    {
        return [
            'Titre' => [
                'title' => [['text' => ['content' => $evenement->getTitre() ?? '']]],
            ],
            'SymfonyId' => [
                'number' => $evenement->getId(),
            ],
            'Statut' => [
                'select' => ['name' => $evenement->getStatut()],
            ],
            'Type' => [
                'select' => ['name' => $evenement->getType()],
            ],
            'DateDebut' => [
                'date' => $evenement->getDateDebut() !== null
                    ? ['start' => $evenement->getDateDebut()->format(\DateTimeInterface::ATOM)]
                    : null,
            ],
            'DateFin' => [
                'date' => $evenement->getDateFin() !== null
                    ? ['start' => $evenement->getDateFin()->format(\DateTimeInterface::ATOM)]
                    : null,
            ],
            'Capacite' => [
                'number' => $evenement->getCapaciteMax(),
            ],
            'Prix' => [
                'number' => $evenement->getPrix(),
            ],
            'Lieu' => [
                'rich_text' => [['text' => ['content' => $evenement->getLieu()?->getNom() ?? '']]],
            ],
            'Description' => [
                'rich_text' => [['text' => ['content' => mb_substr($evenement->getDescription() ?? '', 0, 2000)]]],
            ],
        ];
    }

    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->notionApiKey,
            'Notion-Version' => self::NOTION_VERSION,
        ];
    }
}
