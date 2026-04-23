<?php

namespace App\Service;

use App\Repository\EvenementRepository;

class NotionSyncService
{
    public function __construct(
        private NotionService $notionService,
        private EvenementRepository $repository,
    ) {}

    public function sync(): array
    {
        $result = [
            'created_events' => 0,
            'linked_pages'   => 0,
            'created_pages'  => 0,
            'updated_pages'  => 0,
        ];

        foreach ($this->repository->findAll() as $evenement) {
            try {
                $synced = $this->notionService->syncEvenement($evenement);
                if ($synced) {
                    $result['updated_pages']++;
                }
            } catch (\Throwable) {
                // silently skip failed sync for individual events
            }
        }

        return $result;
    }
}
