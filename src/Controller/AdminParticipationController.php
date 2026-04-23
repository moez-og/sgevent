<?php

namespace App\Controller;

use App\Service\NotificationService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/participations')]
class AdminParticipationController extends AbstractController
{
    #[Route('', name: 'app_admin_participations', methods: ['GET'])]
    public function index(Request $request, Connection $connection): Response
    {
        $statusFilter = strtoupper(trim((string) $request->query->get('statut', '')));
        $annonceFilter = max(0, (int) $request->query->get('annonce', 0));
        $allowedStatuses = ['EN_ATTENTE', 'CONFIRMEE', 'REFUSEE', 'ANNULEE'];

        $whereParts = [];
        $params = [];
        if (in_array($statusFilter, $allowedStatuses, true)) {
            $whereParts[] = 'p.statut = ?';
            $params[] = $statusFilter;
        }

        if ($annonceFilter > 0) {
            $whereParts[] = 'p.annonce_id = ?';
            $params[] = $annonceFilter;
        }

        $where = '';
        if ($whereParts !== []) {
            $where = 'WHERE '.implode(' AND ', $whereParts);
        }

        $participations = $connection->fetchAllAssociative(
            "SELECT p.id, p.annonce_id, p.user_id, p.statut, p.contact_prefer, p.contact_value, p.commentaire, p.reponses_json, p.date_demande,
                    s.titre, s.ville, s.nb_places, s.user_id AS organisateur_id,
                    u.prenom, u.nom,
                    org.prenom AS organisateur_prenom, org.nom AS organisateur_nom
             FROM participation_annonce p
             INNER JOIN annonce_sortie s ON s.id = p.annonce_id
             INNER JOIN user u ON u.id = p.user_id
             LEFT JOIN user org ON org.id = s.user_id
             $where
                         ORDER BY CASE p.statut
                                                WHEN 'EN_ATTENTE' THEN 1
                                                WHEN 'CONFIRMEE' THEN 2
                                                WHEN 'REFUSEE' THEN 3
                                                WHEN 'ANNULEE' THEN 4
                                                ELSE 5
                                            END,
                                            p.date_demande DESC",
            $params
        );

        foreach ($participations as &$participation) {
            $participation['answers'] = [];
            $rawJson = (string) ($participation['reponses_json'] ?? '');
            if ($rawJson === '') {
                continue;
            }

            try {
                $decoded = json_decode($rawJson, true, 512, JSON_THROW_ON_ERROR);
                if (is_array($decoded)) {
                    $participation['answers'] = $decoded;
                }
            } catch (\JsonException) {
                $participation['answers'] = [];
            }
        }
        unset($participation);

        $counts = [
            'EN_ATTENTE' => (int) $connection->fetchOne("SELECT COUNT(*) FROM participation_annonce WHERE statut = 'EN_ATTENTE'"),
            'CONFIRMEE' => (int) $connection->fetchOne("SELECT COUNT(*) FROM participation_annonce WHERE statut = 'CONFIRMEE'"),
            'REFUSEE' => (int) $connection->fetchOne("SELECT COUNT(*) FROM participation_annonce WHERE statut = 'REFUSEE'"),
            'ANNULEE' => (int) $connection->fetchOne("SELECT COUNT(*) FROM participation_annonce WHERE statut = 'ANNULEE'"),
        ];

        $selectedAnnonce = null;
        if ($annonceFilter > 0) {
            $selectedAnnonce = $connection->fetchAssociative(
                'SELECT id, titre, ville, date_sortie FROM annonce_sortie WHERE id = ?',
                [$annonceFilter]
            );
        }

        return $this->render('admin/participation/index.html.twig', [
            'active' => 'participations',
            'participations' => $participations,
            'counts' => $counts,
            'statusFilter' => $statusFilter,
            'annonceFilter' => $annonceFilter,
            'selectedAnnonce' => $selectedAnnonce,
        ]);
    }

