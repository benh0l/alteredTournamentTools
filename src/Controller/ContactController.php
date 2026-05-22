<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\ContactReport;
use App\Form\ContactReportType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/contact')]
final class ContactController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'app_contact_submit', methods: ['POST'])]
    #[IsGranted('ROLE_USER')]
    public function submit(Request $request): JsonResponse
    {
        $contactReport = new ContactReport();
        $form = $this->createForm(ContactReportType::class, $contactReport);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $contactReport->setUser($this->getUser());

            $this->entityManager->persist($contactReport);
            $this->entityManager->flush();

            return new JsonResponse([
                'success' => true,
                'message' => $this->translator->trans('contact.success.message'),
            ]);
        }

        // Collect form errors
        $errors = [];
        foreach ($form->getErrors(true) as $error) {
            $errors[] = $error->getMessage();
        }

        return new JsonResponse([
            'success' => false,
            'message' => $this->translator->trans('contact.error.validation'),
            'errors' => $errors,
        ], Response::HTTP_BAD_REQUEST);
    }

    #[Route('/form', name: 'app_contact_form', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function form(): Response
    {
        $contactReport = new ContactReport();
        $form = $this->createForm(ContactReportType::class, $contactReport, [
            'action' => $this->generateUrl('app_contact_submit'),
        ]);

        return $this->render('contact/_form.html.twig', [
            'form' => $form,
        ]);
    }
}
