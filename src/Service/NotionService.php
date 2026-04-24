<?php

namespace App\Service;

use App\Entity\Evenement;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * NotionService v2 — Synchronisation bidirectionnelle Symfony ↔ Notion.
 *
 * Inspiré de NotionCalendarService.java :
 *  - Test de connexion + détection du schéma
 *  - Auto-création des propriétés manquantes
 *  - Sync "upsert" (create or update) par EventID
 *  - Nettoyage des orphelins Notion
 *  - Retour détaillé (created, updated, deleted, failed, errors)
 *
 * 100% GRATUIT — utilise l'API Notion (pas de carte bancaire).
 */
class NotionService
{
    private const NOTION_VERSION = '2022-06-28';
    private const BASE_URL = 'https://api.notion.com/v1';

    /** Nom détecté de la propriété titre (peut varier selon la DB) */
    private string $titlePropertyName = 'Titre';

    /** Dernière erreur (pour affichage dans le template) */
    private ?string $lastError = null;

    public function __construct(
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        private string $notionApiKey,
        private string $notionDatabaseId,
    ) {}

    // ════════════════════════════════════════════════════════════
    //  CONFIGURATION & TEST
    // ════════════════════════════════════════════════════════════

    public function isConfigured(): bool
    {
        return trim($this->notionApiKey) !== '' && trim($this->notionDatabaseId) !== '';
    }

    public function getLastError(): ?string
    {
        return $this->lastError;
    }

    /**
     * Teste la connexion à la base Notion.
     * Détecte la propriété titre et vérifie/crée les colonnes manquantes.
     *
     * @return array{ok: bool, message: string, properties: string[]}
     */
    public function testConnection(): array
    {
        if (!$this->isConfigured()) {
            $this->lastError = 'NOTION_TOKEN ou NOTION_DATABASE_ID non configuré.';
            return ['ok' => false, 'message' => $this->lastError, 'properties' => []];
        }

        try {
            $response = $this->httpClient->request(
                'GET',
                self::BASE_URL . '/databases/' . $this->notionDatabaseId,
                ['headers' => $this->getHeaders()]
            );

            if ($response->getStatusCode() !== 200) {
                $body = $response->toArray(false);
                $this->lastError = 'Erreur ' . $response->getStatusCode() . ': ' . ($body['message'] ?? 'Inconnu');
                return ['ok' => false, 'message' => $this->lastError, 'properties' => []];
            }

            $data = $response->toArray(false);
            $properties = $data['properties'] ?? [];

            // Détecter la propriété titre
            foreach ($properties as $name => $prop) {
                if (($prop['type'] ?? '') === 'title') {
                    $this->titlePropertyName = $name;
                    break;
                }
            }

            $existingProps = array_keys($properties);

            // Auto-créer les propriétés manquantes
            $created = $this->ensureDatabaseSchema($existingProps);

            $this->lastError = null;
            return [
                'ok' => true,
                'message' => 'Connexion réussie. Propriété titre: "' . $this->titlePropertyName . '".'
                    . ($created > 0 ? ' ' . $created . ' propriété(s) créée(s).' : ''),
                'properties' => array_merge($existingProps, $created > 0 ? ['(schéma mis à jour)'] : []),
            ];

        } catch (\Throwable $e) {
            $this->lastError = 'Erreur de connexion: ' . $e->getMessage();
            return ['ok' => false, 'message' => $this->lastError, 'properties' => []];
        }
    }

    /**
     * Vérifie et crée les propriétés manquantes dans la DB Notion.
     * Inspiré de ensureDatabaseSchema() du Java.
     *
     * @return int Nombre de propriétés créées
     */
    private function ensureDatabaseSchema(array $existingProps): int
    {
        $required = [
            'Date Début' => 'date',
            'Date Fin' => 'date',
            'Statut' => 'select',
            'Type' => 'select',
            'Lieu' => 'rich_text',
            'Prix' => 'number',
            'Capacité' => 'number',
            'EventID' => 'number',
            'Description' => 'rich_text',
        ];

        $missing = [];
        foreach ($required as $name => $type) {
            if (!in_array($name, $existingProps, true)) {
                $missing[$name] = [$type => new \stdClass()];
            }
        }

        if (empty($missing)) {
            return 0;
        }

        try {
            $this->httpClient->request(
                'PATCH',
                self::BASE_URL . '/databases/' . $this->notionDatabaseId,
                [
                    'headers' => $this->getHeaders(),
                    'json' => ['properties' => $missing],
                ]
            );
            return count($missing);
        } catch (\Throwable $e) {
            $this->logger->warning('Notion schema update failed', ['error' => $e->getMessage()]);
            return 0;
        }
    }

