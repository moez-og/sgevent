<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ParticipationRequestType;
use App\Model\ParticipationRequestData;
use App\Service\NotificationService;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class ParticipationController extends AbstractController
{
    #[Route('/sorties/{id}', name: 'app_sorties_show', requirements: ['id' => '\\d+'], methods: ['GET'])]
    public function show(int $id, Connection $connection): Response
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_participations');
        }

        $sortie = $connection->fetchAssociative(
            'SELECT s.id, s.user_id, s.titre, s.description, s.ville, s.lieu_texte, s.point_rencontre, s.type_activite, s.date_sortie, s.budget_max, s.nb_places, s.statut, s.image_url, s.questions_json, u.prenom, u.nom, u.role
             FROM annonce_sortie s
             LEFT JOIN user u ON u.id = s.user_id
             WHERE s.id = ?',
            [$id]
        );

        if (!$sortie) {
            throw $this->createNotFoundException('Annonce introuvable.');
        }

        $currentUser = $this->getUser();
        $currentUserId = $currentUser instanceof User ? $currentUser->getId() : null;
        $isCreator = $currentUserId !== null && (int) $sortie['user_id'] === (int) $currentUserId;

        $confirmedPlaces = (int) $connection->fetchOne(
            "SELECT COALESCE(SUM(nb_places), 0) FROM participation_annonce WHERE annonce_id = ? AND statut = 'CONFIRMEE'",
            [$id]
        );
        $pendingCount = (int) $connection->fetchOne(
            "SELECT COUNT(*) FROM participation_annonce WHERE annonce_id = ? AND statut = 'EN_ATTENTE'",
            [$id]
        );

        $myParticipation = null;
        if ($currentUserId !== null) {
            $myParticipation = $connection->fetchAssociative(
                'SELECT id, statut, contact_prefer, contact_value, commentaire, nb_places, reponses_json, date_demande
                 FROM participation_annonce
                 WHERE annonce_id = ? AND user_id = ?
                 ORDER BY id DESC LIMIT 1',
                [$id, $currentUserId]
            );
            if ($myParticipation === false) {
                $myParticipation = null;
            }
        }

        $questions = $this->decodeJsonArray($sortie['questions_json'] ?? null);
        $myAnswers = $this->decodeParticipationAnswers($myParticipation['reponses_json'] ?? null);
        $remainingPlaces = max(0, ((int) $sortie['nb_places']) - $confirmedPlaces);
        $maxPlacesChoice = max(1, $remainingPlaces, (int) ($myParticipation['nb_places'] ?? 0));

        $currentUserEmail = $currentUser instanceof User ? trim((string) $currentUser->getEmail()) : '';
        $currentUserPhone = $currentUser instanceof User ? $this->normalizePhoneForForm($currentUser->getTelephone()) : '';
        $defaultContactPrefer = 'EMAIL';
        if ($myParticipation && in_array((string) ($myParticipation['contact_prefer'] ?? ''), ['TELEPHONE', 'EMAIL'], true)) {
            $defaultContactPrefer = (string) $myParticipation['contact_prefer'];
        } elseif ($currentUserPhone !== '') {
            $defaultContactPrefer = 'TELEPHONE';
        }

        $defaultContactValue = '';
        if ($myParticipation && !empty($myParticipation['contact_value'])) {
            $rawContactValue = trim((string) $myParticipation['contact_value']);
            if ($defaultContactPrefer === 'TELEPHONE') {
                $defaultContactValue = $this->normalizePhoneForForm($rawContactValue);
            } else {
                $defaultContactValue = $rawContactValue;
            }
        } else {
            $defaultContactValue = $defaultContactPrefer === 'TELEPHONE' ? $currentUserPhone : $currentUserEmail;
        }

        $pendingRequests = [];
        if ($isCreator) {
            $pendingRequests = $connection->fetchAllAssociative(
                "SELECT p.id, p.user_id, p.nb_places, p.contact_prefer, p.contact_value, p.commentaire, p.reponses_json, p.date_demande,
                        u.prenom, u.nom
                 FROM participation_annonce p
                 INNER JOIN user u ON u.id = p.user_id
                 WHERE p.annonce_id = ? AND p.statut = 'EN_ATTENTE'
                 ORDER BY p.date_demande ASC",
                [$id]
            );

            foreach ($pendingRequests as &$pendingRequest) {
                $pendingRequest['answers'] = $this->decodeParticipationAnswers((string) ($pendingRequest['reponses_json'] ?? ''));
            }
            unset($pendingRequest);
        }

        $confirmedParticipants = [];
        $organizerRole = strtolower((string) ($sortie['role'] ?? ''));
        $organizerIsAdmin = in_array($organizerRole, ['admin', 'role_admin'], true);
        $confirmedParticipants[] = [
            'user_id' => (int) $sortie['user_id'],
            'prenom' => (string) ($sortie['prenom'] ?? ''),
            'nom' => (string) ($sortie['nom'] ?? ''),
            'nb_places' => 0,
            'is_organizer' => true,
            'is_admin' => $organizerIsAdmin,
        ];

        $confirmedRows = $connection->fetchAllAssociative(
            "SELECT p.user_id, p.nb_places, u.prenom, u.nom, u.role
             FROM participation_annonce p
             INNER JOIN user u ON u.id = p.user_id
             WHERE p.annonce_id = ? AND p.statut = 'CONFIRMEE'
             ORDER BY p.date_demande ASC",
            [$id]
        );

        foreach ($confirmedRows as $confirmedRow) {
            if ((int) $confirmedRow['user_id'] === (int) $sortie['user_id']) {
                continue;
            }

            $confirmedParticipants[] = [
                'user_id' => (int) $confirmedRow['user_id'],
                'prenom' => (string) ($confirmedRow['prenom'] ?? ''),
                'nom' => (string) ($confirmedRow['nom'] ?? ''),
                'nb_places' => (int) ($confirmedRow['nb_places'] ?? 1),
                'is_organizer' => false,
                'is_admin' => in_array(strtolower((string) ($confirmedRow['role'] ?? '')), ['admin', 'role_admin'], true),
            ];
        }

        $canJoin = $currentUserId !== null
            && !$isCreator
            && ($sortie['statut'] ?? '') === 'OUVERTE'
            && $remainingPlaces > 0
            && (!$myParticipation || in_array((string) $myParticipation['statut'], ['ANNULEE', 'REFUSEE'], true));

        $canEditPending = $currentUserId !== null
            && !$isCreator
            && $myParticipation
            && (string) $myParticipation['statut'] === 'EN_ATTENTE';

        $participationForm = null;
        if ($canJoin || $canEditPending) {
            $participationForm = $this->createParticipationForm(
                $id,
                $this->buildParticipationData(
                    $myParticipation,
                    $defaultContactPrefer,
                    $defaultContactValue,
                    (int) ($myParticipation['nb_places'] ?? 1),
                    $myParticipation['commentaire'] ?? null,
                    $myAnswers
                ),
                $maxPlacesChoice,
                count($questions)
            )->createView();
        }

        return $this->render('front/sortie/show.html.twig', [
            'active' => 'sorties',
            'sortie' => $sortie,
            'questions' => $questions,
            'confirmedCount' => $confirmedPlaces,
            'pendingCount' => $pendingCount,
            'remainingPlaces' => $remainingPlaces,
            'maxPlacesChoice' => $maxPlacesChoice,
            'isCreator' => $isCreator,
            'myParticipation' => $myParticipation,
            'canJoin' => $canJoin,
            'canEditPending' => $canEditPending,
            'myAnswers' => $myAnswers,
            'pendingRequests' => $pendingRequests,
            'confirmedParticipants' => $confirmedParticipants,
            'defaultContactPrefer' => $defaultContactPrefer,
            'defaultContactValue' => $defaultContactValue,
            'defaultContactEmail' => $currentUserEmail,
            'defaultContactPhone' => $currentUserPhone,
            'participationForm' => $participationForm,
        ]);
    }

    #[Route('/sorties/{id}/participer', name: 'app_sorties_join', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function join(int $id, Request $request, Connection $connection, NotificationService $notificationService): RedirectResponse
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_participations');
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            $this->addFlash('error', 'Vous devez être connecté pour participer.');
            return $this->redirectToRoute('app_login');
        }

        $sortie = $connection->fetchAssociative(
            'SELECT id, user_id, titre, questions_json, statut, nb_places FROM annonce_sortie WHERE id = ?',
            [$id]
        );

        if (!$sortie) {
            $this->addFlash('error', 'Annonce introuvable.');
            return $this->redirectToRoute('app_sorties');
        }

        if ((int) $sortie['user_id'] === (int) $currentUser->getId()) {
            $this->addFlash('error', 'Vous ne pouvez pas rejoindre votre propre sortie.');
            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        $existing = $connection->fetchAssociative(
            'SELECT id, statut, nb_places, contact_prefer, contact_value, commentaire, reponses_json
             FROM participation_annonce
             WHERE annonce_id = ? AND user_id = ?
             ORDER BY id DESC LIMIT 1',
            [$id, $currentUser->getId()]
        );
        if ($existing === false) {
            $existing = null;
        }
        $isEditingPending = $existing && (string) $existing['statut'] === 'EN_ATTENTE';

        if ($existing && (string) $existing['statut'] === 'CONFIRMEE') {
            $this->addFlash('error', 'Votre participation est déjà confirmée.');
            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        $confirmedPlaces = (int) $connection->fetchOne(
            "SELECT COALESCE(SUM(nb_places), 0) FROM participation_annonce WHERE annonce_id = ? AND statut = 'CONFIRMEE'",
            [$id]
        );
        $nbPlaces = (int) ($sortie['nb_places'] ?? 0);
        $remainingPlaces = max(0, $nbPlaces - $confirmedPlaces);
        $previousRequestedPlaces = (int) ($existing['nb_places'] ?? 1);
        $questions = $this->decodeJsonArray($sortie['questions_json'] ?? null);
        $existingAnswers = $this->decodeParticipationAnswers($existing['reponses_json'] ?? null);
        $maxPlacesChoice = max(1, $remainingPlaces, $previousRequestedPlaces);

        $defaultContactPrefer = 'EMAIL';
        $currentUserPhone = $this->normalizePhoneForForm($currentUser->getTelephone());
        if ($existing && in_array((string) ($existing['contact_prefer'] ?? ''), ['TELEPHONE', 'EMAIL'], true)) {
            $defaultContactPrefer = (string) $existing['contact_prefer'];
        } elseif ($currentUserPhone !== '') {
            $defaultContactPrefer = 'TELEPHONE';
        }

        $defaultContactValue = '';
        if ($existing && !empty($existing['contact_value'])) {
            $rawContactValue = trim((string) $existing['contact_value']);
            if ($defaultContactPrefer === 'TELEPHONE') {
                $defaultContactValue = $this->normalizePhoneForForm($rawContactValue);
            } else {
                $defaultContactValue = $rawContactValue;
            }
        } else {
            $defaultContactValue = $defaultContactPrefer === 'TELEPHONE'
                ? $currentUserPhone
                : trim((string) $currentUser->getEmail());
        }

        $submitted = $request->request->all();
        $submittedContactValue = trim((string) ($submitted['contact_value'] ?? ''));
        if ($submittedContactValue === '') {
            $submittedContactPrefer = strtoupper(trim((string) ($submitted['contact_prefer'] ?? $defaultContactPrefer)));
            $fallbackContact = $submittedContactPrefer === 'TELEPHONE'
                ? $this->normalizePhoneForForm($currentUser->getTelephone())
                : trim((string) $currentUser->getEmail());

            if ($fallbackContact !== '') {
                $submitted['contact_value'] = $fallbackContact;
                $request->request->replace($submitted);
            }
        }

        $form = $this->createParticipationForm(
            $id,
            $this->buildParticipationData(
                $existing,
                $defaultContactPrefer,
                $defaultContactValue,
                $previousRequestedPlaces,
                $existing['commentaire'] ?? null,
                $existingAnswers
            ),
            $maxPlacesChoice,
            count($questions)
        );
        $form->handleRequest($request);

        if (!$form->isSubmitted()) {
            $this->addFlash('error', 'Formulaire de participation invalide.');
            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        if (!$form->isValid()) {
            $this->addFormErrorsAsFlash($form);
            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        /** @var ParticipationRequestData $payloadData */
        $payloadData = $form->getData();
        $requestedPlaces = (int) $payloadData->getNbPlaces();

        if (!$isEditingPending && (($sortie['statut'] ?? '') !== 'OUVERTE' || ($nbPlaces > 0 && $remainingPlaces <= 0))) {
            if (($sortie['statut'] ?? '') === 'OUVERTE' && $nbPlaces > 0 && $confirmedPlaces >= $nbPlaces) {
                $connection->update('annonce_sortie', ['statut' => 'CLOTUREE'], ['id' => $id]);
            }
            $this->addFlash('warning', 'Cette sortie est complète ou non ouverte aux participations.');
            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        if ($nbPlaces > 0 && $remainingPlaces > 0 && $requestedPlaces > $remainingPlaces) {
            $this->addFlash('warning', 'Le nombre de places demandées dépasse les places restantes.');
            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        if ($isEditingPending && $remainingPlaces === 0 && $requestedPlaces !== $previousRequestedPlaces) {
            $this->addFlash('warning', 'La sortie est complète. Vous pouvez seulement conserver votre nombre de places actuel.');
            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        $contactPrefer = $payloadData->getContactPrefer();
        $contactValue = trim($payloadData->getContactValue());
        $commentaire = trim((string) $payloadData->getCommentaire());

        if ($contactPrefer === 'TELEPHONE') {
            $contactValue = $this->normalizePhoneForForm($contactValue);
            $contactValue = '+216'.$contactValue;
        }

        $rawAnswers = $payloadData->getReponses();
        $answerItems = [];
        foreach ($questions as $index => $question) {
            $answer = trim((string) ($rawAnswers[(string) $index] ?? ''));

            if ($answer === '') {
                $this->addFlash('error', 'Veuillez repondre a toutes les questions obligatoires.');
                return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
            }

            $answerItems[] = [
                'question' => (string) $question,
                'reponse' => $answer,
            ];
        }

        try {
            if ($existing && in_array((string) $existing['statut'], ['CONFIRMEE'], true)) {
                $this->addFlash('error', 'Vous avez déjà une demande en cours pour cette sortie.');
                return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
            }

            $payload = [
                'annonce_id' => $id,
                'user_id' => $currentUser->getId(),
                'statut' => 'EN_ATTENTE',
                'contact_prefer' => $contactPrefer,
                'contact_value' => $contactValue,
                'commentaire' => $commentaire !== '' ? $commentaire : null,
                'nb_places' => $requestedPlaces,
                'reponses_json' => json_encode($answerItems, JSON_UNESCAPED_UNICODE),
                'date_demande' => (new \DateTimeImmutable())->format('Y-m-d H:i:s'),
            ];

            if ($existing) {
                $connection->update('participation_annonce', $payload, ['id' => $existing['id']]);
                $this->addFlash('success', $isEditingPending ? 'Demande modifiée avec succès.' : 'Demande envoyée avec succès.');
            } else {
                $connection->insert('participation_annonce', $payload);
                $this->addFlash('success', 'Demande envoyée avec succès.');
            }

            $notificationService->notifyUser(
                (int) $sortie['user_id'],
                (int) $currentUser->getId(),
                'PARTICIPATION_REQUESTED',
                'Nouvelle demande de participation',
                $currentUser->getPrenom().' '.$currentUser->getNom().' a envoyé une demande pour votre sortie "'.$sortie['titre'].'".',
                'annonce_sortie',
                $id,
                ['statut' => 'EN_ATTENTE', 'anchor' => 'demandes-attente']
            );
        } catch (UniqueConstraintViolationException) {
            $this->addFlash('error', 'Vous avez déjà une demande en cours pour cette sortie.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Impossible d\'envoyer la demande: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
    }

    private function createParticipationForm(
        int $sortieId,
        ParticipationRequestData $data,
        int $maxPlacesChoice,
        int $questionCount
    ): FormInterface {
        return $this->createForm(ParticipationRequestType::class, $data, [
            'action' => $this->generateUrl('app_sorties_join', ['id' => $sortieId]),
            'method' => 'POST',
            'csrf_token_id' => 'join_sortie_'.$sortieId,
            'max_places' => max(1, $maxPlacesChoice),
            'question_count' => max(0, $questionCount),
        ]);
    }

    /**
     * @param array<string, mixed>|null $participation
     * @param array<int, string> $answers
     */
    private function buildParticipationData(
        ?array $participation,
        string $defaultContactPrefer,
        string $defaultContactValue,
        int $defaultPlaces,
        ?string $defaultCommentaire,
        array $answers
    ): ParticipationRequestData {
        $data = new ParticipationRequestData();
        $contactPrefer = (string) ($participation['contact_prefer'] ?? $defaultContactPrefer);
        $contactValue = (string) ($participation['contact_value'] ?? $defaultContactValue);
        if (strtoupper($contactPrefer) === 'TELEPHONE') {
            $contactValue = $this->normalizePhoneForForm($contactValue);
        }

        $data->setNbPlaces(max(1, (int) ($participation['nb_places'] ?? $defaultPlaces)));
        $data->setContactPrefer($contactPrefer);
        $data->setContactValue($contactValue);
        $data->setCommentaire((string) ($participation['commentaire'] ?? $defaultCommentaire));
        $data->setReponses($answers);

        return $data;
    }

    private function addFormErrorsAsFlash(FormInterface $form): void
    {
        $seen = [];
        foreach ($form->getErrors(true) as $error) {
            $message = trim((string) $error->getMessage());
            if ($message === '' || isset($seen[$message])) {
                continue;
            }

            $seen[$message] = true;
            $this->addFlash('error', $message);
        }
    }

    #[Route('/sorties/{id}/quitter', name: 'app_sorties_leave', requirements: ['id' => '\\d+'], methods: ['POST'])]
    public function leave(int $id, Request $request, Connection $connection, NotificationService $notificationService): RedirectResponse
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_participations');
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            $this->addFlash('error', 'Vous devez être connecté pour modifier votre participation.');
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('leave_sortie_'.$id, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        $participation = $connection->fetchAssociative(
            'SELECT id, statut FROM participation_annonce WHERE annonce_id = ? AND user_id = ? ORDER BY id DESC LIMIT 1',
            [$id, $currentUser->getId()]
        );

        if (!$participation) {
            $this->addFlash('error', 'Aucune participation trouvée pour cette sortie.');
            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        try {
            $connection->update('participation_annonce', ['statut' => 'ANNULEE'], ['id' => $participation['id']]);

            $sortie = $connection->fetchAssociative('SELECT id, titre, user_id FROM annonce_sortie WHERE id = ?', [$id]);
            if ($sortie) {
                $notificationService->notifyUser(
                    (int) $sortie['user_id'],
                    (int) $currentUser->getId(),
                    'PARTICIPATION_CANCELLED',
                    'Participation annulée',
                    $currentUser->getPrenom().' '.$currentUser->getNom().' a annulé sa participation pour "'.$sortie['titre'].'".',
                    'annonce_sortie',
                    (int) $sortie['id'],
                    ['statut' => 'ANNULEE', 'anchor' => 'participation']
                );
            }

            $this->refreshSortieStatusByCapacity($connection, $notificationService, $id, (int) $currentUser->getId());
            $this->addFlash('success', 'Votre participation a été annulée.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Impossible d\'annuler la participation: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
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

    #[Route('/sorties/{id}/demandes/{participationId}/accepter', name: 'app_sorties_demandes_accept', requirements: ['id' => '\\d+', 'participationId' => '\\d+'], methods: ['POST'])]
    public function acceptPendingRequest(int $id, int $participationId, Request $request, Connection $connection, NotificationService $notificationService): RedirectResponse
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_participations');
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            $this->addFlash('error', 'Vous devez être connecté.');
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('accept_pending_'.$id.'_'.$participationId, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        $sortie = $connection->fetchAssociative('SELECT id, user_id, titre, nb_places FROM annonce_sortie WHERE id = ?', [$id]);
        if (!$sortie || (int) $sortie['user_id'] !== (int) $currentUser->getId()) {
            $this->addFlash('error', 'Action non autorisée.');
            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        $participation = $connection->fetchAssociative(
            'SELECT id, annonce_id, user_id, statut, nb_places FROM participation_annonce WHERE id = ? AND annonce_id = ?',
            [$participationId, $id]
        );

        if (!$participation || (string) $participation['statut'] !== 'EN_ATTENTE') {
            $this->addFlash('warning', 'Cette demande ne peut plus être acceptée.');
            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        $confirmedPlaces = (int) $connection->fetchOne(
            "SELECT COALESCE(SUM(nb_places), 0) FROM participation_annonce WHERE annonce_id = ? AND statut = 'CONFIRMEE' AND id <> ?",
            [$id, $participationId]
        );
        $requestedPlaces = (int) ($participation['nb_places'] ?? 1);
        $totalPlaces = (int) ($sortie['nb_places'] ?? 0);

        if ($totalPlaces > 0 && ($confirmedPlaces + $requestedPlaces) > $totalPlaces) {
            $this->addFlash('warning', 'Impossible de confirmer: places insuffisantes.');
            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        try {
            $connection->update('participation_annonce', ['statut' => 'CONFIRMEE'], ['id' => $participationId]);

            $notificationService->notifyUser(
                (int) $participation['user_id'],
                (int) $currentUser->getId(),
                'PARTICIPATION_CONFIRMED',
                'Participation acceptée',
                'Votre participation à la sortie "'.$sortie['titre'].'" a été acceptée.',
                'annonce_sortie',
                $id,
                ['statut' => 'CONFIRMEE', 'anchor' => 'participation']
            );

            $this->refreshSortieStatusByCapacity($connection, $notificationService, $id, (int) $currentUser->getId());
            $this->addFlash('success', 'Demande acceptée.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Impossible d\'accepter la demande: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
    }

    #[Route('/sorties/{id}/demandes/{participationId}/refuser', name: 'app_sorties_demandes_refuse', requirements: ['id' => '\\d+', 'participationId' => '\\d+'], methods: ['POST'])]
    public function refusePendingRequest(int $id, int $participationId, Request $request, Connection $connection, NotificationService $notificationService): RedirectResponse
    {
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_participations');
        }

        $currentUser = $this->getUser();
        if (!$currentUser instanceof User) {
            $this->addFlash('error', 'Vous devez être connecté.');
            return $this->redirectToRoute('app_login');
        }

        if (!$this->isCsrfTokenValid('refuse_pending_'.$id.'_'.$participationId, (string) $request->request->get('_token', ''))) {
            $this->addFlash('error', 'Jeton CSRF invalide.');
            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        $sortie = $connection->fetchAssociative('SELECT id, user_id, titre FROM annonce_sortie WHERE id = ?', [$id]);
        if (!$sortie || (int) $sortie['user_id'] !== (int) $currentUser->getId()) {
            $this->addFlash('error', 'Action non autorisée.');
            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        $participation = $connection->fetchAssociative(
            'SELECT id, annonce_id, user_id, statut FROM participation_annonce WHERE id = ? AND annonce_id = ?',
            [$participationId, $id]
        );

        if (!$participation || (string) $participation['statut'] !== 'EN_ATTENTE') {
            $this->addFlash('warning', 'Cette demande ne peut plus être refusée.');
            return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
        }

        try {
            $connection->update('participation_annonce', ['statut' => 'REFUSEE'], ['id' => $participationId]);

            $notificationService->notifyUser(
                (int) $participation['user_id'],
                (int) $currentUser->getId(),
                'PARTICIPATION_REFUSED',
                'Demande refusée',
                'Votre demande pour la sortie "'.$sortie['titre'].'" a été refusée.',
                'annonce_sortie',
                $id,
                ['statut' => 'REFUSEE', 'anchor' => 'participation']
            );

            $this->refreshSortieStatusByCapacity($connection, $notificationService, $id, (int) $currentUser->getId());
            $this->addFlash('success', 'Demande refusée.');
        } catch (Exception $e) {
            $this->addFlash('error', 'Impossible de refuser la demande: '.$e->getMessage());
        }

        return $this->redirectToRoute('app_sorties_show', ['id' => $id]);
    }

    /**
     * @return array<int, string>
     */
    private function decodeJsonArray(?string $json): array
    {
        if (!$json) {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                return [];
            }

            $result = [];
            foreach ($decoded as $value) {
                $text = trim((string) $value);
                if ($text !== '') {
                    $result[] = $text;
                }
            }

            return $result;
        } catch (\JsonException) {
            return [];
        }
    }

    /**
     * @return array<int, string>
     */
    private function decodeParticipationAnswers(?string $json): array
    {
        if (!$json) {
            return [];
        }

        try {
            $decoded = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            if (!is_array($decoded)) {
                return [];
            }

            $answers = [];
            foreach ($decoded as $index => $item) {
                if (is_array($item)) {
                    $answers[(int) $index] = trim((string) ($item['reponse'] ?? ''));
                    continue;
                }

                $answers[(int) $index] = trim((string) $item);
            }

            return $answers;
        } catch (\JsonException) {
            return [];
        }
    }

    private function normalizePhoneForForm(?string $rawPhone): string
    {
        $rawPhone = trim((string) $rawPhone);
        if ($rawPhone === '') {
            return '';
        }

        $digits = preg_replace('/\D+/', '', $rawPhone) ?? '';
        if ($digits === '') {
            return '';
        }

        if (str_starts_with($digits, '216')) {
            $digits = substr($digits, 3);
        }

        return strlen($digits) === 8 ? $digits : '';
    }
}
