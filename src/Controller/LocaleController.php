<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

class LocaleController extends AbstractController
{
    #[Route('/locale/{locale}', name: 'app_set_locale', requirements: ['locale' => 'fr|en|ar'], methods: ['GET'])]
    public function setLocale(string $locale, Request $request): RedirectResponse
    {
        $request->getSession()->set('_locale', $locale);

        $redirect = (string) $request->query->get('redirect', '');
        if ($redirect === '' || str_starts_with($redirect, '/locale/')) {
            $redirect = $this->generateUrl('app_home');
        }

        return $this->redirect($redirect);
    }
}
