<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\HttpClientInterface;

#[Route('/admin')]
class AdminController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard')]
    public function dashboard(Connection $connection): Response
    {
        return $this->render('admin/dashboard/index.html.twig', [
            'active' => 'dashboard',
            'stats' => [
                'users' => $this->fetchValue($connection, 'SELECT COUNT(*) FROM user'),
                'admins' => $this->fetchValue($connection, "SELECT COUNT(*) FROM user WHERE role = 'admin'"),
                'abonnes' => $this->fetchValue($connection, "SELECT COUNT(*) FROM user WHERE role = 'abonne'"),
                'visiteurs' => $this->fetchValue($connection, "SELECT COUNT(*) FROM user WHERE role = 'visiteur'"),
            ],
        ]);
    }

    #[Route('/dashboard-offres', name: 'app_admin_dashboard_offres')]
    public function dashboardOffres(Connection $connection): Response
    {
        $offerStats = [
            'total' => $this->fetchValue($connection, 'SELECT COUNT(*) FROM offre'),
            'active' => $this->fetchValue($connection, "SELECT COUNT(*) FROM offre WHERE LOWER(statut) IN ('actif', 'active')"),
            'expiringSoon' => $this->fetchValue($connection, "SELECT COUNT(*) FROM offre WHERE TIMESTAMPDIFF(HOUR, NOW(), CONCAT(date_fin, ' 23:59:59')) BETWEEN 0 AND 24"),
            'expired' => $this->fetchValue($connection, "SELECT COUNT(*) FROM offre WHERE date_fin < CURDATE() OR LOWER(statut) IN ('expiree', 'expire', 'expired')"),
        ];

        $promoStats = [
            'total' => $this->fetchValue($connection, 'SELECT COUNT(*) FROM code_promo'),
            'active' => $this->fetchValue($connection, "SELECT COUNT(*) FROM code_promo WHERE LOWER(statut) = 'actif'"),
            'used' => $this->fetchValue($connection, "SELECT COUNT(*) FROM code_promo WHERE LOWER(statut) = 'utilise'"),
            'blocked' => $this->fetchValue($connection, "SELECT COUNT(*) FROM code_promo WHERE LOWER(statut) = 'bloque_abus'"),
        ];

        $reservationStats = [
            'total' => $this->fetchValue($connection, 'SELECT COUNT(*) FROM reservation_offre'),
            'pending' => $this->fetchValue($connection, "SELECT COUNT(*) FROM reservation_offre WHERE LOWER(statut) = 'en_attente'"),
            'confirmed' => $this->fetchValue($connection, "SELECT COUNT(*) FROM reservation_offre WHERE LOWER(statut) = 'confirmée' OR LOWER(statut) = 'confirmee'"),
            'refused' => $this->fetchValue($connection, "SELECT COUNT(*) FROM reservation_offre WHERE LOWER(statut) = 'refusée' OR LOWER(statut) = 'refusee'"),
        ];

        return $this->render('admin/dashboard/offres.html.twig', [
            'active' => 'dashboard_offres',
            'offerStats' => $offerStats,
            'promoStats' => $promoStats,
            'reservationStats' => $reservationStats,
            'recentOffers' => $this->fetchAll($connection, "
                SELECT o.id, o.titre, o.type, o.pourcentage, o.date_fin, o.statut, l.nom AS lieu_nom,
                       CASE
                           WHEN TIMESTAMPDIFF(HOUR, NOW(), CONCAT(o.date_fin, ' 23:59:59')) BETWEEN 0 AND 24 THEN 1
                           ELSE 0
                       END AS expiring_soon
                FROM offre o
                LEFT JOIN lieu l ON l.id = o.lieu_id
                ORDER BY o.date_fin ASC, o.id DESC
                LIMIT 8
            "),
            'recentPromos' => $this->fetchAll($connection, "
                SELECT cp.id, cp.statut, cp.date_generation, cp.date_expiration, o.titre AS offre_titre
                FROM code_promo cp
                LEFT JOIN offre o ON o.id = cp.offre_id
                ORDER BY cp.id DESC
                LIMIT 8
            "),
            'recentReservations' => $this->fetchAll($connection, "
                SELECT r.id, r.statut, r.date_reservation, r.nombre_personnes, o.titre AS offre_titre
                FROM reservation_offre r
                LEFT JOIN offre o ON o.id = r.offre_id
                ORDER BY r.id DESC
                LIMIT 8
            "),
        ]);
    }

    #[Route('/users', name: 'app_admin_users')]
    public function users(Request $request, Connection $connection): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $perPage = 3;
        $page = max(1, (int) $request->query->get('page', 1));

        if ($query !== '') {
            $likeQuery = '%'.$query.'%';
            $filters = [$likeQuery, $likeQuery, $likeQuery, $likeQuery, $likeQuery];
            $countRows = $this->fetchAll(
                $connection,
                'SELECT COUNT(*) AS total FROM user WHERE LOWER(prenom) LIKE LOWER(?) OR LOWER(nom) LIKE LOWER(?) OR LOWER(email) LIKE LOWER(?) OR LOWER(telephone) LIKE LOWER(?) OR LOWER(role) LIKE LOWER(?)',
                $filters
            );
            $totalFiltered = (int) ($countRows[0]['total'] ?? 0);
            $pageCount = max(1, (int) ceil($totalFiltered / $perPage));
            $page = min($page, $pageCount);
            $offset = ($page - 1) * $perPage;

            $users = $this->fetchAll(
                $connection,
                'SELECT id, prenom, nom, email, telephone, role, imageUrl FROM user WHERE LOWER(prenom) LIKE LOWER(?) OR LOWER(nom) LIKE LOWER(?) OR LOWER(email) LIKE LOWER(?) OR LOWER(telephone) LIKE LOWER(?) OR LOWER(role) LIKE LOWER(?) ORDER BY id ASC LIMIT '.$perPage.' OFFSET '.$offset,
                $filters
            );
        } else {
            $totalFiltered = $this->fetchValue($connection, 'SELECT COUNT(*) FROM user');
            $pageCount = max(1, (int) ceil($totalFiltered / $perPage));
            $page = min($page, $pageCount);
            $offset = ($page - 1) * $perPage;

            $users = $this->fetchAll(
                $connection,
                'SELECT id, prenom, nom, email, telephone, role, imageUrl FROM user ORDER BY id ASC LIMIT '.$perPage.' OFFSET '.$offset
            );
        }

        return $this->render('admin/user/index.html.twig', [
            'active' => 'users',
            'searchQuery' => $query,
            'stats' => [
                'users' => $this->fetchValue($connection, 'SELECT COUNT(*) FROM user'),
                'admins' => $this->fetchValue($connection, "SELECT COUNT(*) FROM user WHERE role = 'admin'"),
                'abonnes' => $this->fetchValue($connection, "SELECT COUNT(*) FROM user WHERE role = 'abonne'"),
                'visiteurs' => $this->fetchValue($connection, "SELECT COUNT(*) FROM user WHERE role = 'visiteur'"),
            ],
            'users' => $users,
            'page' => $page,
            'pageCount' => $pageCount,
            'perPage' => $perPage,
            'totalFiltered' => $totalFiltered,
        ]);
    }

    #[Route('/users/create', name: 'app_admin_users_create', methods: ['POST'])]
    public function usersCreate(
        Request $request,
        Connection $connection,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if (!$this->isCsrfTokenValid('admin_user_create', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la création.');
            return $this->redirectToRoute('app_admin_users');
        }

        $nom = trim((string) $request->request->get('nom', ''));
        $prenom = trim((string) $request->request->get('prenom', ''));
        $email = strtolower(trim((string) $request->request->get('email', '')));
        $telephone = trim((string) $request->request->get('telephone', ''));
        $role = strtolower(trim((string) $request->request->get('role', 'abonne')));
        $password = (string) $request->request->get('password', '');

        if ($nom === '' || $prenom === '' || $email === '' || $password === '') {
            $this->addFlash('error', 'Nom, prénom, email et mot de passe sont obligatoires.');
            return $this->redirectToRoute('app_admin_users');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Email invalide.');
            return $this->redirectToRoute('app_admin_users');
        }

        if (!$this->isValidHumanName($nom) || !$this->isValidHumanName($prenom)) {
            $this->addFlash('error', 'Nom et prénom doivent contenir 2 à 50 lettres (espaces, tirets et apostrophes autorisés).');
            return $this->redirectToRoute('app_admin_users');
        }

        if ($telephone !== '' && !$this->isValidPhoneNumber($telephone)) {
            $this->addFlash('error', 'Téléphone invalide. Format attendu: +216 12 345 678');
            return $this->redirectToRoute('app_admin_users');
        }

        if (strlen($password) < 8) {
            $this->addFlash('error', 'Le mot de passe doit contenir au moins 8 caractères.');
            return $this->redirectToRoute('app_admin_users');
        }

        if (!$this->isStrongPassword($password)) {
            $this->addFlash('error', 'Mot de passe trop faible (majuscule, minuscule, chiffre et caractère spécial requis).');
            return $this->redirectToRoute('app_admin_users');
        }

        if (!in_array($role, ['admin', 'abonne', 'visiteur'], true)) {
            $role = 'abonne';
        }

        try {
            $exists = (int) $connection->fetchOne('SELECT COUNT(*) FROM user WHERE email = ?', [$email]);
            if ($exists > 0) {
                $this->addFlash('error', 'Un utilisateur avec cet email existe déjà.');
                return $this->redirectToRoute('app_admin_users');
            }

            $user = new User();
            $passwordHash = $passwordHasher->hashPassword($user, $password);

            $connection->insert('user', [
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'password_hash' => $passwordHash,
                'role' => $role,
                'telephone' => $telephone !== '' ? $telephone : null,
                'imageUrl' => 'theme/images/logo.png',
            ]);

            $this->addFlash('success', 'Utilisateur ajouté avec succès.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur création utilisateur: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/users/{id}/update', name: 'app_admin_users_update', methods: ['POST'])]
    public function usersUpdate(
        int $id,
        Request $request,
        Connection $connection,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        if (!$this->isCsrfTokenValid('admin_user_edit_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la modification.');
            return $this->redirectToRoute('app_admin_users');
        }

        $nom = trim((string) $request->request->get('nom', ''));
        $prenom = trim((string) $request->request->get('prenom', ''));
        $email = strtolower(trim((string) $request->request->get('email', '')));
        $telephone = trim((string) $request->request->get('telephone', ''));
        $role = strtolower(trim((string) $request->request->get('role', 'abonne')));
        $password = (string) $request->request->get('password', '');

        if ($nom === '' || $prenom === '' || $email === '') {
            $this->addFlash('error', 'Nom, prénom et email sont obligatoires pour la modification.');
            return $this->redirectToRoute('app_admin_users');
        }

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Email invalide.');
            return $this->redirectToRoute('app_admin_users');
        }

        if (!$this->isValidHumanName($nom) || !$this->isValidHumanName($prenom)) {
            $this->addFlash('error', 'Nom et prénom doivent contenir 2 à 50 lettres (espaces, tirets et apostrophes autorisés).');
            return $this->redirectToRoute('app_admin_users');
        }

        if ($telephone !== '' && !$this->isValidPhoneNumber($telephone)) {
            $this->addFlash('error', 'Téléphone invalide. Format attendu: +216 12 345 678');
            return $this->redirectToRoute('app_admin_users');
        }

        if (!in_array($role, ['admin', 'abonne', 'visiteur'], true)) {
            $role = 'abonne';
        }

        try {
            $exists = (int) $connection->fetchOne('SELECT COUNT(*) FROM user WHERE email = ? AND id <> ?', [$email, $id]);
            if ($exists > 0) {
                $this->addFlash('error', 'Cet email est déjà utilisé par un autre compte.');
                return $this->redirectToRoute('app_admin_users');
            }

            $data = [
                'nom' => $nom,
                'prenom' => $prenom,
                'email' => $email,
                'telephone' => $telephone !== '' ? $telephone : null,
                'role' => $role,
            ];

            if ($password !== '') {
                if (strlen($password) < 8) {
                    $this->addFlash('error', 'Le nouveau mot de passe doit contenir au moins 8 caractères.');
                    return $this->redirectToRoute('app_admin_users');
                }

                if (!$this->isStrongPassword($password)) {
                    $this->addFlash('error', 'Nouveau mot de passe trop faible (majuscule, minuscule, chiffre et caractère spécial requis).');
                    return $this->redirectToRoute('app_admin_users');
                }

                $user = new User();
                $data['password_hash'] = $passwordHasher->hashPassword($user, $password);
            }

            $connection->update('user', $data, ['id' => $id]);
            $this->addFlash('success', 'Utilisateur modifié avec succès.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur modification utilisateur: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/users/{id}/delete', name: 'app_admin_users_delete', methods: ['POST'])]
    public function usersDelete(int $id, Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('admin_user_delete_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la suppression.');
            return $this->redirectToRoute('app_admin_users');
        }

        try {
            $connection->delete('user', ['id' => $id]);
            $this->addFlash('success', 'Utilisateur supprimé avec succès.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur suppression utilisateur: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_users');
    }

    #[Route('/lieux', name: 'app_admin_lieux')]
    public function lieux(Connection $connection): Response
    {
        return $this->render('admin/lieu/index.html.twig', [
            'active' => 'lieux',
            'places' => $this->fetchAll($connection, 'SELECT id, nom, ville, categorie, type, budget_min, budget_max FROM lieu ORDER BY id DESC'),
        ]);
    }

    #[Route('/sorties', name: 'app_admin_sorties')]
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
            default => 's.date_sortie DESC, s.id DESC',
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

        return $this->render('admin/sortie/index.html.twig', [
            'active' => 'sorties',
            'sorties' => $this->fetchAll(
                $connection,
                'SELECT s.id, s.user_id, s.titre, s.description, s.ville, s.lieu_texte, s.point_rencontre, s.type_activite, s.date_sortie, s.budget_max, s.nb_places, s.statut, s.image_url, s.questions_json, u.prenom, u.nom, u.imageUrl AS user_image_url
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
        ]);
    }

    #[Route('/offres', name: 'app_admin_offres')]
    public function offres(Request $request, Connection $connection): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $status = trim((string) $request->query->get('status', ''));
        $sort = trim((string) $request->query->get('sort', 'date_fin'));
        $direction = $this->normalizeSortDirection((string) $request->query->get('direction', 'asc'));
        $promoQuery = trim((string) $request->query->get('promo_q', ''));
        $promoStatus = trim((string) $request->query->get('promo_status', ''));
        $promoSort = trim((string) $request->query->get('promo_sort', 'id'));
        $promoDirection = $this->normalizeSortDirection((string) $request->query->get('promo_direction', 'desc'));
        $page = max(1, (int) $request->query->get('page', 1));
        $pageSize = 10;
        $offset = ($page - 1) * $pageSize;

        $whereParts = [];
        $params = [];

        if ($query !== '') {
            $whereParts[] = '(LOWER(o.titre) LIKE LOWER(?) OR LOWER(o.type) LIKE LOWER(?) OR LOWER(COALESCE(o.description, \'\')) LIKE LOWER(?))';
            $like = '%'.$query.'%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($status !== '') {
            $aliases = $this->statusAliases($status);
            $placeholders = implode(', ', array_fill(0, count($aliases), '?'));
            $whereParts[] = 'LOWER(o.statut) IN ('.$placeholders.')';
            foreach ($aliases as $alias) {
                $params[] = $alias;
            }
        }

        $whereSql = $whereParts !== [] ? ' WHERE '.implode(' AND ', $whereParts) : '';
        $offerSortSql = $this->offerSortSql($sort, $direction);

        $total = (int) $connection->fetchOne(
            'SELECT COUNT(*) FROM offre o'.$whereSql,
            $params
        );

        $offres = $this->fetchAll(
            $connection,
            'SELECT o.id, o.titre, o.type, o.pourcentage, o.date_debut, o.date_fin, o.statut, o.description, o.lieu_id, l.nom AS lieu_nom
             FROM offre o
             LEFT JOIN lieu l ON l.id = o.lieu_id'
            .$whereSql.
            ' ORDER BY '.$offerSortSql.', o.id DESC LIMIT '.$pageSize.' OFFSET '.$offset,
            $params
        );

        $promoWhereParts = [];
        $promoParams = [];

        if ($promoQuery !== '') {
            $promoWhereParts[] = '(LOWER(COALESCE(o.titre, \'\')) LIKE LOWER(?) OR LOWER(COALESCE(u.prenom, \'\')) LIKE LOWER(?) OR LOWER(COALESCE(u.nom, \'\')) LIKE LOWER(?) OR LOWER(CAST(cp.id AS CHAR)) LIKE LOWER(?) OR LOWER(cp.statut) LIKE LOWER(?))';
            $like = '%'.$promoQuery.'%';
            $promoParams[] = $like;
            $promoParams[] = $like;
            $promoParams[] = $like;
            $promoParams[] = $like;
            $promoParams[] = $like;
        }

        if ($promoStatus !== '') {
            $promoWhereParts[] = 'LOWER(cp.statut) = LOWER(?)';
            $promoParams[] = $this->normalizePromoStatus($promoStatus);
        }

        $promoWhereSql = $promoWhereParts !== [] ? ' WHERE '.implode(' AND ', $promoWhereParts) : '';
        $promoSortSql = $this->promoSortSql($promoSort, $promoDirection);
        $promoSql = "
                SELECT cp.id, cp.offre_id, cp.user_id, cp.qr_image_url, cp.date_generation, cp.date_expiration, cp.statut,
                       o.titre AS offre_titre, u.prenom, u.nom
                FROM code_promo cp
                LEFT JOIN offre o ON o.id = cp.offre_id
                LEFT JOIN user u ON u.id = cp.user_id
            ".$promoWhereSql.' ORDER BY '.$promoSortSql.' LIMIT 120';

        $stats = [
            'total' => $this->fetchValue($connection, 'SELECT COUNT(*) FROM offre'),
            'actives' => $this->fetchValue($connection, "SELECT COUNT(*) FROM offre WHERE LOWER(statut) IN ('actif', 'active')"),
            'expirees' => $this->fetchValue($connection, "SELECT COUNT(*) FROM offre WHERE date_fin < CURDATE() OR LOWER(statut) IN ('expiree', 'expire', 'expired')"),
        ];

        return $this->render('admin/offre/index.html.twig', [
            'active' => 'offres',
            'offres' => $offres,
            'lieux' => $this->fetchAll($connection, 'SELECT id, nom FROM lieu ORDER BY nom ASC'),
            'promoCodes' => $this->fetchAll($connection, $promoSql, $promoParams),
            'reservations' => $this->fetchAll($connection, "
                SELECT r.id, r.date_reservation, r.nombre_personnes, r.statut, r.note, r.created_at,
                       o.titre AS offre_titre, l.nom AS lieu_nom,
                       u.prenom, u.nom
                FROM reservation_offre r
                LEFT JOIN offre o ON o.id = r.offre_id
                LEFT JOIN lieu l ON l.id = r.lieu_id
                LEFT JOIN user u ON u.id = r.user_id
                ORDER BY r.id DESC
                LIMIT 150
            "),
            'stats' => $stats,
            'filters' => [
                'q' => $query,
                'status' => $status,
                'sort' => $sort,
                'direction' => $direction,
                'promo_q' => $promoQuery,
                'promo_status' => $promoStatus,
                'promo_sort' => $promoSort,
                'promo_direction' => $promoDirection,
            ],
            'pagination' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
                'totalPages' => max(1, (int) ceil($total / $pageSize)),
            ],
            'analyses' => $this->getOffresAnalysesFromSession($request, $offres),
        ]);
    }

    #[Route('/offres/create', name: 'app_admin_offres_create', methods: ['POST'])]
    public function offresCreate(Request $request, Connection $connection, HttpClientInterface $httpClient): Response
    {
        if (!$this->isCsrfTokenValid('admin_offre_create', (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la création d\'offre.');
            return $this->redirectToRoute('app_admin_offres');
        }

        $payload = $this->normalizeOffrePayload([
            'titre' => (string) $request->request->get('titre', ''),
            'type' => (string) $request->request->get('type', ''),
            'pourcentage' => (string) $request->request->get('pourcentage', ''),
            'date_debut' => (string) $request->request->get('date_debut', ''),
            'date_fin' => (string) $request->request->get('date_fin', ''),
            'statut' => (string) $request->request->get('statut', ''),
            'description' => (string) $request->request->get('description', ''),
            'lieu_id' => (string) $request->request->get('lieu_id', ''),
        ]);

        $errors = $this->validateOffrePayload($payload, true);
        if ($errors !== []) {
            $this->addFlash('error', implode(' ', $errors));
            return $this->redirectToRoute('app_admin_offres');
        }

        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? $currentUser->getId() : null;

        try {
            $connection->insert('offre', [
                'user_id' => $userId,
                'titre' => $payload['titre'],
                'type' => $payload['type'],
                'pourcentage' => $payload['pourcentage'],
                'date_debut' => $payload['date_debut'],
                'date_fin' => $payload['date_fin'],
                'statut' => $payload['statut'],
                'description' => $payload['description'] !== '' ? $payload['description'] : null,
                'lieu_id' => $payload['lieu_id'],
            ]);

            $offreId = (int) $connection->lastInsertId();
            $webhookWarning = $this->sendOffreCreateWebhook($httpClient, $offreId, $userId, $payload);
            if ($webhookWarning !== null) {
                $this->addFlash('warning', $webhookWarning);
            }

            $this->addFlash('success', 'Offre créée avec succès.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur création offre: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_offres');
    }

    #[Route('/offres/analyze', name: 'app_admin_offres_analyze', methods: ['POST'])]
    public function offresAnalyze(Request $request, HttpClientInterface $httpClient, Connection $connection): Response
    {
        // Lire les données JSON ou form POST
        $contentType = $request->headers->get('Content-Type', '');
        $data = [];
        
        if (str_contains($contentType, 'application/json')) {
            // Données JSON depuis AJAX
            $data = json_decode($request->getContent(), true) ?? [];
        } else {
            // Données de formulaire POST
            $data = $request->request->all();
        }

        if (!$this->isCsrfTokenValid('admin_offre_create', (string) ($data['_token'] ?? ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour l\'analyse d\'offre.');
            if ($request->isXmlHttpRequest() || str_contains($contentType, 'application/json')) {
                return $this->json(['success' => false, 'error' => 'CSRF invalide'], 403);
            }
            return $this->redirectToRoute('app_admin_offres');
        }

        // offre_id est optionnel (peut être vide si c'est une création)
        $offreId = (string) ($data['offre_id'] ?? '');

        $payload = $this->normalizeOffrePayload([
            'titre' => (string) ($data['titre'] ?? ''),
            'type' => (string) ($data['type'] ?? ''),
            'pourcentage' => (string) ($data['pourcentage'] ?? ''),
            'date_debut' => (string) ($data['date_debut'] ?? ''),
            'date_fin' => (string) ($data['date_fin'] ?? ''),
            'statut' => (string) ($data['statut'] ?? ''),
            'description' => (string) ($data['description'] ?? ''),
            'lieu_id' => (string) ($data['lieu_id'] ?? ''),
        ]);

        $webhookUrl = trim((string) ($_ENV['N8N_OFFRE_ANALYZE_WEBHOOK_URL'] ?? $_SERVER['N8N_OFFRE_ANALYZE_WEBHOOK_URL'] ?? ''));
        if ($webhookUrl === '') {
            $this->addFlash('error', 'Webhook n8n non configuré. Ajoutez N8N_OFFRE_ANALYZE_WEBHOOK_URL dans votre .env.local.');
            if ($request->isXmlHttpRequest() || str_contains($contentType, 'application/json')) {
                return $this->json(['success' => false, 'error' => 'Webhook non configuré'], 500);
            }
            return $this->redirectToRoute('app_admin_offres');
        }

        // Créer un ID de tracking unique
        $trackingId = bin2hex(random_bytes(18)); // 36 caractères

        // Sauvegarder le tracking
        $connection->insert('offre_analysis_tracking', [
            'tracking_id' => $trackingId,
            'offre_id' => !empty($offreId) ? (int) $offreId : null,
            'status' => 'pending',
            'created_at' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            'expires_at' => (new \DateTimeImmutable())->modify('+1 hour')->format('Y-m-d H:i:s'),
        ]);

        $currentUser = $this->getUser();
        $adminUserId = $currentUser instanceof User ? $currentUser->getId() : null;

        try {
            $response = $httpClient->request('POST', $webhookUrl, [
                'json' => [
                    'source' => 'admin_offre_analyze_button',
                    'sent_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                    'admin_user_id' => $adminUserId,
                    'offre_id' => !empty($offreId) ? (int) $offreId : null,
                    'tracking_id' => $trackingId,
                    'offre' => $payload,
                ],
            ]);

            if ($response->getStatusCode() >= 400) {
                $errorMsg = 'Le webhook n8n a répondu avec une erreur HTTP '.$response->getStatusCode().'.';
                if ($request->isXmlHttpRequest() || str_contains($contentType, 'application/json')) {
                    return $this->json(['success' => false, 'error' => $errorMsg], 400);
                }
                $this->addFlash('error', $errorMsg);
                return $this->redirectToRoute('app_admin_offres');
            }

            // Retourner JSON pour le modal popup
            return $this->json([
                'success' => true,
                'tracking_id' => $trackingId,
                'message' => 'Analyse envoyée à n8n...',
            ]);
        } catch (\Throwable $e) {
            $errorMsg = 'Impossible d\'envoyer les données à n8n: '.$e->getMessage();
            if ($request->isXmlHttpRequest() || str_contains($contentType, 'application/json')) {
                return $this->json(['success' => false, 'error' => $errorMsg], 500);
            }
            $this->addFlash('error', $errorMsg);
            return $this->redirectToRoute('app_admin_offres');
        }
    }

    #[Route('/offres/{id}/update', name: 'app_admin_offres_update', methods: ['POST'])]
    public function offresUpdate(int $id, Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('admin_offre_edit_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la modification d\'offre.');
            return $this->redirectToRoute('app_admin_offres');
        }

        $payload = $this->normalizeOffrePayload([
            'titre' => (string) $request->request->get('titre', ''),
            'type' => (string) $request->request->get('type', ''),
            'pourcentage' => (string) $request->request->get('pourcentage', ''),
            'date_debut' => (string) $request->request->get('date_debut', ''),
            'date_fin' => (string) $request->request->get('date_fin', ''),
            'statut' => (string) $request->request->get('statut', ''),
            'description' => (string) $request->request->get('description', ''),
            'lieu_id' => (string) $request->request->get('lieu_id', ''),
        ]);

        $errors = $this->validateOffrePayload($payload, true);
        if ($errors !== []) {
            $this->addFlash('error', implode(' ', $errors));
            return $this->redirectToRoute('app_admin_offres');
        }

        try {
            $connection->update('offre', [
                'titre' => $payload['titre'],
                'type' => $payload['type'],
                'pourcentage' => $payload['pourcentage'],
                'date_debut' => $payload['date_debut'],
                'date_fin' => $payload['date_fin'],
                'statut' => $payload['statut'],
                'description' => $payload['description'] !== '' ? $payload['description'] : null,
                'lieu_id' => $payload['lieu_id'],
            ], ['id' => $id]);

            $this->addFlash('success', 'Offre modifiée avec succès.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur modification offre: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_offres');
    }

    #[Route('/offres/{id}/delete', name: 'app_admin_offres_delete', methods: ['POST'])]
    public function offresDelete(int $id, Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('admin_offre_delete_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la suppression d\'offre.');
            return $this->redirectToRoute('app_admin_offres');
        }

        try {
            $connection->delete('offre', ['id' => $id]);
            $this->addFlash('success', 'Offre supprimée avec succès.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur suppression offre: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_offres');
    }

    #[Route('/reservations/{id}/confirm', name: 'app_admin_reservation_confirm', methods: ['POST'])]
    public function reservationConfirm(int $id, Request $request, Connection $connection): Response
    {
        $adminUser = $this->getUser();
        $adminUserId = $adminUser instanceof User ? (int) $adminUser->getId() : null;

        if (!$this->isCsrfTokenValid('admin_reservation_confirm_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la confirmation.');
            return $this->redirectToRoute('app_admin_offres');
        }

        $reservation = $connection->fetchAssociative(
            "SELECT r.id, r.statut, r.user_id, r.offre_id, o.titre AS offre_titre
             FROM reservation_offre r
             LEFT JOIN offre o ON o.id = r.offre_id
             WHERE r.id = ?",
            [$id]
        );
        if (!$reservation) {
            $this->addFlash('error', 'Réservation introuvable.');
            return $this->redirectToRoute('app_admin_offres');
        }

        if ((string) $reservation['statut'] !== 'EN_ATTENTE') {
            $this->addFlash('error', 'Cette réservation ne peut plus être confirmée.');
            return $this->redirectToRoute('app_admin_offres');
        }

        try {
            $connection->update('reservation_offre', ['statut' => 'CONFIRMÉE'], ['id' => $id]);
            $this->createInAppNotification(
                $connection,
                (int) $reservation['user_id'],
                $adminUserId,
                'RESERVATION_CONFIRMEE',
                'Réservation confirmée',
                'Votre réservation pour l\'offre "'.(string) ($reservation['offre_titre'] ?? 'Offre').'" a été confirmée.',
                'reservation_offre',
                (int) $reservation['id'],
                ['offre_id' => (int) $reservation['offre_id']],
                168
            );
            $this->addFlash('success', 'Réservation confirmée.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur confirmation réservation: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_offres');
    }

    #[Route('/reservations/{id}/refuse', name: 'app_admin_reservation_refuse', methods: ['POST'])]
    public function reservationRefuse(int $id, Request $request, Connection $connection): Response
    {
        $adminUser = $this->getUser();
        $adminUserId = $adminUser instanceof User ? (int) $adminUser->getId() : null;

        if (!$this->isCsrfTokenValid('admin_reservation_refuse_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour le refus.');
            return $this->redirectToRoute('app_admin_offres');
        }

        $reservation = $connection->fetchAssociative(
            "SELECT r.id, r.statut, r.user_id, r.offre_id, o.titre AS offre_titre
             FROM reservation_offre r
             LEFT JOIN offre o ON o.id = r.offre_id
             WHERE r.id = ?",
            [$id]
        );
        if (!$reservation) {
            $this->addFlash('error', 'Réservation introuvable.');
            return $this->redirectToRoute('app_admin_offres');
        }

        if ((string) $reservation['statut'] !== 'EN_ATTENTE') {
            $this->addFlash('error', 'Cette réservation ne peut plus être refusée.');
            return $this->redirectToRoute('app_admin_offres');
        }

        try {
            $connection->update('reservation_offre', ['statut' => 'REFUSÉE'], ['id' => $id]);
            $this->createInAppNotification(
                $connection,
                (int) $reservation['user_id'],
                $adminUserId,
                'RESERVATION_REFUSEE',
                'Réservation refusée',
                'Votre réservation pour l\'offre "'.(string) ($reservation['offre_titre'] ?? 'Offre').'" a été refusée.',
                'reservation_offre',
                (int) $reservation['id'],
                ['offre_id' => (int) $reservation['offre_id']],
                168
            );
            $this->addFlash('success', 'Réservation refusée.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur refus réservation: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_offres');
    }

    #[Route('/promo-codes/{id}/update', name: 'app_admin_promo_codes_update', methods: ['POST'])]
    public function promoCodeUpdate(int $id, Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('admin_promo_edit_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la modification du code promo.');
            return $this->redirectToRoute('app_admin_offres');
        }

        $dateExpiration = trim((string) $request->request->get('date_expiration', ''));
        $statut = $this->normalizePromoStatus(trim((string) $request->request->get('statut', '')));

        if ($dateExpiration === '') {
            $this->addFlash('error', 'La date d’expiration est obligatoire.');
            return $this->redirectToRoute('app_admin_offres');
        }

        $dateExpirationObject = \DateTimeImmutable::createFromFormat('Y-m-d', $dateExpiration);
        if (!$dateExpirationObject) {
            $this->addFlash('error', 'Format de date invalide pour le code promo.');
            return $this->redirectToRoute('app_admin_offres');
        }

        if (!in_array($statut, ['ACTIF', 'EXPIRE', 'DESACTIVE', 'UTILISE', 'BLOQUE_ABUS'], true)) {
            $this->addFlash('error', 'Statut de code promo invalide.');
            return $this->redirectToRoute('app_admin_offres');
        }

        try {
            $connection->update('code_promo', [
                'date_expiration' => $dateExpirationObject->format('Y-m-d'),
                'statut' => $statut,
            ], ['id' => $id]);

            $this->addFlash('success', 'Code promo modifié avec succès.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur modification code promo: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_offres');
    }

    #[Route('/promo-codes/{id}/delete', name: 'app_admin_promo_codes_delete', methods: ['POST'])]
    public function promoCodeDelete(int $id, Request $request, Connection $connection): Response
    {
        if (!$this->isCsrfTokenValid('admin_promo_delete_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide pour la suppression du code promo.');
            return $this->redirectToRoute('app_admin_offres');
        }

        try {
            $connection->delete('code_promo', ['id' => $id]);
            $this->addFlash('success', 'Code promo supprimé avec succès.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Erreur suppression code promo: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_admin_offres');
    }

    #[Route('/api/offres', name: 'app_admin_api_offres_list', methods: ['GET'])]
    public function apiOffresList(Request $request, Connection $connection): JsonResponse
    {
        $query = trim((string) $request->query->get('q', ''));
        $status = trim((string) $request->query->get('status', ''));
        $page = max(1, (int) $request->query->get('page', 1));
        $pageSize = min(100, max(1, (int) $request->query->get('pageSize', 10)));
        $offset = ($page - 1) * $pageSize;

        $whereParts = [];
        $params = [];

        if ($query !== '') {
            $whereParts[] = '(LOWER(o.titre) LIKE LOWER(?) OR LOWER(o.type) LIKE LOWER(?) OR LOWER(COALESCE(o.description, \'\')) LIKE LOWER(?))';
            $like = '%'.$query.'%';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($status !== '') {
            $aliases = $this->statusAliases($status);
            $placeholders = implode(', ', array_fill(0, count($aliases), '?'));
            $whereParts[] = 'LOWER(o.statut) IN ('.$placeholders.')';
            foreach ($aliases as $alias) {
                $params[] = $alias;
            }
        }

        $whereSql = $whereParts !== [] ? ' WHERE '.implode(' AND ', $whereParts) : '';
        $total = (int) $connection->fetchOne('SELECT COUNT(*) FROM offre o'.$whereSql, $params);

        $rows = $this->fetchAll(
            $connection,
            'SELECT o.id, o.titre, o.description, o.type, o.pourcentage, o.date_debut, o.date_fin, o.statut, o.lieu_id, l.nom AS lieu_nom
             FROM offre o
             LEFT JOIN lieu l ON l.id = o.lieu_id'
            .$whereSql.
            ' ORDER BY o.id DESC LIMIT '.$pageSize.' OFFSET '.$offset,
            $params
        );

        return $this->json([
            'data' => array_map(fn (array $row) => $this->toOffreDto($row), $rows),
            'meta' => [
                'page' => $page,
                'pageSize' => $pageSize,
                'total' => $total,
                'totalPages' => max(1, (int) ceil($total / $pageSize)),
            ],
        ]);
    }

    #[Route('/api/offres/{id}', name: 'app_admin_api_offres_detail', methods: ['GET'])]
    public function apiOffresDetail(int $id, Connection $connection): JsonResponse
    {
        $row = $connection->fetchAssociative(
            'SELECT o.id, o.titre, o.description, o.type, o.pourcentage, o.date_debut, o.date_fin, o.statut, o.lieu_id, l.nom AS lieu_nom
             FROM offre o
             LEFT JOIN lieu l ON l.id = o.lieu_id
             WHERE o.id = ?',
            [$id]
        );

        if (!$row) {
            return $this->json(['error' => 'Offre introuvable.'], Response::HTTP_NOT_FOUND);
        }

        return $this->json(['data' => $this->toOffreDto($row)]);
    }

    #[Route('/api/offres', name: 'app_admin_api_offres_create', methods: ['POST'])]
    public function apiOffresCreate(Request $request, Connection $connection): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Payload JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $payload = $this->normalizeOffrePayload($payload);
        $errors = $this->validateOffrePayload($payload, true);
        if ($errors !== []) {
            return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $currentUser = $this->getUser();
        $userId = $currentUser instanceof User ? $currentUser->getId() : null;

        try {
            $connection->insert('offre', [
                'user_id' => $userId,
                'titre' => $payload['titre'],
                'type' => $payload['type'],
                'pourcentage' => $payload['pourcentage'],
                'date_debut' => $payload['date_debut'],
                'date_fin' => $payload['date_fin'],
                'statut' => $payload['statut'],
                'description' => $payload['description'] !== '' ? $payload['description'] : null,
                'lieu_id' => $payload['lieu_id'],
            ]);

            $newId = (int) $connection->lastInsertId();
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['id' => $newId], Response::HTTP_CREATED);
    }

    #[Route('/api/offres/{id}', name: 'app_admin_api_offres_update', methods: ['PUT', 'PATCH'])]
    public function apiOffresUpdate(int $id, Request $request, Connection $connection): JsonResponse
    {
        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Payload JSON invalide.'], Response::HTTP_BAD_REQUEST);
        }

        $payload = $this->normalizeOffrePayload($payload);
        $errors = $this->validateOffrePayload($payload, true);
        if ($errors !== []) {
            return $this->json(['errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        try {
            $affected = $connection->update('offre', [
                'titre' => $payload['titre'],
                'type' => $payload['type'],
                'pourcentage' => $payload['pourcentage'],
                'date_debut' => $payload['date_debut'],
                'date_fin' => $payload['date_fin'],
                'statut' => $payload['statut'],
                'description' => $payload['description'] !== '' ? $payload['description'] : null,
                'lieu_id' => $payload['lieu_id'],
            ], ['id' => $id]);

            if ($affected === 0) {
                return $this->json(['error' => 'Offre introuvable ou inchangée.'], Response::HTTP_NOT_FOUND);
            }
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['status' => 'updated']);
    }

    #[Route('/api/offres/{id}', name: 'app_admin_api_offres_delete', methods: ['DELETE'])]
    public function apiOffresDelete(int $id, Connection $connection): JsonResponse
    {
        try {
            $affected = $connection->delete('offre', ['id' => $id]);
            if ($affected === 0) {
                return $this->json(['error' => 'Offre introuvable.'], Response::HTTP_NOT_FOUND);
            }
        } catch (Exception $e) {
            return $this->json(['error' => $e->getMessage()], Response::HTTP_INTERNAL_SERVER_ERROR);
        }

        return $this->json(['status' => 'deleted']);
    }

    #[Route('/evenements-legacy', name: 'app_admin_evenements_legacy')]
    public function evenements(Connection $connection): Response
    {
        return $this->render('admin/evenement/index.html.twig', [
            'active' => 'evenements',
            'events' => $this->fetchAll($connection, 'SELECT id, titre, date_debut, date_fin, type, prix, statut FROM evenement ORDER BY date_debut ASC'),
        ]);
    }

    private function isValidHumanName(string $value): bool
    {
        return (bool) preg_match('/^[\\p{L}\\s\\-\']{2,50}$/u', $value);
    }

    private function isValidPhoneNumber(string $value): bool
    {
        return (bool) preg_match('/^\\+?[0-9\\s\\-]{8,16}$/', $value);
    }

    private function isStrongPassword(string $value): bool
    {
        return (bool) preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\\d)(?=.*[^\\w\\s]).{8,}$/', $value);
    }

    private function fetchAll(Connection $connection, string $sql, array $params = []): array
    {
        try {
            return $connection->fetchAllAssociative($sql, $params);
        } catch (Exception) {
            return [];
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

    private function sendOffreCreateWebhook(HttpClientInterface $httpClient, int $offreId, ?int $adminUserId, array $payload): ?string
    {
        $webhookUrl = trim((string) ($_ENV['N8N_OFFRE_CREATE_WEBHOOK_URL'] ?? $_SERVER['N8N_OFFRE_CREATE_WEBHOOK_URL'] ?? ''));
        if ($webhookUrl === '') {
            return null;
        }

        try {
            $response = $httpClient->request('POST', $webhookUrl, [
                'timeout' => 10,
                'max_duration' => 12,
                'json' => [
                    'source' => 'admin_offre_create',
                    'sent_at' => (new \DateTimeImmutable())->format(DATE_ATOM),
                    'admin_user_id' => $adminUserId,
                    'offre' => [
                        'id' => $offreId,
                        'titre' => $payload['titre'],
                        'type' => $payload['type'],
                        'pourcentage' => $payload['pourcentage'],
                        'date_debut' => $payload['date_debut'],
                        'date_fin' => $payload['date_fin'],
                        'statut' => $payload['statut'],
                        'description' => $payload['description'],
                        'lieu_id' => $payload['lieu_id'],
                    ],
                ],
            ]);

            if ($response->getStatusCode() >= 400) {
                return 'Offre créée, mais échec envoi webhook n8n (HTTP '.$response->getStatusCode().').';
            }

            return null;
        } catch (\Throwable $e) {
            return 'Offre créée, mais webhook n8n indisponible: '.$e->getMessage();
        }
    }

    private function normalizeOffrePayload(array $payload): array
    {
        $titre = preg_replace('/\s+/', ' ', trim((string) ($payload['titre'] ?? '')));
        $type = preg_replace('/\s+/', ' ', trim((string) ($payload['type'] ?? '')));
        $description = trim((string) ($payload['description'] ?? ''));

        return [
            'titre' => $titre,
            'type' => $type,
            'pourcentage_raw' => trim((string) ($payload['pourcentage'] ?? '')),
            'pourcentage' => isset($payload['pourcentage']) ? (float) $payload['pourcentage'] : -1,
            'date_debut' => trim((string) ($payload['date_debut'] ?? '')),
            'date_fin' => trim((string) ($payload['date_fin'] ?? '')),
            'statut' => $this->normalizeStatus(trim((string) ($payload['statut'] ?? ''))),
            'description' => $description,
            'lieu_id' => isset($payload['lieu_id']) ? (int) $payload['lieu_id'] : 0,
        ];
    }

    /**
     * @return string[]
     */
    private function validateOffrePayload(array $payload, bool $requireLieu): array
    {
        $errors = [];

        if ($payload['titre'] === '') {
            $errors[] = 'Le titre est obligatoire.';
        } elseif (mb_strlen($payload['titre']) < 3 || mb_strlen($payload['titre']) > 120) {
            $errors[] = 'Le titre doit contenir entre 3 et 120 caractères.';
        }

        if ($payload['type'] === '') {
            $errors[] = 'Le type est obligatoire.';
        } elseif (mb_strlen($payload['type']) < 2 || mb_strlen($payload['type']) > 60) {
            $errors[] = 'Le type doit contenir entre 2 et 60 caractères.';
        }

        if ($payload['description'] !== '' && mb_strlen($payload['description']) > 1000) {
            $errors[] = 'La description ne doit pas dépasser 1000 caractères.';
        }

        if ($payload['statut'] === '') {
            $errors[] = 'Le statut est obligatoire.';
        } elseif (!in_array($payload['statut'], ['ACTIVE', 'EXPIREE', 'DESACTIVEE'], true)) {
            $errors[] = 'Le statut est invalide.';
        }

        if ($payload['pourcentage_raw'] === '' || !is_numeric($payload['pourcentage_raw'])) {
            $errors[] = 'Le pourcentage est obligatoire et doit être un nombre.';
        } elseif ($payload['pourcentage'] < 0 || $payload['pourcentage'] > 100) {
            $errors[] = 'Le pourcentage doit être entre 0 et 100.';
        } elseif (preg_match('/^-?\d+(\.\d{1,2})?$/', $payload['pourcentage_raw']) !== 1) {
            $errors[] = 'Le pourcentage accepte au maximum 2 chiffres après la virgule.';
        }

        if ($requireLieu && $payload['lieu_id'] <= 0) {
            $errors[] = 'Le lieu est obligatoire.';
        }

        $dateDebut = $this->parseStrictYmdDate($payload['date_debut']);
        $dateFin = $this->parseStrictYmdDate($payload['date_fin']);

        if (!$dateDebut || !$dateFin) {
            $errors[] = 'Les dates début/fin sont obligatoires et doivent être au format YYYY-MM-DD.';
        } elseif ($dateDebut > $dateFin) {
            $errors[] = 'La date de début doit être inférieure ou égale à la date de fin.';
        }

        return $errors;
    }

    private function parseStrictYmdDate(string $date): ?\DateTimeImmutable
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            return null;
        }

        $parsed = \DateTimeImmutable::createFromFormat('!Y-m-d', $date);
        if (!$parsed) {
            return null;
        }

        return $parsed->format('Y-m-d') === $date ? $parsed : null;
    }

    private function normalizeStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'actif', 'active' => 'ACTIVE',
            'expiree', 'expire', 'expired' => 'EXPIREE',
            'desactivee', 'desactive', 'inactif', 'inactive', 'brouillon', 'draft' => 'DESACTIVEE',
            default => strtoupper(trim($status)),
        };
    }

    private function normalizePromoStatus(string $status): string
    {
        return match (strtolower(trim($status))) {
            'actif', 'active' => 'ACTIF',
            'expire', 'expiré', 'expiree', 'expired' => 'EXPIRE',
            'desactive', 'désactivé', 'desactivee', 'inactive', 'inactif', 'disabled' => 'DESACTIVE',
            'utilise', 'utilisé', 'used' => 'UTILISE',
            'bloque_abus', 'bloqué_abus', 'blocked_abuse' => 'BLOQUE_ABUS',
            default => strtoupper(trim($status)),
        };
    }

    private function normalizeSortDirection(string $direction): string
    {
        return strtolower(trim($direction)) === 'desc' ? 'desc' : 'asc';
    }

    private function offerSortSql(string $sort, string $direction): string
    {
        $column = match ($sort) {
            'id' => 'o.id',
            'titre' => 'o.titre',
            'type' => 'o.type',
            'pourcentage' => 'o.pourcentage',
            'date_debut' => 'o.date_debut',
            'date_fin' => 'o.date_fin',
            'statut' => 'o.statut',
            'lieu' => 'l.nom',
            default => 'o.date_fin',
        };

        return $column.' '.strtoupper($direction);
    }

    private function promoSortSql(string $sort, string $direction): string
    {
        $column = match ($sort) {
            'id' => 'cp.id',
            'offre' => 'o.titre',
            'user' => 'u.prenom',
            'date_generation' => 'cp.date_generation',
            'date_expiration' => 'cp.date_expiration',
            'statut' => 'cp.statut',
            default => 'cp.id',
        };

        return $column.' '.strtoupper($direction);
    }

    /**
     * @return string[]
     */
    private function statusAliases(string $status): array
    {
        return match ($this->normalizeStatus($status)) {
            'ACTIVE' => ['active', 'actif'],
            'EXPIREE' => ['expiree', 'expire', 'expired'],
            'DESACTIVEE' => ['desactivee', 'desactive', 'inactive', 'inactif', 'draft', 'brouillon'],
            default => [strtolower(trim($status))],
        };
    }

    private function toOffreDto(array $row): array
    {
        return [
            'id' => (int) ($row['id'] ?? 0),
            'titre' => (string) ($row['titre'] ?? ''),
            'type' => (string) ($row['type'] ?? ''),
            'pourcentage' => isset($row['pourcentage']) ? (float) $row['pourcentage'] : 0.0,
            'dateDebut' => isset($row['date_debut']) ? (string) $row['date_debut'] : null,
            'dateFin' => isset($row['date_fin']) ? (string) $row['date_fin'] : null,
            'statut' => (string) ($row['statut'] ?? ''),
            'description' => isset($row['description']) ? (string) $row['description'] : null,
            'lieu' => [
                'id' => isset($row['lieu_id']) ? (int) $row['lieu_id'] : null,
                'nom' => $row['lieu_nom'] ?? null,
            ],
            'createdAt' => null,
        ];
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

    private function getOffresAnalysesFromSession(Request $request, array $offres): array
    {
        $analyses = [];
        $session = $request->getSession();

        foreach ($offres as $offre) {
            $offreId = (string) $offre['id'];
            $sessionKey = 'offre_analysis_' . $offreId;
            
            if ($session->has($sessionKey)) {
                $analyses[$offreId] = $session->get($sessionKey);
            }
        }

        return $analyses;
    }

    #[Route('/offres/dismiss-analysis', name: 'app_admin_offres_dismiss_analysis', methods: ['POST'])]
    public function offresAnalyzeDismiss(Request $request): Response
    {
        try {
            $data = json_decode($request->getContent(), true);
            $offreId = $data['offre_id'] ?? null;

            if (!$offreId) {
                return new JsonResponse(['error' => 'offre_id manquant'], 400);
            }

            $session = $request->getSession();
            $session->remove('offre_analysis_' . $offreId);

            return new JsonResponse(['status' => 'success']);
        } catch (\Throwable $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }
}