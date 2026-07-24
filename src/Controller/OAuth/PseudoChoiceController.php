<?php

declare(strict_types=1);

namespace App\Controller\OAuth;

use App\Entity\User;
use App\Form\PseudoChoiceFormType;
use App\Service\OAuth\OAuthFeatureService;
use App\Service\OAuth\OAuthLoginException;
use App\Service\OAuth\OAuthLoginService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Authentication\UserAuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\FormLoginAuthenticator;

class PseudoChoiceController extends AbstractController
{
    private const OAUTH_CLAIMS_SESSION_KEY = 'oauth_pending_claims';

    public function __construct(
        private readonly OAuthLoginService $loginService,
        private readonly UserAuthenticatorInterface $userAuthenticator,
        private readonly FormLoginAuthenticator $formLoginAuthenticator,
    ) {
    }

    #[Route('/oauth/altered-reunion/choose-pseudo', name: 'oauth_choose_pseudo')]
    public function choosePseudo(Request $request): Response
    {
        $session = $request->getSession();
        $claims = $session->get(self::OAUTH_CLAIMS_SESSION_KEY);

        if ($claims === null) {
            $this->addFlash('error', 'Session expirée. Veuillez réessayer.');

            return $this->redirectToRoute('app_login');
        }

        $originalPseudo = $claims['preferred_username'] ?? '';
        $suggestions = $this->loginService->generatePseudoSuggestions($originalPseudo);

        $form = $this->createForm(PseudoChoiceFormType::class, null, [
            'suggestions' => $suggestions,
            'original_pseudo' => $originalPseudo,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            $chosenPseudo = $data['pseudo_choice'];

            // If custom option selected, use the custom pseudo
            if ($chosenPseudo === '_custom') {
                $chosenPseudo = $data['custom_pseudo'];

                if (empty($chosenPseudo)) {
                    $this->addFlash('error', 'Veuillez saisir un pseudo personnalisé.');

                    return $this->render('oauth/choose_pseudo.html.twig', [
                        'form' => $form->createView(),
                        'original_pseudo' => $originalPseudo,
                    ]);
                }
            }

            try {
                $user = $this->loginService->createUserWithCustomPseudo(
                    OAuthFeatureService::PROVIDER_ALTERED_REUNION,
                    $claims,
                    $chosenPseudo
                );

                // Clear session data
                $session->remove(self::OAUTH_CLAIMS_SESSION_KEY);

                // Login the user
                return $this->loginUser($request, $user);
            } catch (OAuthLoginException $e) {
                $this->addFlash('error', $e->getMessage());

                // Regenerate suggestions if pseudo conflict
                if ($e->isPseudoConflict()) {
                    $suggestions = $this->loginService->generatePseudoSuggestions($chosenPseudo);
                    $form = $this->createForm(PseudoChoiceFormType::class, null, [
                        'suggestions' => $suggestions,
                        'original_pseudo' => $chosenPseudo,
                    ]);
                }
            }
        }

        return $this->render('oauth/choose_pseudo.html.twig', [
            'form' => $form->createView(),
            'original_pseudo' => $originalPseudo,
        ]);
    }

    private function loginUser(Request $request, User $user): Response
    {
        $this->userAuthenticator->authenticateUser($user, $this->formLoginAuthenticator, $request);

        $this->addFlash('success', 'Compte créé et connexion réussie via Altered Re:Union.');

        return $this->redirectToRoute('app_home');
    }
}
