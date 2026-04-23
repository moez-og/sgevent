<?php

namespace App\Controller;

use App\Entity\User;
use App\Form\ProfileUpdateFormType;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\FaceRecognitionService;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use League\OAuth2\Client\Provider\GoogleUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security as SecurityHelper;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\String\Slugger\SluggerInterface;

class SecurityController extends AbstractController
{
    private const RESET_OTP_EMAIL = 'reset_otp_email';
    private const RESET_OTP_HASH = 'reset_otp_hash';
    private const RESET_OTP_EXPIRES_AT = 'reset_otp_expires_at';
    private const RESET_OTP_ATTEMPTS = 'reset_otp_attempts';

    #[Route('/', name: 'app_intro')]
    public function intro(): Response
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectAccordingToRole($this->getUser());
        }

        return $this->render('security/intro.html.twig');
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser() instanceof User) {
            return $this->redirectAccordingToRole($this->getUser());
        }

        $recaptchaSiteKey = (string) ($_ENV['RECAPTCHA3_KEY'] ?? $_SERVER['RECAPTCHA3_KEY'] ?? '');

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error' => $authenticationUtils->getLastAuthenticationError(),
            'recaptcha_site_key' => $recaptchaSiteKey,
        ]);
    }

    #[Route('/connect/google', name: 'app_connect_google_start')]
    public function connectGoogle(ClientRegistry $clientRegistry): Response
    {
        return $clientRegistry
            ->getClient('google_main')
            ->redirect(['openid', 'profile', 'email']);
    }

    #[Route('/connect/google/check', name: 'app_connect_google_check')]
    public function connectGoogleCheck(
        ClientRegistry $clientRegistry,
        UserRepository $userRepository,
        EntityManagerInterface $entityManager,
        UserPasswordHasherInterface $passwordHasher,
        SecurityHelper $security
    ): Response {
        try {
            $client = $clientRegistry->getClient('google_main');
            $accessToken = $client->getAccessToken();
            $googleUser = $client->fetchUserFromToken($accessToken);
        } catch (\Throwable) {
            $this->addFlash('error', 'Connexion Google echouee. Veuillez reessayer.');

            return $this->redirectToRoute('app_register');
        }

        if (!$googleUser instanceof GoogleUser) {
            $this->addFlash('error', 'Impossible de recuperer les informations Google.');

            return $this->redirectToRoute('app_register');
        }

        $email = strtolower(trim((string) $googleUser->getEmail()));
        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->addFlash('error', 'Email Google invalide.');

            return $this->redirectToRoute('app_register');
        }

        $user = $userRepository->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            $prenom = $this->sanitizeGoogleName((string) ($googleUser->getFirstName() ?? ''), 'Google');
            $nom = $this->sanitizeGoogleName((string) ($googleUser->getLastName() ?? ''), 'User');

            // If Google does not provide first/last name, split full name when available.
            $fullName = trim((string) ($googleUser->getName() ?? ''));
            if (($prenom === 'Google' || $nom === 'User') && $fullName !== '') {
                $parts = preg_split('/\s+/', $fullName) ?: [];
                if (count($parts) > 0 && $prenom === 'Google') {
                    $prenom = $this->sanitizeGoogleName((string) $parts[0], 'Google');
                }
                if (count($parts) > 1 && $nom === 'User') {
                    $nom = $this->sanitizeGoogleName((string) implode(' ', array_slice($parts, 1)), 'User');
                }
            }

            $imageUrl = (string) ($googleUser->getAvatar() ?? '');
            if ($imageUrl === '' || (!str_starts_with($imageUrl, 'http://') && !str_starts_with($imageUrl, 'https://'))) {
                $imageUrl = 'theme/images/logo.png';
            }

            $user = new User();
            $user->setEmail($email);
            $user->setPrenom($prenom);
            $user->setNom($nom);
            $user->setRole('abonne');
            $user->setImageUrl($imageUrl);

            // Generate a random local password hash for Google-created accounts.
            $randomPassword = bin2hex(random_bytes(24));
            $user->setPasswordHash($passwordHasher->hashPassword($user, $randomPassword));

            $entityManager->persist($user);
            $entityManager->flush();

            $this->addFlash('success', 'Compte Google cree avec succes.');
        }

        if ($user->getRole() === null || $user->getRole() === '') {
            $user->setRole('abonne');
            $entityManager->flush();
        }

        $security->login($user, firewallName: 'main');

        return $this->redirectAccordingToRole($user);
    }

    #[Route('/login/face', name: 'app_login_face', methods: ['POST'])]
    public function loginByFace(
        Request $request,
        UserRepository $userRepository,
        FaceRecognitionService $faceRecognitionService,
        SecurityHelper $security
    ): JsonResponse {
        if ($this->getUser() instanceof User) {
            return new JsonResponse([
                'success' => true,
                'message' => 'Utilisateur deja connecte.',
                'redirect' => $this->redirectAccordingToRole($this->getUser())->getTargetUrl(),
            ]);
        }

        $payload = json_decode((string) $request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse(['success' => false, 'message' => 'Payload JSON invalide.'], 400);
        }

        $csrfToken = (string) ($payload['_csrf_token'] ?? '');
        if (!$this->isCsrfTokenValid('face_login', $csrfToken)) {
            return new JsonResponse(['success' => false, 'message' => 'Jeton CSRF invalide.'], 403);
        }

        $email = strtolower(trim((string) ($payload['email'] ?? '')));
        $cameraImage = (string) ($payload['image'] ?? '');

        if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['success' => false, 'message' => 'Email invalide.'], 400);
        }

        if ($cameraImage === '') {
            return new JsonResponse(['success' => false, 'message' => 'Image camera manquante.'], 400);
        }

        $user = $userRepository->findOneBy(['email' => $email]);
        if (!$user instanceof User) {
            return new JsonResponse(['success' => false, 'message' => 'Utilisateur introuvable.'], 404);
        }

        $referenceImagePath = $this->resolveReferenceImagePath($user);
        if ($referenceImagePath === null) {
            return new JsonResponse(['success' => false, 'message' => 'Image de reference utilisateur introuvable.'], 400);
        }

        $comparison = $faceRecognitionService->compare($cameraImage, $referenceImagePath);
        if (!((bool) ($comparison['success'] ?? false))) {
            return new JsonResponse([
                'success' => false,
                'message' => (string) ($comparison['message'] ?? 'Visage non reconnu.'),
                'details' => $comparison,
            ], 401);
        }

        $security->login($user, firewallName: 'main');

        return new JsonResponse([
            'success' => true,
            'message' => 'Connexion faciale reussie.',
            'redirect' => $this->redirectAccordingToRole($user)->getTargetUrl(),
            'details' => $comparison,
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): never
    {
        throw new \LogicException('This method is intercepted by the logout firewall key.');
    }

    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(
        Request $request,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager,
        SluggerInterface $slugger
    ): Response {
        if ($this->getUser() instanceof User) {
            return $this->redirectAccordingToRole($this->getUser());
        }

        $user = new User();
        $user->setRole('abonne');
        $user->setImageUrl('theme/images/logo.png');
        $form = $this->createForm(RegistrationFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setEmail(strtolower((string) $user->getEmail()));
            $user->setRole('abonne');

            $imageName = 'theme/images/logo.png';
            $imageFile = $form->get('image')->getData();
            if ($imageFile !== null) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid('', true).'.'.$imageFile->guessExtension();

                $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/users';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                try {
                    $imageFile->move($uploadDir, $newFilename);
                    $imageName = 'uploads/users/'.$newFilename;
                } catch (FileException) {
                    $form->get('image')->addError(new \Symfony\Component\Form\FormError('Erreur pendant l\'upload de l\'image.'));
                }
            }

            if ($form->isValid()) {
                $plainPassword = (string) $form->get('plainPassword')->getData();
                $user->setImageUrl($imageName);
                $user->setPasswordHash($passwordHasher->hashPassword($user, $plainPassword));

                $entityManager->persist($user);
                $entityManager->flush();

                $this->addFlash('success', 'Compte cree avec succes. Vous pouvez maintenant vous connecter.');

                return $this->redirectToRoute('app_login');
            }
        }

        return $this->render('security/register.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    public function profile(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('IS_AUTHENTICATED_FULLY');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(ProfileUpdateFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setEmail(strtolower((string) $user->getEmail()));

            $imageFile = $form->get('image')->getData();
            if ($imageFile !== null) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid('', true).'.'.$imageFile->guessExtension();

                $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/users';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                try {
                    $imageFile->move($uploadDir, $newFilename);
                    $user->setImageUrl('uploads/users/'.$newFilename);
                } catch (FileException) {
                    $form->get('image')->addError(new \Symfony\Component\Form\FormError('Erreur pendant l\'upload de l\'image.'));
                }
            }

            if ($form->isValid()) {
                $entityManager->flush();
                $this->addFlash('success', 'Profil mis a jour avec succes.');

                return $this->redirectToRoute('app_profile');
            }
        }

        return $this->render('security/profile.html.twig', [
            'form' => $form,
        ]);
    }

    #[Route('/forgot-password', name: 'app_forgot_password_request', methods: ['GET', 'POST'])]
    public function forgotPasswordRequest(Request $request, UserRepository $userRepository, MailerInterface $mailer): Response
    {
        $lastEmail = '';

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('forgot_password_request', (string) $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'Jeton CSRF invalide.');

                return $this->redirectToRoute('app_forgot_password_request');
            }

            $lastEmail = strtolower(trim((string) $request->request->get('email')));
            if ($lastEmail === '' || !filter_var($lastEmail, FILTER_VALIDATE_EMAIL)) {
                $this->addFlash('error', 'Merci de saisir un email valide.');
            } else {
                $user = $userRepository->findOneBy(['email' => $lastEmail]);

                if ($user instanceof User) {
                    $otp = (string) random_int(100000, 999999);
                    $session = $request->getSession();
                    $session->set(self::RESET_OTP_EMAIL, $lastEmail);
                    $session->set(self::RESET_OTP_HASH, password_hash($otp, PASSWORD_DEFAULT));
                    $session->set(self::RESET_OTP_EXPIRES_AT, time() + 600);
                    $session->set(self::RESET_OTP_ATTEMPTS, 0);

                    try {
                        $mailerFrom = (string) ($_ENV['MAILER_FROM_ADDRESS'] ?? $_SERVER['MAILER_FROM_ADDRESS'] ?? 'no-reply@example.com');
                        $message = (new Email())
                            ->from($mailerFrom)
                            ->to($lastEmail)
                            ->subject('Code OTP - Reinitialisation mot de passe')
                            ->text("Bonjour,\n\nVotre code OTP est: {$otp}\nCe code expire dans 10 minutes.\n\nSi vous n'etes pas a l'origine de cette demande, ignorez cet email.");

                        $mailer->send($message);

                        $this->addFlash('success', 'Un code OTP a ete envoye sur votre email.');

                        return $this->redirectToRoute('app_forgot_password_verify');
                    } catch (TransportExceptionInterface) {
                        $this->clearResetSession($request);
                        $this->addFlash('error', 'Impossible d\'envoyer l\'email OTP pour le moment.');
                    }
                } else {
                    // Generic response to avoid exposing whether an email exists.
                    $this->addFlash('success', 'Si cet email existe, un code OTP sera envoye.');
                }
            }
        }

        return $this->render('security/forgot_password_request.html.twig', [
            'last_email' => $lastEmail,
        ]);
    }

    #[Route('/forgot-password/verify', name: 'app_forgot_password_verify', methods: ['GET', 'POST'])]
    public function forgotPasswordVerify(
        Request $request,
        UserRepository $userRepository,
        UserPasswordHasherInterface $passwordHasher,
        EntityManagerInterface $entityManager
    ): Response {
        $session = $request->getSession();
        $email = (string) $session->get(self::RESET_OTP_EMAIL, '');
        $expiresAt = (int) $session->get(self::RESET_OTP_EXPIRES_AT, 0);

        if ($email === '') {
            $this->addFlash('error', 'Commencez par demander un code OTP.');

            return $this->redirectToRoute('app_forgot_password_request');
        }

        if ($expiresAt !== 0 && time() > $expiresAt) {
            $this->clearResetSession($request);
            $this->addFlash('error', 'Le code OTP a expire. Demandez un nouveau code.');

            return $this->redirectToRoute('app_forgot_password_request');
        }

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('forgot_password_verify', (string) $request->request->get('_csrf_token'))) {
                $this->addFlash('error', 'Jeton CSRF invalide.');

                return $this->redirectToRoute('app_forgot_password_verify');
            }

            $otp = trim((string) $request->request->get('otp'));
            $newPassword = (string) $request->request->get('new_password');
            $confirmPassword = (string) $request->request->get('confirm_password');

            $errors = [];
            if (!preg_match('/^\d{6}$/', $otp)) {
                $errors[] = 'Le code OTP doit contenir 6 chiffres.';
            }

            if ($newPassword === '' || $confirmPassword === '') {
                $errors[] = 'Le nouveau mot de passe et sa confirmation sont obligatoires.';
            }

            if ($newPassword !== $confirmPassword) {
                $errors[] = 'La confirmation du mot de passe ne correspond pas.';
            }

            if (!preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^\w\s]).{8,}$/', $newPassword)) {
                $errors[] = 'Mot de passe trop faible (majuscule, minuscule, chiffre et caractere special requis).';
            }

            $otpHash = (string) $session->get(self::RESET_OTP_HASH, '');
            $attempts = (int) $session->get(self::RESET_OTP_ATTEMPTS, 0);

            if ($otpHash === '' || !password_verify($otp, $otpHash)) {
                $attempts++;
                $session->set(self::RESET_OTP_ATTEMPTS, $attempts);

                if ($attempts >= 5) {
                    $this->clearResetSession($request);
                    $this->addFlash('error', 'Trop de tentatives OTP. Demandez un nouveau code.');

                    return $this->redirectToRoute('app_forgot_password_request');
                }

                $errors[] = 'Code OTP invalide.';
            }

            if ($errors === []) {
                $user = $userRepository->findOneBy(['email' => $email]);
                if ($user instanceof User) {
                    $user->setPasswordHash($passwordHasher->hashPassword($user, $newPassword));
                    $entityManager->flush();
                }

                $this->clearResetSession($request);
                $this->addFlash('success', 'Votre mot de passe a ete reinitialise. Vous pouvez vous connecter.');

                return $this->redirectToRoute('app_login');
            }

            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
        }

        return $this->render('security/forgot_password_verify.html.twig', [
            'email' => $email,
            'expires_in' => max(0, $expiresAt - time()),
        ]);
    }

    #[Route('/admin/profile', name: 'app_admin_profile', methods: ['GET', 'POST'])]
    public function adminProfile(Request $request, EntityManagerInterface $entityManager, SluggerInterface $slugger): Response
    {
        $this->denyAccessUnlessGranted('ROLE_ADMIN');

        $user = $this->getUser();
        if (!$user instanceof User) {
            return $this->redirectToRoute('app_login');
        }

        $form = $this->createForm(ProfileUpdateFormType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $user->setEmail(strtolower((string) $user->getEmail()));

            $imageFile = $form->get('image')->getData();
            if ($imageFile !== null) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid('', true).'.'.$imageFile->guessExtension();

                $uploadDir = $this->getParameter('kernel.project_dir').'/public/uploads/users';
                if (!is_dir($uploadDir)) {
                    mkdir($uploadDir, 0775, true);
                }

                try {
                    $imageFile->move($uploadDir, $newFilename);
                    $user->setImageUrl('uploads/users/'.$newFilename);
                } catch (FileException) {
                    $form->get('image')->addError(new \Symfony\Component\Form\FormError('Erreur pendant l\'upload de l\'image.'));
                }
            }

            if ($form->isValid()) {
                $entityManager->flush();
                $this->addFlash('success', 'Profil admin mis a jour avec succes.');

                return $this->redirectToRoute('app_admin_profile');
            }
        }

        return $this->render('admin/profile.html.twig', [
            'form' => $form,
            'active' => 'profile',
        ]);
    }

    private function redirectAccordingToRole(User $user): RedirectResponse
    {
        if (in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return $this->redirectToRoute('app_admin_dashboard');
        }

        return $this->redirectToRoute('app_home');
    }

    private function resolveReferenceImagePath(User $user): ?string
    {
        $imageUrl = trim((string) $user->getImageUrl());
        if ($imageUrl === '') {
            return null;
        }

        if (str_starts_with($imageUrl, 'http://') || str_starts_with($imageUrl, 'https://')) {
            return null;
        }

        $projectDir = (string) $this->getParameter('kernel.project_dir');
        $publicDir = realpath($projectDir.'/public');
        if ($publicDir === false) {
            return null;
        }

        $relativePath = ltrim(str_replace('\\', '/', $imageUrl), '/');
        $absolutePath = realpath($publicDir.'/'.$relativePath);
        if ($absolutePath === false) {
            return null;
        }

        $normalizedPublicDir = str_replace('\\', '/', $publicDir);
        $normalizedAbsolutePath = str_replace('\\', '/', $absolutePath);
        if (!str_starts_with($normalizedAbsolutePath, $normalizedPublicDir.'/') && $normalizedAbsolutePath !== $normalizedPublicDir) {
            return null;
        }

        return $absolutePath;
    }

    private function sanitizeGoogleName(string $input, string $fallback): string
    {
        $normalized = preg_replace('/[^A-Za-zÀ-ÿ\s\-\']/u', '', $input) ?? '';
        $normalized = trim(preg_replace('/\s+/', ' ', $normalized) ?? '');

        if (mb_strlen($normalized) < 2) {
            return $fallback;
        }

        return mb_substr($normalized, 0, 50);
    }

    private function clearResetSession(Request $request): void
    {
        $session = $request->getSession();
        $session->remove(self::RESET_OTP_EMAIL);
        $session->remove(self::RESET_OTP_HASH);
        $session->remove(self::RESET_OTP_EXPIRES_AT);
        $session->remove(self::RESET_OTP_ATTEMPTS);
    }
}
