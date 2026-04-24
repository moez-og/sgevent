<?php

namespace App\Service;

use App\Repository\EvenementRepository;

/**
 * Orchestrateur de sync Notion ↔ Symfony.
 * Délègue le travail réel à NotionService.
 */
class NotionSyncService
{
    public function __construct(
        private NotionService $notionService,
        private EvenementRepository $repository,
    ) {}

    /**
     * Sync complet : tous les événements → Notion.
     * Crée, met à jour, supprime les orphelins.
     *
     * @return array{created: int, updated: int, deleted: int, failed: int, errors: string[], total: int}
     */
    public function sync(): array
    {
        $evenements = $this->repository->findAll();
        return $this->notionService->syncAll($evenements);
    }

    /**
     * Teste la connexion Notion.
     * @return array{ok: bool, message: string, properties: string[]}
     */
    public function testConnection(): array
    {
        return $this->notionService->testConnection();
    }

    /**
     * Le service est-il configuré ?
     */
    public function isConfigured(): bool
    {
        return $this->notionService->isConfigured();
    }
}