    // ════════════════════════════════════════════════════════════
    //  SYNC COMPLET (inspiré de syncAll() du Java)
    // ════════════════════════════════════════════════════════════

    /**
     * Synchronise tous les événements vers Notion.
     * Crée/met à jour/nettoie les orphelins.
     *
     * @param Evenement[] $evenements
     * @return array{created: int, updated: int, deleted: int, failed: int, errors: string[], total: int}
     */
    public function syncAll(array $evenements): array
    {
        $result = [
            'created' => 0,
            'updated' => 0,
            'deleted' => 0,
            'failed' => 0,
            'errors' => [],
            'total' => count($evenements),
        ];

        if (!$this->isConfigured()) {
            $result['errors'][] = 'Service non configuré.';
            return $result;
        }

        // D'abord, tester la connexion et préparer le schéma
        $test = $this->testConnection();
        if (!$test['ok']) {
            $result['errors'][] = $test['message'];
            return $result;
        }

        // Récupérer tous les EventID existants dans Notion
        $notionPages = $this->fetchAllNotionPages();

        // Set des EventIDs locaux (pour détecter les orphelins)
        $localIds = [];
        foreach ($evenements as $ev) {
            $localIds[$ev->getId()] = true;
        }

        // Sync chaque événement
        foreach ($evenements as $ev) {
            try {
                $existingPageId = $notionPages[$ev->getId()] ?? null;

                if ($existingPageId !== null) {
                    // Update
                    $this->updatePage($existingPageId, $ev);
                    $result['updated']++;
                } else {
                    // Create
                    $this->createPage($ev);
                    $result['created']++;
                }
            } catch (\Throwable $e) {
                $result['failed']++;
                $result['errors'][] = sprintf(
                    '[%d] %s: %s',
                    $ev->getId(),
                    mb_substr($ev->getTitre() ?? '', 0, 40),
                    $e->getMessage()
                );
            }
        }

        // Nettoyer les orphelins (pages Notion sans événement local)
        foreach ($notionPages as $eventId => $pageId) {
            if (!isset($localIds[$eventId])) {
                try {
                    $this->archivePage($pageId);
                    $result['deleted']++;
                } catch (\Throwable $e) {
                    $result['errors'][] = 'Orphelin ' . $pageId . ': ' . $e->getMessage();
                }
            }
        }

        return $result;
    }