    #[Route('/{id}/accepter', name: 'app_admin_participations_accept', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function accept(int $id, Request $request, Connection $connection, NotificationService $notificationService): RedirectResponse
    {
        $redirectParams = $this->getIndexRedirectParams($request);
        if (!$this->isCsrfTokenValid('accept_participation_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin_participations', $redirectParams);
        }

        $participation = $connection->fetchAssociative('SELECT p.id, p.annonce_id, p.user_id, p.statut, p.nb_places, s.titre, s.nb_places AS sortie_places FROM participation_annonce p INNER JOIN annonce_sortie s ON s.id = p.annonce_id WHERE p.id = ?', [$id]);
        if (!$participation) {
            $this->addFlash('error', 'Participation introuvable.');
            return $this->redirectToRoute('app_admin_participations', $redirectParams);
        }

        try {
            $confirmedPlaces = (int) $connection->fetchOne(
                "SELECT COALESCE(SUM(nb_places), 0) FROM participation_annonce WHERE annonce_id = ? AND statut = 'CONFIRMEE' AND id <> ?",
                [(int) $participation['annonce_id'], $id]
            );
            $sortiePlaces = (int) ($participation['sortie_places'] ?? 0);
            $requestedPlaces = (int) ($participation['nb_places'] ?? 1);

            if ($sortiePlaces > 0 && ($confirmedPlaces + $requestedPlaces) > $sortiePlaces) {
                $this->addFlash('warning', 'Impossible de confirmer: places insuffisantes.');
                return $this->redirectToRoute('app_admin_participations', $redirectParams);
            }

            $connection->update('participation_annonce', ['statut' => 'CONFIRMEE'], ['id' => $id]);
            $senderId = $this->getAuthenticatedUserId();

            $notificationService->notifyUser(
                (int) $participation['user_id'],
                $senderId,
                'PARTICIPATION_CONFIRMED',
                'Participation acceptée',
                'Votre participation à la sortie "'.$participation['titre'].'" a été acceptée.',
                'annonce_sortie',
                (int) $participation['annonce_id'],
                ['statut' => 'CONFIRMEE', 'anchor' => 'participation']
            );

            $this->refreshSortieStatusByCapacity($connection, $notificationService, (int) $participation['annonce_id'], $senderId);
            $this->addFlash('success', 'Participation confirmée.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur lors de la confirmation: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_participations', $redirectParams);
    }

    #[Route('/{id}/refuser', name: 'app_admin_participations_refuse', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function refuse(int $id, Request $request, Connection $connection, NotificationService $notificationService): RedirectResponse
    {
        $redirectParams = $this->getIndexRedirectParams($request);
        if (!$this->isCsrfTokenValid('refuse_participation_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin_participations', $redirectParams);
        }

        $participation = $connection->fetchAssociative('SELECT p.id, p.annonce_id, p.user_id, s.titre FROM participation_annonce p INNER JOIN annonce_sortie s ON s.id = p.annonce_id WHERE p.id = ?', [$id]);
        if (!$participation) {
            $this->addFlash('error', 'Participation introuvable.');
            return $this->redirectToRoute('app_admin_participations', $redirectParams);
        }

        try {
            $connection->update('participation_annonce', ['statut' => 'REFUSEE'], ['id' => $id]);
            $senderId = $this->getAuthenticatedUserId();

            $notificationService->notifyUser(
                (int) $participation['user_id'],
                $senderId,
                'PARTICIPATION_REFUSED',
                'Demande refusée',
                'Votre demande pour la sortie "'.$participation['titre'].'" a été refusée.',
                'annonce_sortie',
                (int) $participation['annonce_id'],
                ['statut' => 'REFUSEE', 'anchor' => 'participation']
            );

            $this->refreshSortieStatusByCapacity($connection, $notificationService, (int) $participation['annonce_id'], $senderId);
            $this->addFlash('success', 'Participation refusée.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur lors du refus: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_participations', $redirectParams);
    }

    #[Route('/{id}/supprimer', name: 'app_admin_participations_delete', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, Connection $connection, NotificationService $notificationService): RedirectResponse
    {
        $redirectParams = $this->getIndexRedirectParams($request);
        if (!$this->isCsrfTokenValid('delete_participation_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_admin_participations', $redirectParams);
        }

        $participation = $connection->fetchAssociative('SELECT p.id, p.annonce_id, p.user_id, s.titre FROM participation_annonce p INNER JOIN annonce_sortie s ON s.id = p.annonce_id WHERE p.id = ?', [$id]);
        if (!$participation) {
            $this->addFlash('error', 'Participation introuvable.');
            return $this->redirectToRoute('app_admin_participations', $redirectParams);
        }

        try {
            $senderId = $this->getAuthenticatedUserId();
            $connection->delete('participation_annonce', ['id' => $id]);

            $notificationService->notifyUser(
                (int) $participation['user_id'],
                $senderId,
                'PARTICIPATION_DELETED',
                'Participation supprimée',
                'Votre participation pour la sortie "'.$participation['titre'].'" a été supprimée par un administrateur.',
                'annonce_sortie',
                (int) $participation['annonce_id'],
                ['statut' => 'SUPPRIMEE', 'anchor' => 'participation']
            );

            $this->refreshSortieStatusByCapacity($connection, $notificationService, (int) $participation['annonce_id'], $senderId);
            $this->addFlash('success', 'Participation supprimée.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur lors de la suppression: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_participations', $redirectParams);
    }

    /**
     * @return array<string, mixed>
     */
    private function getIndexRedirectParams(Request $request): array
    {
        $params = [];
        $annonce = max(0, (int) $request->query->get('annonce', 0));
        $statut = strtoupper(trim((string) $request->query->get('statut', '')));

        if ($annonce > 0) {
            $params['annonce'] = $annonce;
        }

        if (in_array($statut, ['EN_ATTENTE', 'CONFIRMEE', 'REFUSEE', 'ANNULEE'], true)) {
            $params['statut'] = $statut;
        }

        return $params;
    }

    private function refreshSortieStatusByCapacity(Connection $connection, NotificationService $notificationService, int $sortieId, ?int $senderId = null): void
    {
        $sortie = $connection->fetchAssociative('SELECT id, titre, nb_places, statut, user_id FROM annonce_sortie WHERE id = ?', [$sortieId]);
        if (!$sortie) {
            return;
        }

        $currentStatus = (string) ($sortie['statut'] ?? '');
        if (in_array($currentStatus, ['ANNULEE', 'TERMINEE'], true)) {
            return;
        }

        $nbPlaces = (int) ($sortie['nb_places'] ?? 0);
        $confirmed = (int) $connection->fetchOne(
            "SELECT COALESCE(SUM(nb_places), 0) FROM participation_annonce WHERE annonce_id = ? AND statut = 'CONFIRMEE'",
            [$sortieId]
        );

        if ($nbPlaces > 0 && $confirmed >= $nbPlaces && $currentStatus !== 'CLOTUREE') {
            $connection->update('annonce_sortie', ['statut' => 'CLOTUREE'], ['id' => $sortieId]);

            $pendingUserIds = array_map(
                static fn (array $row): int => (int) $row['user_id'],
                $connection->fetchAllAssociative(
                    "SELECT user_id FROM participation_annonce WHERE annonce_id = ? AND statut = 'EN_ATTENTE'",
                    [$sortieId]
                )
            );

            $notificationService->notifyMany(
                $pendingUserIds,
                $senderId,
                'SORTIE_CLOSED',
                'La sortie est complète',
                'La sortie "'.$sortie['titre'].'" est complète et vient d\'être clôturée.',
                'annonce_sortie',
                (int) $sortie['id'],
                ['statut' => 'CLOTUREE', 'anchor' => 'participation']
            );
            return;
        }

        if ($currentStatus === 'CLOTUREE' && $confirmed < $nbPlaces) {
            $connection->update('annonce_sortie', ['statut' => 'OUVERTE'], ['id' => $sortieId]);

            $interestedUserIds = array_map(
                static fn (array $row): int => (int) $row['user_id'],
                $connection->fetchAllAssociative(
                    "SELECT DISTINCT user_id FROM participation_annonce WHERE annonce_id = ? AND user_id <> ? AND statut IN ('EN_ATTENTE','REFUSEE','ANNULEE')",
                    [$sortieId, (int) $sortie['user_id']]
                )
            );

            $notificationService->notifyMany(
                $interestedUserIds,
                $senderId,
                'SORTIE_REOPENED',
                'Une place est disponible',
                'Une place est de nouveau disponible pour la sortie "'.$sortie['titre'].'".',
                'annonce_sortie',
                (int) $sortie['id'],
                ['statut' => 'OUVERTE', 'anchor' => 'participation']
            );
        }
    }

    private function getAuthenticatedUserId(): ?int
    {
        $user = $this->getUser();
        if (!$user instanceof \App\Entity\User) {
            return null;
        }

        return (int) $user->getId();
    }
}
