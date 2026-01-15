<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\User;
use App\Form\Admin\AdminMessageType;
use App\Service\Admin\AdminMessagingService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Admin messaging controller (FR71).
 */
#[Route('/admin/messaging')]
#[IsGranted('ROLE_ADMIN')]
class AdminMessagingController extends AbstractController
{
    public function __construct(
        private readonly AdminMessagingService $messagingService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Route('', name: 'admin_messaging')]
    public function compose(Request $request): Response
    {
        $organizers = $this->messagingService->findOrganizers();

        $form = $this->createForm(AdminMessageType::class, null, [
            'organizers' => $organizers,
        ]);

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $data = $form->getData();
            /** @var User $sender */
            $sender = $this->getUser();

            $recipientType = $data['recipientType'];
            $subject = $data['subject'];
            $body = $data['body'];

            if ($recipientType === 'all_organizers') {
                $count = $this->messagingService->sendToAllOrganizers($subject, $body, $sender);
                $this->addFlash('success', $this->translator->trans('flash.success.message_sent', ['%count%' => $count]));
            } else {
                $recipient = $data['recipient'];
                if ($recipient === null) {
                    $this->addFlash('error', $this->translator->trans('flash.error.select_recipient'));
                    return $this->render('admin/messaging/compose.html.twig', [
                        'form' => $form,
                        'organizerCount' => \count($organizers),
                    ]);
                }

                $this->messagingService->sendToOrganizer($recipient, $subject, $body, $sender);
                $this->addFlash('success', $this->translator->trans('flash.success.message_sent_to_user', ['%pseudo%' => $recipient->getPseudo()]));
            }

            return $this->redirectToRoute('admin_messaging_history');
        }

        return $this->render('admin/messaging/compose.html.twig', [
            'form' => $form,
            'organizerCount' => \count($organizers),
        ]);
    }

    #[Route('/history', name: 'admin_messaging_history')]
    public function history(Request $request): Response
    {
        $page = $request->query->getInt('page', 1);
        $result = $this->messagingService->getMessageHistory($page);

        return $this->render('admin/messaging/history.html.twig', [
            'messages' => $result['messages'],
            'pagination' => [
                'current' => $result['page'],
                'total' => $result['total'],
                'totalPages' => $result['totalPages'],
            ],
        ]);
    }

    #[Route('/{id}', name: 'admin_messaging_view', methods: ['GET'])]
    public function view(int $id): Response
    {
        $message = $this->messagingService->getMessage($id);

        if (!$message) {
            throw $this->createNotFoundException('Message not found');
        }

        return $this->render('admin/messaging/view.html.twig', [
            'message' => $message,
        ]);
    }
}
