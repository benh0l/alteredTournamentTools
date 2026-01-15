<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\Wizard\AdditionalDetailsType;
use App\Form\Wizard\DateLocationType;
use App\Form\Wizard\DecklistTransparencyType;
use App\Form\Wizard\ExpectedPlayersType;
use App\Form\Wizard\GameFormatType;
use App\Form\Wizard\MatchFormatType;
use App\Form\Wizard\TournamentNameType;
use App\Form\Wizard\TournamentStructureType;
use App\Form\Wizard\VisibilityType;
use App\Service\TournamentService;
use App\Service\TournamentWizardService;
use App\Service\WizardSessionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/tournaments/create/wizard')]
#[IsGranted('ROLE_USER')]
final class TournamentWizardController extends AbstractController
{
    private const WIZARD_NAME = 'tournament_creation';

    private const STEP_CONFIG = [
        1 => ['form' => TournamentNameType::class, 'template' => 'step1_name', 'title' => 'Nom du tournoi'],
        2 => ['form' => DateLocationType::class, 'template' => 'step2_date_location', 'title' => 'Date et lieu'],
        3 => ['form' => GameFormatType::class, 'template' => 'step3_format', 'title' => 'Format de jeu'],
        4 => ['form' => TournamentStructureType::class, 'template' => 'step4_structure', 'title' => 'Structure'],
        5 => ['form' => MatchFormatType::class, 'template' => 'step5_match_format', 'title' => 'Format des matchs'],
        6 => ['form' => ExpectedPlayersType::class, 'template' => 'step6_players', 'title' => 'Nombre de joueurs'],
        7 => ['form' => VisibilityType::class, 'template' => 'step7_visibility', 'title' => 'Visibilite'],
        8 => ['form' => DecklistTransparencyType::class, 'template' => 'step8_decklist', 'title' => 'Decklists'],
        9 => ['form' => AdditionalDetailsType::class, 'template' => 'step9_details', 'title' => 'Details'],
        10 => ['form' => null, 'template' => 'step10_summary', 'title' => 'Resume'],
    ];

    public function __construct(
        private readonly WizardSessionService $wizardSession,
        private readonly TournamentWizardService $wizardService,
        private readonly TournamentService $tournamentService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'tournament_wizard_start')]
    public function start(): Response
    {
        // Clear any previous wizard data and start fresh
        $this->wizardSession->clearWizard(self::WIZARD_NAME);

        return $this->redirectToRoute('tournament_wizard_step', ['step' => 1]);
    }

    #[Route('/{step}', name: 'tournament_wizard_step', requirements: ['step' => '\d+'])]
    public function step(Request $request, int $step): Response
    {
        if ($step < 1 || $step > 10) {
            return $this->redirectToRoute('tournament_wizard_step', ['step' => 1]);
        }

        $config = self::STEP_CONFIG[$step];

        // Handle summary step (no form)
        if ($step === 10) {
            return $this->renderSummary();
        }

        /** @var class-string $formClass */
        $formClass = $config['form'];
        $existingData = $this->wizardSession->getStepData(self::WIZARD_NAME, $step) ?? [];

        $form = $this->createForm($formClass, $existingData);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->wizardSession->setStepData(self::WIZARD_NAME, $step, $form->getData());

            return $this->redirectToRoute('tournament_wizard_step', ['step' => $step + 1]);
        }

        // Get all wizard data to pass to template (for conditional display)
        $wizardData = $this->wizardSession->getAllData(self::WIZARD_NAME);

        return $this->render('tournament/wizard/' . $config['template'] . '.html.twig', [
            'form' => $form,
            'step' => $step,
            'totalSteps' => 10,
            'stepTitle' => $config['title'],
            'stepConfig' => self::STEP_CONFIG,
            'wizard_data' => $wizardData,
        ]);
    }

    #[Route('/create', name: 'tournament_wizard_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('wizard_create', $request->request->get('_token'))) {
            $this->addFlash('error', $this->translator->trans('flash.error.invalid_csrf_token'));

            return $this->redirectToRoute('tournament_wizard_step', ['step' => 10]);
        }

        $allData = $this->wizardSession->getAllData(self::WIZARD_NAME);

        if (\count($allData) < 9) {
            $this->addFlash('error', $this->translator->trans('flash.error.wizard_incomplete'));

            return $this->redirectToRoute('tournament_wizard_start');
        }

        /** @var User $user */
        $user = $this->getUser();
        $tournament = $this->wizardService->buildTournamentFromWizardData($allData, $user);
        $this->tournamentService->createTournament($tournament, $user);

        $this->wizardSession->clearWizard(self::WIZARD_NAME);
        $this->addFlash('success', $this->translator->trans('flash.success.tournament_created'));

        return $this->redirectToRoute('tournament_show', ['id' => $tournament->getId()]);
    }

    private function renderSummary(): Response
    {
        $allData = $this->wizardSession->getAllData(self::WIZARD_NAME);

        // Check if all required steps are completed
        $missingSteps = [];
        for ($i = 1; $i <= 9; ++$i) {
            if (!isset($allData[$i])) {
                $missingSteps[] = $i;
            }
        }

        if (!empty($missingSteps)) {
            $this->addFlash('warning', $this->translator->trans('flash.warning.wizard_steps_incomplete'));

            return $this->redirectToRoute('tournament_wizard_step', ['step' => $missingSteps[0]]);
        }

        $summary = $this->wizardService->buildSummaryFromWizardData($allData);

        return $this->render('tournament/wizard/step10_summary.html.twig', [
            'step' => 10,
            'totalSteps' => 10,
            'stepTitle' => 'Resume',
            'summary' => $summary,
            'stepConfig' => self::STEP_CONFIG,
        ]);
    }
}
