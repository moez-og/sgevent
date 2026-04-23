<?php

namespace App\Controller;

use App\Entity\EvaluationLieu;
use App\Entity\User;
use App\Service\OffreManager;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class FrontController extends AbstractController
{
    #[Route('/home', name: 'app_home')]
    public function home(Connection $connection): Response
    {
        $notificationData = $this->getNotificationData($connection);
        $homeOffers = $this->enrichOffersCountdown($this->fetchAll($connection, "
            SELECT o.id, o.titre, o.type, o.pourcentage, o.date_fin, l.nom AS lieu_nom, l.ville,
                   CASE
                       WHEN TIMESTAMPDIFF(HOUR, NOW(), CONCAT(o.date_fin, ' 23:59:59')) BETWEEN 0 AND 24 THEN 1
                       ELSE 0
                   END AS expiring_soon
            FROM offre o
            LEFT JOIN lieu l ON l.id = o.lieu_id
            WHERE (LOWER(o.statut) IN ('active', 'actif') OR o.statut IS NULL OR o.statut = '')
              AND o.date_fin >= CURDATE()
            ORDER BY expiring_soon DESC, o.date_fin ASC, o.id DESC
            LIMIT 6
        "));

        return $this->render('front/home/index.html.twig', [
            'active' => 'home',
            'stats' => $this->getFrontStats($connection),
            'cities' => $this->fetchAll($connection, "
                SELECT ville, COUNT(*) AS total, MIN(categorie) AS categorie
                FROM lieu
                GROUP BY ville
                ORDER BY total DESC, ville ASC
                LIMIT 6
            "),
            'places' => $this->fetchAll($connection, "
                SELECT id, nom, ville, categorie, type, budget_min, budget_max
                FROM lieu
                ORDER BY id DESC
                LIMIT 6
            "),
            'sorties' => $this->fetchAll($connection, "
                SELECT id, titre, ville, type_activite, date_sortie, budget_max, statut, nb_places, image_url, lieu_texte
                FROM annonce_sortie
                ORDER BY date_sortie ASC
                LIMIT 6
            "),
            'offres' => $homeOffers,
            'events' => $this->fetchAll($connection, "
                SELECT e.id, e.titre, e.type, e.date_debut, e.prix, l.nom AS lieu_nom, l.ville
                FROM evenement e
                LEFT JOIN lieu l ON l.id = e.lieu_id
                ORDER BY e.date_debut ASC
                LIMIT 6
            "),
            'userChip' => $this->fetchOne($connection, "
                SELECT prenom, nom, role, email, imageUrl
                FROM user
                ORDER BY id ASC
                LIMIT 1
            "),
            'notificationData' => $notificationData,
        ]);
    }

    #[Route('/lieux', name: 'app_lieux')]
    public function lieux(Connection $connection): Response
    {
        return $this->render('front/lieu/index.html.twig', [
            'active' => 'lieux',
            'places' => $this->fetchAll($connection, "
                SELECT id, nom, ville, adresse, categorie, type, budget_min, budget_max, site_web, instagram
                FROM lieu
                ORDER BY ville ASC, nom ASC
            "),
            'notificationData' => $this->getNotificationData($connection),
        ]);
    }

    #[Route('/lieux/{id}', name: 'app_lieu_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function lieuShow(int $id, Connection $connection): Response
    {
        $lieu = $connection->fetchAssociative(
            "SELECT id, nom, ville, adresse, categorie, type, budget_min, budget_max, description, telephone, site_web, instagram, image_url, latitude, longitude
             FROM lieu
             WHERE id = ?",
            [$id]
        );

        if (!$lieu) {
            throw $this->createNotFoundException('Lieu introuvable.');
        }

        $galleryImages = $connection->fetchFirstColumn(
            'SELECT image_url FROM lieu_image WHERE lieu_id = ? ORDER BY ordre ASC, id ASC',
            [$id]
        );

        if (($galleryImages === [] || $galleryImages === null) && !empty($lieu['image_url'])) {
            $galleryImages = [(string) $lieu['image_url']];
        }

        $horaires = $connection->fetchAllAssociative(
            "SELECT jour, ouvert,
                    TIME_FORMAT(heure_ouverture_1, '%H:%i') AS heure_ouverture_1,
                    TIME_FORMAT(heure_fermeture_1, '%H:%i') AS heure_fermeture_1,
                    TIME_FORMAT(heure_ouverture_2, '%H:%i') AS heure_ouverture_2,
                    TIME_FORMAT(heure_fermeture_2, '%H:%i') AS heure_fermeture_2
             FROM lieu_horaire
             WHERE lieu_id = ?
             ORDER BY id ASC",
            [$id]
        );

        $offres = $connection->fetchAllAssociative(
            "SELECT id, titre, pourcentage, date_fin
             FROM offre
             WHERE lieu_id = ?
             ORDER BY date_fin ASC, id DESC",
            [$id]
        );

        $evaluations = $connection->fetchAllAssociative(
            "SELECT e.id, e.user_id, e.note, e.commentaire, e.date_evaluation, e.updated_at, u.prenom, u.nom
             FROM evaluation_lieu e
             LEFT JOIN user u ON u.id = e.user_id
             WHERE e.lieu_id = ?
             ORDER BY COALESCE(e.updated_at, e.date_evaluation) DESC",
            [$id]
        );

        $evaluationStats = $connection->fetchAssociative(
            'SELECT COUNT(*) AS total, ROUND(AVG(note), 1) AS moyenne FROM evaluation_lieu WHERE lieu_id = ?',
            [$id]
        ) ?: ['total' => 0, 'moyenne' => null];

        $currentUserEvaluation = null;
        $user = $this->getUser();
        if ($user instanceof User) {
            $currentUserEvaluation = $connection->fetchAssociative(
                'SELECT id, note, commentaire, date_evaluation, updated_at FROM evaluation_lieu WHERE lieu_id = ? AND user_id = ? LIMIT 1',
                [$id, $user->getId()]
            );
        }

        return $this->render('front/lieu/show.html.twig', [
            'active' => 'lieux',
            'lieu' => $lieu,
            'galleryImages' => $galleryImages ?? [],
            'horaires' => $horaires,
            'offres' => $offres,
            'evaluations' => $evaluations,
            'evaluationStats' => $evaluationStats,
            'currentUserEvaluation' => $currentUserEvaluation,
            'notificationData' => $this->getNotificationData($connection),
        ]);
    }

    #[Route('/lieux/{id}/evaluations', name: 'app_lieu_evaluation_create', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function createLieuEvaluation(int $id, Request $request, Connection $connection, ValidatorInterface $validator): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Veuillez vous connecter pour ajouter un avis.');
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('front_eval_create_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_lieu_show', ['id' => $id]);
        }

        $note = (int) $request->request->get('note', 0);
        $commentaire = trim((string) $request->request->get('commentaire', ''));

        $evaluationInput = (new EvaluationLieu())
            ->setNote($note)
            ->setCommentaire($commentaire !== '' ? $commentaire : null)
            ->setDate_evaluation(new \DateTimeImmutable());

        $violations = $validator->validate($evaluationInput);
        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $this->addFlash('error', (string) $violation->getMessage());
            }
            return $this->redirectToRoute('app_lieu_show', ['id' => $id]);
        }

        $existing = $connection->fetchOne(
            'SELECT id FROM evaluation_lieu WHERE lieu_id = ? AND user_id = ? LIMIT 1',
            [$id, $user->getId()]
        );

        if ($existing) {
            $this->addFlash('error', 'Vous avez déjà laissé un avis.');
            return $this->redirectToRoute('app_lieu_show', ['id' => $id]);
        }

        try {
            $connection->insert('evaluation_lieu', [
                'lieu_id' => $id,
                'user_id' => $user->getId(),
                'note' => $note,
                'commentaire' => $commentaire !== '' ? $commentaire : null,
                'date_evaluation' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
                'updated_at' => null,
            ]);
            $this->addFlash('success', 'Avis publié avec succès.');
        } catch (\Throwable) {
            $this->addFlash('error', 'Impossible de publier cet avis pour le moment.');
        }

        return $this->redirectToRoute('app_lieu_show', ['id' => $id]);
    }

    #[Route('/lieux/{id}/evaluations/{evaluationId}/update', name: 'app_lieu_evaluation_update', methods: ['POST'], requirements: ['id' => '\\d+', 'evaluationId' => '\\d+'])]
    public function updateLieuEvaluation(int $id, int $evaluationId, Request $request, Connection $connection, ValidatorInterface $validator): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Veuillez vous connecter pour modifier un avis.');
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('front_eval_update_'.$evaluationId, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_lieu_show', ['id' => $id]);
        }

        $ownedEvaluation = $connection->fetchOne(
            'SELECT id FROM evaluation_lieu WHERE id = ? AND lieu_id = ? AND user_id = ? LIMIT 1',
            [$evaluationId, $id, $user->getId()]
        );

        if (!$ownedEvaluation) {
            $this->addFlash('error', 'Avis introuvable ou non autorisé.');
            return $this->redirectToRoute('app_lieu_show', ['id' => $id]);
        }

        $note = (int) $request->request->get('note', 0);
        $commentaire = trim((string) $request->request->get('commentaire', ''));

        $evaluationInput = (new EvaluationLieu())
            ->setNote($note)
            ->setCommentaire($commentaire !== '' ? $commentaire : null)
            ->setDate_evaluation(new \DateTimeImmutable());

        $violations = $validator->validate($evaluationInput);
        if (count($violations) > 0) {
            foreach ($violations as $violation) {
                $this->addFlash('error', (string) $violation->getMessage());
            }
            return $this->redirectToRoute('app_lieu_show', ['id' => $id]);
        }

        $connection->update('evaluation_lieu', [
            'note' => $note,
            'commentaire' => $commentaire !== '' ? $commentaire : null,
            'updated_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
        ], [
            'id' => $evaluationId,
        ]);

        $this->addFlash('success', 'Avis mis à jour.');

        return $this->redirectToRoute('app_lieu_show', ['id' => $id]);
    }

    #[Route('/lieux/{id}/evaluations/{evaluationId}/delete', name: 'app_lieu_evaluation_delete', methods: ['POST'], requirements: ['id' => '\\d+', 'evaluationId' => '\\d+'])]
    public function deleteLieuEvaluation(int $id, int $evaluationId, Request $request, Connection $connection): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Veuillez vous connecter pour supprimer un avis.');
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('front_eval_delete_'.$evaluationId, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_lieu_show', ['id' => $id]);
        }

        $deleted = $connection->executeStatement(
            'DELETE FROM evaluation_lieu WHERE id = ? AND lieu_id = ? AND user_id = ?',
            [$evaluationId, $id, $user->getId()]
        );

        if ($deleted > 0) {
            $this->addFlash('success', 'Avis supprimé.');
        } else {
            $this->addFlash('error', 'Avis introuvable ou non autorisé.');
        }

        return $this->redirectToRoute('app_lieu_show', ['id' => $id]);
    }

    #[Route('/sorties', name: 'app_sorties')]
    public function sorties(Request $request, Connection $connection): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $status = strtoupper(trim((string) $request->query->get('status', '')));
        $sort = trim((string) $request->query->get('sort', 'recent'));

        $whereParts = [];
        $params = [];

        if ($query !== '') {
            $whereParts[] = "(LOWER(s.titre) LIKE LOWER(?) OR LOWER(COALESCE(s.description, '')) LIKE LOWER(?) OR LOWER(s.ville) LIKE LOWER(?) OR LOWER(COALESCE(s.type_activite, '')) LIKE LOWER(?) OR LOWER(COALESCE(s.lieu_texte, '')) LIKE LOWER(?) OR LOWER(CONCAT(COALESCE(u.prenom, ''), ' ', COALESCE(u.nom, ''))) LIKE LOWER(?))";
            $like = '%'.$query.'%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        $allowedStatuses = ['OUVERTE', 'CLOTUREE', 'ANNULEE', 'TERMINEE'];
        if (in_array($status, $allowedStatuses, true)) {
            $whereParts[] = 's.statut = ?';
            $params[] = $status;
        } else {
            $status = '';
        }

        $sortSql = match ($sort) {
            'date_asc' => 's.date_sortie ASC',
            'date_desc', 'recent' => 's.date_sortie DESC',
            'title_asc' => 's.titre ASC',
            'title_desc' => 's.titre DESC',
            'city_asc' => 's.ville ASC',
            'places_desc' => 's.nb_places DESC',
            'status_asc' => 's.statut ASC',
            'creator_asc' => "COALESCE(u.prenom, '') ASC, COALESCE(u.nom, '') ASC",
            default => 's.date_sortie DESC',
        };
        if (!in_array($sort, ['date_asc', 'date_desc', 'recent', 'title_asc', 'title_desc', 'city_asc', 'places_desc', 'status_asc', 'creator_asc'], true)) {
            $sort = 'recent';
        }

        $whereSql = $whereParts === [] ? '' : ' WHERE '.implode(' AND ', $whereParts);

        $page = max(1, (int) $request->query->get('page', 1));
        $pageSize = 6;
        $total = (int) $connection->fetchOne(
            'SELECT COUNT(*)
             FROM annonce_sortie s
             LEFT JOIN user u ON u.id = s.user_id'
             .$whereSql,
            $params
        );
        $totalPages = max(1, (int) ceil($total / $pageSize));
        $page = min($page, $totalPages);
        $offset = ($page - 1) * $pageSize;

        return $this->render('front/sortie/index.html.twig', [
            'active' => 'sorties',
            'sorties' => $this->fetchAll(
                $connection,
                'SELECT s.id, s.user_id, s.titre, s.description, s.ville, s.lieu_texte, s.point_rencontre,
                        s.type_activite, s.date_sortie, s.budget_max, s.nb_places, s.statut, s.image_url, s.questions_json,
                        u.prenom, u.nom, u.imageUrl AS user_image_url
                 FROM annonce_sortie s
                 LEFT JOIN user u ON u.id = s.user_id
                 '.$whereSql.'
                 ORDER BY '.$sortSql.'
                 LIMIT '.$pageSize.' OFFSET '.$offset
            ,
                $params
            ),
            'page' => $page,
            'pageSize' => $pageSize,
            'total' => $total,
            'totalPages' => $totalPages,
            'filters' => [
                'q' => $query,
                'status' => $status,
                'sort' => $sort,
            ],
            'notificationData' => $this->getNotificationData($connection),
        ]);
    }

    #[Route('/search', name: 'app_global_search', methods: ['GET'])]
    public function globalSearch(Request $request, Connection $connection): Response
    {
        $raw = trim((string) $request->query->get('q', ''));
        $query = mb_substr($raw, 0, 80);

        $sorties = [];
        $lieux = [];
        $offres = [];
        $evenements = [];
        $users = [];

        if ($query !== '' && mb_strlen($query) >= 2) {
            $like = '%'.$query.'%';

            $sorties = $this->fetchAll($connection,
                'SELECT id, titre, ville, type_activite, date_sortie, statut
                 FROM annonce_sortie
                 WHERE titre LIKE ? OR ville LIKE ? OR type_activite LIKE ? OR description LIKE ? OR lieu_texte LIKE ?
                 ORDER BY date_sortie DESC
                 LIMIT 8',
                [$like, $like, $like, $like, $like]
            );

            $lieux = $this->fetchAll($connection,
                'SELECT id, nom, ville, categorie, type
                 FROM lieu
                 WHERE nom LIKE ? OR ville LIKE ? OR categorie LIKE ? OR type LIKE ? OR adresse LIKE ?
                 ORDER BY nom ASC
                 LIMIT 8',
                [$like, $like, $like, $like, $like]
            );

            $offres = $this->fetchAll($connection,
                'SELECT o.id, o.titre, o.type, o.pourcentage, o.date_fin, l.nom AS lieu_nom
                 FROM offre o
                 LEFT JOIN lieu l ON l.id = o.lieu_id
                 WHERE o.titre LIKE ? OR o.type LIKE ? OR o.description LIKE ? OR l.nom LIKE ?
                 ORDER BY o.date_fin DESC
                 LIMIT 8',
                [$like, $like, $like, $like]
            );

            $evenements = $this->fetchAll($connection,
                'SELECT e.id, e.titre, e.type, e.date_debut, l.nom AS lieu_nom, l.ville
                 FROM evenement e
                 LEFT JOIN lieu l ON l.id = e.lieu_id
                 WHERE e.titre LIKE ? OR e.type LIKE ? OR e.description LIKE ? OR l.nom LIKE ? OR l.ville LIKE ?
                 ORDER BY e.date_debut DESC
                 LIMIT 8',
                [$like, $like, $like, $like, $like]
            );

            $users = $this->fetchAll($connection,
                'SELECT id, prenom, nom, role
                 FROM user
                 WHERE prenom LIKE ? OR nom LIKE ? OR email LIKE ?
                 ORDER BY prenom ASC, nom ASC
                 LIMIT 8',
                [$like, $like, $like]
            );
        }

        return $this->render('front/search/index.html.twig', [
            'active' => null,
            'query' => $query,
            'sorties' => $sorties,
            'lieux' => $lieux,
            'offres' => $offres,
            'evenements' => $evenements,
            'users' => $users,
            'notificationData' => $this->getNotificationData($connection),
        ]);
    }

    #[Route('/offres', name: 'app_offres')]
    public function offres(Request $request, Connection $connection, OffreManager $offreManager): Response
    {
        $lieuId = (int) $request->query->get('lieu', 0);
        $selectedSort = strtolower(trim((string) $request->query->get('sort', 'urgent')));
        $allowedSorts = ['urgent', 'date_fin_asc', 'date_fin_desc', 'reduction_desc', 'reduction_asc', 'titre_asc', 'titre_desc'];
        if (!in_array($selectedSort, $allowedSorts, true)) {
            $selectedSort = 'urgent';
        }

        $offres = $this->enrichOffersCountdown($offreManager->findActiveByLieu($lieuId > 0 ? $lieuId : null, $selectedSort));

        return $this->render('front/offre/index.html.twig', [
            'active' => 'offres',
            'offres' => $offres,
            'lieux' => $this->fetchAll($connection, 'SELECT id, nom FROM lieu ORDER BY nom ASC'),
            'selectedLieu' => $lieuId,
            'selectedSort' => $selectedSort,
            'notificationData' => $this->getNotificationData($connection),
        ]);
    }

    #[Route('/offres/{id}', name: 'app_offres_show', methods: ['GET'])]
    public function offreShow(int $id, Connection $connection): Response
    {
        $offre = $connection->fetchAssociative(
            "SELECT o.id, o.titre, o.description, o.type, o.pourcentage, o.date_debut, o.date_fin, o.statut, o.lieu_id,
                    l.nom AS lieu_nom, l.ville,
                    CASE
                        WHEN TIMESTAMPDIFF(HOUR, NOW(), CONCAT(o.date_fin, ' 23:59:59')) BETWEEN 0 AND 24 THEN 1
                        ELSE 0
                    END AS expiring_soon
             FROM offre o
             LEFT JOIN lieu l ON l.id = o.lieu_id
             WHERE o.id = ?",
            [$id]
        );

        if (!$offre) {
            throw $this->createNotFoundException('Offre introuvable.');
        }

        $offre = $this->enrichOfferCountdown($offre);

        $currentUser = $this->getUser();
        $userPromoCodes = [];
        if ($currentUser instanceof User) {
            $userPromoCodes = $connection->fetchAllAssociative(
                'SELECT id, qr_image_url, date_generation, date_expiration, statut
                 FROM code_promo
                 WHERE offre_id = ? AND user_id = ?
                 ORDER BY id DESC',
                [$id, $currentUser->getId()]
            );
        }

        $userReservations = [];
        if ($currentUser instanceof User) {
            $userReservations = $connection->fetchAllAssociative(
                "SELECT id, date_reservation, nombre_personnes, statut, note, created_at
                 FROM reservation_offre
                 WHERE offre_id = ? AND user_id = ?
                 ORDER BY id DESC",
                [$id, $currentUser->getId()]
            );
        }

        return $this->render('front/offre/show.html.twig', [
            'active' => 'offres',
            'offre' => $offre,
            'userPromoCodes' => $userPromoCodes,
            'userReservations' => $userReservations,
            'notificationData' => $this->getNotificationData($connection),
        ]);
    }

    #[Route('/notifications/{id}/read', name: 'app_notifications_read', methods: ['POST'])]
    public function markNotificationRead(int $id, Request $request, Connection $connection): Response
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            if ($this->isCsrfTokenValid('notification_read_'.$id, (string) $request->request->get('_token', ''))) {
                $connection->executeStatement(
                    'UPDATE notifications SET read_at = NOW() WHERE id = ? AND receiver_id = ? AND read_at IS NULL',
                    [$id, (int) $user->getId()]
                );
            }
        }

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_home'));
    }

    #[Route('/notifications/read-all', name: 'app_notifications_read_all', methods: ['POST'])]
    public function markAllNotificationsRead(Request $request, Connection $connection): Response
    {
        $user = $this->getUser();
        if ($user instanceof User) {
            if ($this->isCsrfTokenValid('notifications_read_all', (string) $request->request->get('_token', ''))) {
                $connection->executeStatement(
                    'UPDATE notifications SET read_at = NOW() WHERE receiver_id = ? AND read_at IS NULL',
                    [(int) $user->getId()]
                );
            }
        }

        return $this->redirect($request->headers->get('referer') ?: $this->generateUrl('app_home'));
    }

    #[Route('/offres/{id}/reserve', name: 'app_offres_reserve', methods: ['POST'])]
    public function reserveOffre(int $id, Request $request, Connection $connection): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Veuillez vous connecter pour réserver une offre.');
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('reserve_offre_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_offres_show', ['id' => $id]);
        }

        $offre = $connection->fetchAssociative('SELECT id, statut, date_fin, lieu_id FROM offre WHERE id = ?', [$id]);
        if (!$offre) {
            throw $this->createNotFoundException('Offre introuvable.');
        }

        $offreStatus = strtolower(trim((string) ($offre['statut'] ?? '')));
        if (!in_array($offreStatus, ['active', 'actif', ''], true)) {
            $this->addFlash('error', 'Cette offre n\'est pas disponible à la réservation.');
            return $this->redirectToRoute('app_offres_show', ['id' => $id]);
        }

        if (!empty($offre['date_fin']) && new \DateTimeImmutable((string) $offre['date_fin']) < new \DateTimeImmutable('today')) {
            $this->addFlash('error', 'Cette offre est expirée.');
            return $this->redirectToRoute('app_offres_show', ['id' => $id]);
        }

        $nombrePersonnes = (int) $request->request->get('nombre_personnes', 1);
        $note = trim((string) $request->request->get('note', ''));

        if ($nombrePersonnes < 1 || $nombrePersonnes > 20) {
            $this->addFlash('error', 'Le nombre de personnes doit être entre 1 et 20.');
            return $this->redirectToRoute('app_offres_show', ['id' => $id]);
        }

        if (mb_strlen($note) > 300) {
            $this->addFlash('error', 'La note ne doit pas dépasser 300 caractères.');
            return $this->redirectToRoute('app_offres_show', ['id' => $id]);
        }

        $existingReservation = (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM reservation_offre
             WHERE user_id = ? AND offre_id = ? AND statut IN ('EN_ATTENTE', 'CONFIRMÉE')",
            [(int) $user->getId(), $id]
        );

        if ($existingReservation > 0) {
            $this->addFlash('error', 'Vous avez déjà une réservation en cours ou confirmée pour cette offre.');
            return $this->redirectToRoute('app_offres_show', ['id' => $id]);
        }

        $cooldownAttempts = (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM reservation_offre
             WHERE user_id = ? AND offre_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 10 MINUTE)",
            [(int) $user->getId(), $id]
        );

        if ($cooldownAttempts > 0) {
            $this->addFlash('error', 'Merci de patienter quelques minutes avant d’envoyer une nouvelle demande.');
            return $this->redirectToRoute('app_offres_show', ['id' => $id]);
        }

        try {
            $connection->insert('reservation_offre', [
                'user_id' => (int) $user->getId(),
                'offre_id' => $id,
                'lieu_id' => (int) ($offre['lieu_id'] ?? 0),
                'date_reservation' => (new \DateTimeImmutable('today'))->format('Y-m-d'),
                'nombre_personnes' => $nombrePersonnes,
                'statut' => 'EN_ATTENTE',
                'note' => $note !== '' ? $note : null,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ]);

            $this->addFlash('success', 'Réservation envoyée. Elle sera confirmée par l\'administrateur.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur réservation: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_offres_show', ['id' => $id]);
    }

    #[Route('/offres/{offreId}/reservation/{reservationId}/update', name: 'app_offres_reservation_update', methods: ['POST'])]
    public function updateReservation(int $offreId, int $reservationId, Request $request, Connection $connection): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Veuillez vous connecter pour modifier une réservation.');
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('update_reservation_'.$reservationId, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_offres_show', ['id' => $offreId]);
        }

        $reservation = $connection->fetchAssociative(
            'SELECT id, statut FROM reservation_offre WHERE id = ? AND offre_id = ? AND user_id = ?',
            [$reservationId, $offreId, (int) $user->getId()]
        );

        if (!$reservation) {
            $this->addFlash('error', 'Réservation introuvable.');
            return $this->redirectToRoute('app_offres_show', ['id' => $offreId]);
        }

        if ((string) $reservation['statut'] !== 'EN_ATTENTE') {
            $this->addFlash('error', 'Seules les réservations en attente peuvent être modifiées.');
            return $this->redirectToRoute('app_offres_show', ['id' => $offreId]);
        }

        $nombrePersonnes = (int) $request->request->get('nombre_personnes', 1);
        $note = trim((string) $request->request->get('note', ''));

        if ($nombrePersonnes < 1 || $nombrePersonnes > 20) {
            $this->addFlash('error', 'Le nombre de personnes doit être entre 1 et 20.');
            return $this->redirectToRoute('app_offres_show', ['id' => $offreId]);
        }

        if (mb_strlen($note) > 300) {
            $this->addFlash('error', 'La note ne doit pas dépasser 300 caractères.');
            return $this->redirectToRoute('app_offres_show', ['id' => $offreId]);
        }

        try {
            $connection->update('reservation_offre', [
                'nombre_personnes' => $nombrePersonnes,
                'note' => $note !== '' ? $note : null,
                'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ], ['id' => $reservationId]);

            $this->addFlash('success', 'Réservation mise à jour avec succès.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur modification réservation: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_offres_show', ['id' => $offreId]);
    }

    #[Route('/offres/{offreId}/reservation/{reservationId}/cancel', name: 'app_offres_reservation_cancel', methods: ['POST'])]
    public function cancelReservation(int $offreId, int $reservationId, Request $request, Connection $connection): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Veuillez vous connecter pour annuler une réservation.');
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('cancel_reservation_'.$reservationId, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_offres_show', ['id' => $offreId]);
        }

        $reservation = $connection->fetchAssociative(
            'SELECT id, statut FROM reservation_offre WHERE id = ? AND offre_id = ? AND user_id = ?',
            [$reservationId, $offreId, (int) $user->getId()]
        );

        if (!$reservation) {
            $this->addFlash('error', 'Réservation introuvable.');
            return $this->redirectToRoute('app_offres_show', ['id' => $offreId]);
        }

        if ((string) $reservation['statut'] !== 'EN_ATTENTE') {
            $this->addFlash('error', 'Seules les réservations en attente peuvent être annulées.');
            return $this->redirectToRoute('app_offres_show', ['id' => $offreId]);
        }

        try {
            $connection->update('reservation_offre', ['statut' => 'ANNULEE'], ['id' => $reservationId]);
            $this->addFlash('success', 'Réservation annulée.');
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur annulation réservation: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_offres_show', ['id' => $offreId]);
    }

    #[Route('/offres/{id}/generate-code', name: 'app_offres_generate_code', methods: ['POST'])]
    public function generateCodePromo(int $id, Request $request, Connection $connection): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Veuillez vous connecter pour générer un code promo.');
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('generate_promo_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_offres_show', ['id' => $id]);
        }

        $offre = $connection->fetchAssociative('SELECT id, date_fin FROM offre WHERE id = ?', [$id]);
        if (!$offre) {
            throw $this->createNotFoundException('Offre introuvable.');
        }

        $today = new \DateTimeImmutable('today');
        $expiration = isset($offre['date_fin']) ? new \DateTimeImmutable((string) $offre['date_fin']) : $today->modify('+7 days');
        if ($expiration < $today) {
            $expiration = $today->modify('+7 days');
        }

        $activeCount = (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM code_promo
             WHERE offre_id = ? AND user_id = ? AND statut = 'ACTIF'",
            [$id, (int) $user->getId()]
        );

        if ($activeCount > 0) {
            $blockedPromoId = $this->createPromoRecord($connection, $id, (int) $user->getId(), 'BLOQUE_ABUS', $today, $today);
            $blockedPromoNumber = $this->formatPromoNumber($blockedPromoId);
            $connection->update(
                'code_promo',
                ['qr_image_url' => $this->buildQrImageUrl($blockedPromoNumber)],
                ['id' => $blockedPromoId]
            );
            $this->addFlash('error', 'Vous avez déjà un code promo actif pour cette offre. Cette tentative a été bloquée.');
            return $this->redirectToRoute('app_offres_show', ['id' => $id]);
        }

        try {
            $promoId = $this->createPromoRecord($connection, $id, (int) $user->getId(), 'ACTIF', $today, $expiration);
            $promoNumber = $this->formatPromoNumber($promoId);
            $qrImageUrl = $this->buildQrImageUrl($promoNumber);
            $connection->update('code_promo', ['qr_image_url' => $qrImageUrl], ['id' => $promoId]);

            $this->addFlash('success', 'Code promo généré avec succès: '.$promoNumber);
        } catch (\Throwable $e) {
            $this->addFlash('error', 'Erreur génération code promo: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_offres_show', ['id' => $id]);
    }

    #[Route('/offres/{offreId}/promo/{promoId}/use', name: 'app_offres_use_code', methods: ['POST'])]
    public function useCodePromo(int $offreId, int $promoId, Request $request, Connection $connection): Response
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            $this->addFlash('error', 'Veuillez vous connecter pour utiliser un code promo.');
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('use_promo_'.$promoId, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_offres_show', ['id' => $offreId]);
        }

        $promo = $connection->fetchAssociative(
            'SELECT id, date_expiration, statut
             FROM code_promo
             WHERE id = ? AND offre_id = ? AND user_id = ?',
            [$promoId, $offreId, (int) $user->getId()]
        );

        if (!$promo) {
            $this->addFlash('error', 'Code promo introuvable.');
            return $this->redirectToRoute('app_offres_show', ['id' => $offreId]);
        }

        if ((string) $promo['statut'] !== 'ACTIF') {
            if ((string) $promo['statut'] === 'BLOQUE_ABUS') {
                $this->addFlash('error', 'Ce code promo a été bloqué pour abus.');
            } else {
                $this->addFlash('error', 'Ce code promo n\'est pas actif.');
            }
            return $this->redirectToRoute('app_offres_show', ['id' => $offreId]);
        }

        $expiration = new \DateTimeImmutable((string) $promo['date_expiration']);
        if ($expiration < new \DateTimeImmutable('today')) {
            $connection->update('code_promo', ['statut' => 'EXPIRE'], ['id' => $promoId]);
            $this->addFlash('error', 'Ce code promo est expiré.');
            return $this->redirectToRoute('app_offres_show', ['id' => $offreId]);
        }

        $connection->update('code_promo', ['statut' => 'UTILISE'], ['id' => $promoId]);
        $this->addFlash('success', 'Code promo utilisé avec succès.');

        return $this->redirectToRoute('app_offres_show', ['id' => $offreId]);
    }

    #[Route('/evenements', name: 'app_evenements')]
    public function evenements(Connection $connection): Response
    {
        return $this->render('front/evenement/index.html.twig', [
            'active' => 'evenements',
            'events' => $this->fetchAll($connection, "
                SELECT e.id, e.titre, e.description, e.date_debut, e.date_fin, e.prix, e.type, e.statut, e.image_url,
                       l.nom AS lieu_nom, l.ville
                FROM evenement e
                LEFT JOIN lieu l ON l.id = e.lieu_id
                ORDER BY e.date_debut ASC
            "),
            'notificationData' => $this->getNotificationData($connection),
        ]);
    }

    private function getNotificationData(Connection $connection): array
    {
        $user = $this->getUser();
        if (!$user instanceof User) {
            return ['items' => [], 'unreadCount' => 0];
        }

        $this->syncInAppNotifications($connection, $user);

        return [
            'items' => $this->fetchAll(
                $connection,
                'SELECT id, type, title, body, entity_type, entity_id, created_at, read_at
                 FROM notifications
                 WHERE receiver_id = ?
                 ORDER BY created_at DESC
                 LIMIT 8',
                [(int) $user->getId()]
            ),
            'unreadCount' => (int) $connection->fetchOne(
                'SELECT COUNT(*) FROM notifications WHERE receiver_id = ? AND read_at IS NULL',
                [(int) $user->getId()]
            ),
        ];
    }

    private function syncInAppNotifications(Connection $connection, User $user): void
    {
        $userId = (int) $user->getId();

        $expiringPromos = $this->fetchAll(
            $connection,
            "SELECT cp.id, cp.date_expiration, o.titre AS offre_titre
             FROM code_promo cp
             LEFT JOIN offre o ON o.id = cp.offre_id
             WHERE cp.user_id = ?
               AND cp.statut = 'ACTIF'
               AND TIMESTAMPDIFF(HOUR, NOW(), CONCAT(cp.date_expiration, ' 23:59:59')) BETWEEN 0 AND 24",
            [$userId]
        );

        foreach ($expiringPromos as $promo) {
            $this->createInAppNotification(
                $connection,
                $userId,
                null,
                'PROMO_EXPIRE_BIENTOT',
                'Code promo bientôt expiré',
                'Votre code promo CP'.str_pad((string) $promo['id'], 6, '0', STR_PAD_LEFT).' pour l\'offre "'.(string) ($promo['offre_titre'] ?? 'Offre').'" expire bientôt.',
                'code_promo',
                (int) $promo['id'],
                ['date_expiration' => (string) ($promo['date_expiration'] ?? '')],
                12
            );
        }

        $expiringOffers = $this->fetchAll(
            $connection,
            "SELECT DISTINCT o.id, o.titre, o.date_fin
             FROM reservation_offre r
             INNER JOIN offre o ON o.id = r.offre_id
             WHERE r.user_id = ?
               AND r.statut IN ('EN_ATTENTE', 'CONFIRMÉE')
               AND TIMESTAMPDIFF(HOUR, NOW(), CONCAT(o.date_fin, ' 23:59:59')) BETWEEN 0 AND 24",
            [$userId]
        );

        foreach ($expiringOffers as $offer) {
            $this->createInAppNotification(
                $connection,
                $userId,
                null,
                'OFFRE_TERMINE_BIENTOT',
                'Offre bientôt terminée',
                'L\'offre "'.(string) ($offer['titre'] ?? 'Offre').'" se termine bientôt. Pensez à finaliser votre réservation.',
                'offre',
                (int) $offer['id'],
                ['date_fin' => (string) ($offer['date_fin'] ?? '')],
                12
            );
        }
    }

    private function createInAppNotification(
        Connection $connection,
        int $receiverId,
        ?int $senderId,
        string $type,
        string $title,
        string $body,
        string $entityType,
        int $entityId,
        array $metadata = [],
        int $dedupHours = 24
    ): void {
        $alreadyExists = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM notifications
             WHERE receiver_id = ?
               AND type = ?
               AND entity_type = ?
               AND entity_id = ?
               AND created_at >= DATE_SUB(NOW(), INTERVAL '.$dedupHours.' HOUR)',
            [$receiverId, $type, $entityType, $entityId]
        );

        if ($alreadyExists > 0) {
            return;
        }

        $connection->insert('notifications', [
            'receiver_id' => $receiverId,
            'sender_id' => $senderId,
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'read_at' => null,
            'metadata_json' => $metadata !== [] ? json_encode($metadata, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) : null,
        ]);
    }

    private function getFrontStats(Connection $connection): array
    {
        return [
            'lieux' => $this->fetchValue($connection, 'SELECT COUNT(*) FROM lieu'),
            'sorties' => $this->fetchValue($connection, 'SELECT COUNT(*) FROM annonce_sortie'),
            'offres' => $this->fetchValue($connection, 'SELECT COUNT(*) FROM offre'),
            'events' => $this->fetchValue($connection, 'SELECT COUNT(*) FROM evenement'),
        ];
    }

    private function enrichOffersCountdown(array $offers): array
    {
        return array_map(fn (array $offer): array => $this->enrichOfferCountdown($offer), $offers);
    }

    private function enrichOfferCountdown(array $offer): array
    {
        $offer['countdown_label'] = null;
        $offer['countdown_target'] = null;

        if (!empty($offer['expiring_soon']) && !empty($offer['date_fin'])) {
            $target = new \DateTimeImmutable((string) $offer['date_fin'] . ' 23:59:59');
            $offer['countdown_target'] = $target->format(DATE_ATOM);
            $offer['countdown_label'] = $this->formatCountdownLabel($target);
        }

        return $offer;
    }

    private function formatCountdownLabel(\DateTimeImmutable $target): string
    {
        $now = new \DateTimeImmutable('now');

        if ($target <= $now) {
            return 'Expirée';
        }

        $diff = $now->diff($target);

        if ($diff->days > 0) {
            return sprintf('%dj %dh %dm', $diff->days, $diff->h, $diff->i);
        }

        if ($diff->h > 0) {
            return sprintf('%dh %dm', $diff->h, $diff->i);
        }

        return sprintf('%dm', $diff->i);
    }

    private function fetchAll(Connection $connection, string $sql, array $params = []): array
    {
        try {
            return $connection->fetchAllAssociative($sql, $params);
        } catch (Exception) {
            return [];
        }
    }

    private function fetchOne(Connection $connection, string $sql): ?array
    {
        try {
            return $connection->fetchAssociative($sql) ?: null;
        } catch (Exception) {
            return null;
        }
    }

    private function fetchValue(Connection $connection, string $sql): int
    {
        try {
            return (int) $connection->fetchOne($sql);
        } catch (Exception) {
            return 0;
        }
    }

    private function createPromoRecord(Connection $connection, int $offreId, int $userId, string $statut, \DateTimeImmutable $today, \DateTimeImmutable $expiration): int
    {
        $connection->insert('code_promo', [
            'offre_id' => $offreId,
            'user_id' => $userId,
            'qr_image_url' => 'pending',
            'date_generation' => $today->format('Y-m-d'),
            'date_expiration' => $expiration->format('Y-m-d'),
            'statut' => $statut,
        ]);

        return (int) $connection->lastInsertId();
    }

    private function buildQrImageUrl(string $promoNumber): string
    {
        return 'https://api.qrserver.com/v1/create-qr-code/?size=280x280&data='.urlencode($promoNumber);
    }

    private function formatPromoNumber(int|string $promoId): string
    {
        return 'CP'.str_pad((string) $promoId, 6, '0', STR_PAD_LEFT);
    }
}