    /**
     * Sync un seul événement (create or update).
     */
    public function syncEvenement(Evenement $evenement): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        try {
            $pageId = $this->findPageIdByEventId($evenement->getId());

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

    // ════════════════════════════════════════════════════════════
    //  CRUD NOTION PAGES
    // ════════════════════════════════════════════════════════════

    private function createPage(Evenement $ev): void
    {
        $response = $this->httpClient->request('POST', self::BASE_URL . '/pages', [
            'headers' => $this->getHeaders(),
            'json' => [
                'parent' => ['database_id' => $this->notionDatabaseId],
                'properties' => $this->buildProperties($ev),
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            $body = $response->toArray(false);
            throw new \RuntimeException($body['message'] ?? 'HTTP ' . $response->getStatusCode());
        }
    }

    private function updatePage(string $pageId, Evenement $ev): void
    {
        $response = $this->httpClient->request('PATCH', self::BASE_URL . '/pages/' . $pageId, [
            'headers' => $this->getHeaders(),
            'json' => [
                'properties' => $this->buildProperties($ev),
            ],
        ]);

        if ($response->getStatusCode() !== 200) {
            $body = $response->toArray(false);
            throw new \RuntimeException($body['message'] ?? 'HTTP ' . $response->getStatusCode());
        }
    }

    private function archivePage(string $pageId): void
    {
        $this->httpClient->request('PATCH', self::BASE_URL . '/pages/' . $pageId, [
            'headers' => $this->getHeaders(),
            'json' => ['archived' => true],
        ]);
    }

    // ════════════════════════════════════════════════════════════
    //  QUERIES
    // ════════════════════════════════════════════════════════════

    private function findPageIdByEventId(int $id): ?string
    {
        try {
            $response = $this->httpClient->request(
                'POST',
                self::BASE_URL . '/databases/' . $this->notionDatabaseId . '/query',
                [
                    'headers' => $this->getHeaders(),
                    'json' => [
                        'filter' => [
                            'property' => 'EventID',
                            'number' => ['equals' => $id],
                        ],
                    ],
                ]
            );
            $data = $response->toArray(false);
            return $data['results'][0]['id'] ?? null;
        } catch (\Throwable) {
            // Fallback: try SymfonyId (compat ancien schéma)
            try {
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
            } catch (\Throwable) {
                return null;
            }
        }
    }

    /**
     * Récupère TOUTES les pages de la DB Notion.
     * @return array<int, string> eventId → notionPageId
     */
    private function fetchAllNotionPages(): array
    {
        $map = [];
        $startCursor = null;

        do {
            try {
                $body = [];
                if ($startCursor !== null) {
                    $body['start_cursor'] = $startCursor;
                }

                $response = $this->httpClient->request(
                    'POST',
                    self::BASE_URL . '/databases/' . $this->notionDatabaseId . '/query',
                    [
                        'headers' => $this->getHeaders(),
                        'json' => $body ?: new \stdClass(),
                    ]
                );

                $data = $response->toArray(false);
                $results = $data['results'] ?? [];

                foreach ($results as $page) {
                    $pageId = $page['id'] ?? null;
                    if (!$pageId) continue;

                    // Extraire EventID
                    $eventId = $page['properties']['EventID']['number'] ?? null;
                    if ($eventId === null) {
                        // Fallback SymfonyId
                        $eventId = $page['properties']['SymfonyId']['number'] ?? null;
                    }
                    if ($eventId !== null) {
                        $map[(int) $eventId] = $pageId;
                    }
                }

                $startCursor = ($data['has_more'] ?? false) ? ($data['next_cursor'] ?? null) : null;
            } catch (\Throwable) {
                break;
            }
        } while ($startCursor !== null);

        return $map;
    }

    // ════════════════════════════════════════════════════════════
    //  PROPERTIES BUILDER
    // ════════════════════════════════════════════════════════════

    private function buildProperties(Evenement $ev): array
    {
        $props = [
            $this->titlePropertyName => [
                'title' => [['text' => ['content' => $ev->getTitre() ?? '']]],
            ],
            'EventID' => [
                'number' => $ev->getId(),
            ],
            'Statut' => [
                'select' => ['name' => $ev->getStatut()],
            ],
            'Type' => [
                'select' => ['name' => $ev->getType()],
            ],
            'Capacité' => [
                'number' => $ev->getCapaciteMax(),
            ],
            'Prix' => [
                'number' => $ev->getPrix(),
            ],
        ];

        if ($ev->getDateDebut() !== null) {
            $props['Date Début'] = ['date' => ['start' => $ev->getDateDebut()->format(\DateTimeInterface::ATOM)]];
        }
        if ($ev->getDateFin() !== null) {
            $props['Date Fin'] = ['date' => ['start' => $ev->getDateFin()->format(\DateTimeInterface::ATOM)]];
        }

        $lieu = $ev->getLieu()?->getNom() ?? '';
        $props['Lieu'] = ['rich_text' => $lieu !== '' ? [['text' => ['content' => $lieu]]] : []];

        $desc = mb_substr($ev->getDescription() ?? '', 0, 1900);
        $props['Description'] = ['rich_text' => $desc !== '' ? [['text' => ['content' => $desc]]] : []];

        return $props;
    }

    private function getHeaders(): array
    {
        return [
            'Authorization' => 'Bearer ' . $this->notionApiKey,
            'Notion-Version' => self::NOTION_VERSION,
        ];
    }
}
