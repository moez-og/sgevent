<?php

namespace App\Controller;

use App\Entity\User;
use App\Service\NotificationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class NotificationController extends AbstractController
{
    #[Route('/notifications/api/feed', name: 'app_notifications_api_feed', methods: ['GET'])]
    public function feed(NotificationService $notificationService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json([
                'items' => [],
                'unread' => 0,
            ]);
        }

        $items = $notificationService->fetchLatestForUser((int) $user->getId(), 12);
        foreach ($items as &$item) {
            $entityType = (string) ($item['entity_type'] ?? '');
            $entityId = (int) ($item['entity_id'] ?? 0);
            $type = (string) ($item['type'] ?? '');
            $metadata = [];

            if (is_string($item['metadata_json'] ?? null) && (string) $item['metadata_json'] !== '') {
                try {
                    $decoded = json_decode((string) $item['metadata_json'], true, 512, JSON_THROW_ON_ERROR);
                    if (is_array($decoded)) {
                        $metadata = $decoded;
                    }
                } catch (\JsonException) {
                    $metadata = [];
                }
            }

            $anchor = '';
            if (isset($metadata['anchor']) && is_string($metadata['anchor']) && preg_match('/^[a-zA-Z0-9_-]+$/', $metadata['anchor'])) {
                $anchor = (string) $metadata['anchor'];
            } elseif ($type === 'PARTICIPATION_REQUESTED') {
                $anchor = 'demandes-attente';
            } elseif (str_starts_with($type, 'PARTICIPATION_') || str_starts_with($type, 'SORTIE_')) {
                $anchor = 'participation';
            }

            $item['url'] = null;
            if ($entityType === 'annonce_sortie' && $entityId > 0) {
                $item['url'] = $this->generateUrl('app_sorties_show', ['id' => $entityId]);
                if ($anchor !== '') {
                    $item['url'] .= '#'.$anchor;
                }
            } elseif (in_array($entityType, ['offre', 'reservation_offre', 'code_promo'], true)) {
                $offreId = 0;
                if (isset($metadata['offre_id'])) {
                    $offreId = (int) $metadata['offre_id'];
                } elseif ($entityType === 'offre' && $entityId > 0) {
                    $offreId = $entityId;
                }

                if ($offreId > 0) {
                    $item['url'] = $this->generateUrl('app_offres_show', ['id' => $offreId]);
                } else {
                    $item['url'] = $this->generateUrl('app_offres');
                }
            } elseif (str_starts_with($type, 'OFFRE_') || str_starts_with($type, 'PROMO_') || str_starts_with($type, 'RESERVATION_')) {
                $item['url'] = $this->generateUrl('app_offres');
            }

            if (str_starts_with($type, 'PARTICIPATION_') || str_starts_with($type, 'SORTIE_')) {
                $adminUrl = $this->isGranted('ROLE_ADMIN') ? $this->generateUrl('app_admin_participations') : null;
                if ($adminUrl !== null && $type === 'PARTICIPATION_REQUESTED') {
                    $adminUrl = $this->generateUrl('app_admin_participations', ['statut' => 'EN_ATTENTE']);
                }
                $item['admin_url'] = $adminUrl;
            } elseif (str_starts_with($type, 'OFFRE_') || str_starts_with($type, 'PROMO_') || str_starts_with($type, 'RESERVATION_') || in_array($entityType, ['offre', 'reservation_offre', 'code_promo'], true)) {
                $item['admin_url'] = $this->isGranted('ROLE_ADMIN') ? $this->generateUrl('app_admin_offres') : null;
            }

            unset($item['metadata_json']);
        }
        unset($item);

        $unread = $notificationService->countUnreadForUser((int) $user->getId());

        return $this->json([
            'items' => $items,
            'unread' => $unread,
        ]);
    }

    #[Route('/notifications/api/read-all', name: 'app_notifications_api_read_all', methods: ['POST'])]
    public function markAllRead(Request $request, NotificationService $notificationService): JsonResponse
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->json(['ok' => false], 401);
        }

        $token = (string) $request->request->get('_token', '');
        if (!$this->isCsrfTokenValid('notifications_read_all', $token)) {
            return $this->json(['ok' => false], 400);
        }

        $notificationService->markAllAsRead((int) $user->getId());

        return $this->json(['ok' => true]);
    }
}
