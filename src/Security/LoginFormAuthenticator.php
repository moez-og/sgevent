<?php

namespace App\Security;

use App\Entity\User;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\PasswordCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function authenticate(Request $request): Passport
    {
        $email = $request->request->getString('email');
        $captchaToken = $request->request->getString('g-recaptcha-response');

        if ($captchaToken === '') {
            throw new CustomUserMessageAuthenticationException('Veuillez cocher la case "Je ne suis pas un robot".');
        }

        $captchaSecret = (string) ($_ENV['RECAPTCHA3_SECRET'] ?? $_SERVER['RECAPTCHA3_SECRET'] ?? '');
        if ($captchaSecret === '') {
            throw new CustomUserMessageAuthenticationException('Configuration reCAPTCHA serveur manquante.');
        }

        $clientIp = $request->getClientIp() ?? '';

        try {
            $response = HttpClient::create()->request('POST', 'https://www.google.com/recaptcha/api/siteverify', [
                'body' => [
                    'secret' => $captchaSecret,
                    'response' => $captchaToken,
                    'remoteip' => $clientIp,
                ],
            ]);

            $result = $response->toArray(false);
            $isValid = (bool) ($result['success'] ?? false);
            if (!$isValid) {
                throw new CustomUserMessageAuthenticationException('reCAPTCHA invalide. Veuillez reessayer.');
            }
        } catch (CustomUserMessageAuthenticationException $exception) {
            throw $exception;
        } catch (\Throwable) {
            throw new CustomUserMessageAuthenticationException('Verification reCAPTCHA indisponible. Veuillez reessayer.');
        }

        return new Passport(
            new UserBadge($email),
            new PasswordCredentials($request->request->getString('password')),
            [
                new CsrfTokenBadge('authenticate', $request->request->getString('_csrf_token')),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        $user = $token->getUser();
        if ($user instanceof User && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_admin_dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_home'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate('app_login');
    }
}
