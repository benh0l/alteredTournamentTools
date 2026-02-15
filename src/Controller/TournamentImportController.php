<?php

declare(strict_types=1);

namespace App\Controller;

use App\DTO\ColumnMapping;
use App\Entity\Tournament;
use App\Security\Voter\TournamentVoter;
use App\Service\CsvParserService;
use App\Service\RegistrationImportService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Controller for bulk registration import from CSV files.
 *
 * Workflow:
 * 1. Upload CSV file
 * 2. Map columns to fields
 * 3. Execute import and view results
 */
#[Route('/tournaments/{id}/registrations/import', requirements: ['id' => '\d+'])]
final class TournamentImportController extends AbstractController
{
    public function __construct(
        private readonly CsvParserService $csvParser,
        private readonly RegistrationImportService $importService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    /**
     * Step 1: Show upload form.
     */
    #[Route('', name: 'tournament_import', methods: ['GET'])]
    public function upload(Tournament $tournament): Response
    {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        return $this->render('tournament/import/upload.html.twig', [
            'tournament' => $tournament,
            'maxFileSize' => $this->csvParser->getMaxFileSize(),
            'maxRows' => $this->csvParser->getMaxRows(),
        ]);
    }

    /**
     * Step 2a: Process uploaded file and redirect to mapping page.
     */
    #[Route('/process', name: 'tournament_import_process', methods: ['POST'])]
    public function processUpload(
        Request $request,
        Tournament $tournament,
        SessionInterface $session
    ): Response {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        /** @var UploadedFile|null $file */
        $file = $request->files->get('csv_file');

        if (!$file || !$file->isValid()) {
            $this->addFlash('error', $this->translator->trans('import.error.invalid_file'));

            return $this->redirectToRoute('tournament_import', ['id' => $tournament->getId()]);
        }

        // Validate file extension
        $extension = strtolower($file->getClientOriginalExtension());
        if ($extension !== 'csv') {
            $this->addFlash('error', $this->translator->trans('import.error.csv_only'));

            return $this->redirectToRoute('tournament_import', ['id' => $tournament->getId()]);
        }

        try {
            $rows = $this->csvParser->parse($file);
            $suggestions = $this->csvParser->detectColumns($rows[0]);
            $preview = $this->csvParser->getPreview($rows);

            // Store in session for mapping step
            $session->set('import_rows_' . $tournament->getId(), $rows);
            $session->set('import_suggestions_' . $tournament->getId(), $suggestions);
            $session->set('import_preview_' . $tournament->getId(), $preview);

            return $this->redirectToRoute('tournament_import_mapping', ['id' => $tournament->getId()]);
        } catch (\InvalidArgumentException $e) {
            $this->addFlash('error', $e->getMessage());

            return $this->redirectToRoute('tournament_import', ['id' => $tournament->getId()]);
        }
    }

    /**
     * Step 2b: Show column mapping page.
     */
    #[Route('/mapping', name: 'tournament_import_mapping', methods: ['GET'])]
    public function showMapping(
        Tournament $tournament,
        SessionInterface $session
    ): Response {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        $rows = $session->get('import_rows_' . $tournament->getId());
        $suggestions = $session->get('import_suggestions_' . $tournament->getId());
        $preview = $session->get('import_preview_' . $tournament->getId());

        if (!$rows || !$preview) {
            $this->addFlash('error', $this->translator->trans('import.error.session_expired'));

            return $this->redirectToRoute('tournament_import', ['id' => $tournament->getId()]);
        }

        return $this->render('tournament/import/mapping.html.twig', [
            'tournament' => $tournament,
            'preview' => $preview,
            'suggestions' => $suggestions,
            'totalRows' => count($rows) - 1, // Exclude header
            'fieldOptions' => $this->getFieldOptions(),
        ]);
    }

    /**
     * Step 3a: Execute import with chosen mapping.
     */
    #[Route('/execute', name: 'tournament_import_execute', methods: ['POST'])]
    public function executeImport(
        Request $request,
        Tournament $tournament,
        SessionInterface $session
    ): Response {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        // Verify CSRF token
        if (!$this->isCsrfTokenValid('import_' . $tournament->getId(), $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Invalid CSRF token');
        }

        $rows = $session->get('import_rows_' . $tournament->getId());

        if (!$rows) {
            $this->addFlash('error', $this->translator->trans('import.error.session_expired'));

            return $this->redirectToRoute('tournament_import', ['id' => $tournament->getId()]);
        }

        // Clean up session
        $session->remove('import_rows_' . $tournament->getId());
        $session->remove('import_suggestions_' . $tournament->getId());
        $session->remove('import_preview_' . $tournament->getId());

        // Build column mapping from form
        $mappingData = $request->request->all('mapping');
        $mapping = ColumnMapping::fromArray($mappingData);

        if (!$mapping->isValid()) {
            $this->addFlash('error', $this->translator->trans('import.error.email_required'));

            return $this->redirectToRoute('tournament_import', ['id' => $tournament->getId()]);
        }

        $sendEmails = $request->request->getBoolean('send_emails', false);

        try {
            $result = $this->importService->import($tournament, $rows, $mapping, $sendEmails);

            // Store result in session for display
            $session->set('import_result_' . $tournament->getId(), [
                'successCount' => $result->getSuccessCount(),
                'linkedCount' => $result->getLinkedCount(),
                'createdCount' => $result->getCreatedCount(),
                'skippedCount' => $result->getSkippedCount(),
                'errorCount' => $result->getErrorCount(),
                'totalProcessed' => $result->getTotalProcessed(),
                'errors' => $result->getErrors(),
                'skipped' => $result->getSkipped(),
                'details' => $result->getDetails(),
            ]);

            return $this->redirectToRoute('tournament_import_results', ['id' => $tournament->getId()]);
        } catch (\Exception $e) {
            $this->addFlash('error', $this->translator->trans('import.error.import_failed', ['%message%' => $e->getMessage()]));

            return $this->redirectToRoute('tournament_import', ['id' => $tournament->getId()]);
        }
    }

    /**
     * Step 3b: Show import results.
     */
    #[Route('/results', name: 'tournament_import_results', methods: ['GET'])]
    public function showResults(
        Tournament $tournament,
        SessionInterface $session
    ): Response {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        $resultData = $session->get('import_result_' . $tournament->getId());

        if (!$resultData) {
            return $this->redirectToRoute('tournament_import', ['id' => $tournament->getId()]);
        }

        // Clean up session
        $session->remove('import_result_' . $tournament->getId());

        // Convert array back to object-like structure for template
        $result = new \stdClass();
        $result->successCount = $resultData['successCount'];
        $result->linkedCount = $resultData['linkedCount'];
        $result->createdCount = $resultData['createdCount'];
        $result->skippedCount = $resultData['skippedCount'];
        $result->errorCount = $resultData['errorCount'];
        $result->totalProcessed = $resultData['totalProcessed'];
        $result->errors = $resultData['errors'];
        $result->skipped = $resultData['skipped'];
        $result->details = $resultData['details'];

        return $this->render('tournament/import/results.html.twig', [
            'tournament' => $tournament,
            'result' => $result,
        ]);
    }

    /**
     * Download sample CSV template.
     */
    #[Route('/template', name: 'tournament_import_template', methods: ['GET'])]
    public function downloadTemplate(Tournament $tournament): Response
    {
        $this->denyAccessUnlessGranted(TournamentVoter::MANAGE_REGISTRATIONS, $tournament);

        $content = "Email;Pseudo;Nom;Faction;Hero;Decklist URL\n";
        $content .= "joueur1@example.com;Player1;Jean Dupont;Axiom;Sierra & Oddball;https://altered.gg/deck/xxx\n";
        $content .= "joueur2@example.com;Player2;Marie Martin;Lyra;Akesha, Champion of Meun;\n";
        $content .= "joueur3@example.com;;Paul Bernard;Muna;;\n";

        $response = new Response($content);
        $response->headers->set('Content-Type', 'text/csv; charset=UTF-8');
        $response->headers->set('Content-Disposition', 'attachment; filename="import_template.csv"');

        return $response;
    }

    /**
     * Get available field options for column mapping.
     *
     * @return array<string, string>
     */
    private function getFieldOptions(): array
    {
        return [
            '' => $this->translator->trans('import.mapping.field_ignore'),
            'email' => $this->translator->trans('import.mapping.field_email'),
            'pseudo' => $this->translator->trans('import.mapping.field_pseudo'),
            'name' => $this->translator->trans('import.mapping.field_name'),
            'faction' => $this->translator->trans('import.mapping.field_faction'),
            'faction2' => $this->translator->trans('import.mapping.field_faction2'),
            'hero' => $this->translator->trans('import.mapping.field_hero'),
            'decklistUrl' => $this->translator->trans('import.mapping.field_decklist_url'),
        ];
    }
}
