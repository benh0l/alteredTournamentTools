<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * Controller for handling locale/language switching.
 */
final class LocaleController extends AbstractController
{
    #[Route('/locale/{locale}', name: 'app_locale_switch', requirements: ['locale' => 'fr|en'])]
    public function switchLocale(Request $request, string $locale): Response
    {
        // Store locale in session
        $request->getSession()->set('_locale', $locale);

        // Redirect back to the previous page, or home if not available
        $referer = $request->headers->get('referer');

        if ($referer) {
            return $this->redirect($referer);
        }

        return $this->redirectToRoute('app_home');
    }
}
