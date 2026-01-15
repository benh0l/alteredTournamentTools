<?php

declare(strict_types=1);

namespace App\Service\Admin;

use App\Entity\AdminMessage;
use App\Entity\User;
use App\Message\SendAdminEmailMessage;
use App\Repository\AdminMessageRepository;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;

/**
 * Service for admin messaging to organizers (FR71).
 */
class AdminMessagingService
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly AdminMessageRepository $messageRepository,
        private readonly MessageBusInterface $messageBus,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Send message to a single organizer.
     */
    public function sendToOrganizer(
        User $recipient,
        string $subject,
        string $body,
        User $sender
    ): AdminMessage {
        // Dispatch async email
        $this->messageBus->dispatch(new SendAdminEmailMessage(
            $recipient->getEmail(),
            $recipient->getPseudo(),
            $subject,
            $body,
            $sender->getPseudo()
        ));

        // Store message record
        $message = new AdminMessage();
        $message->setSender($sender);
        $message->setRecipientType('individual');
        $message->setRecipientUser($recipient);
        $message->setSubject($subject);
        $message->setBody($body);
        $message->setRecipientCount(1);

        $this->messageRepository->save($message, true);

        $this->logger->info('Admin message sent to organizer', [
            'recipient' => $recipient->getEmail(),
            'subject' => $subject,
        ]);

        return $message;
    }

    /**
     * Send message to all organizers.
     *
     * @return int Number of recipients
     */
    public function sendToAllOrganizers(
        string $subject,
        string $body,
        User $sender
    ): int {
        $organizers = $this->findOrganizers();
        $count = 0;

        foreach ($organizers as $organizer) {
            $this->messageBus->dispatch(new SendAdminEmailMessage(
                $organizer->getEmail(),
                $organizer->getPseudo(),
                $subject,
                $body,
                $sender->getPseudo()
            ));
            $count++;
        }

        // Store message record
        $message = new AdminMessage();
        $message->setSender($sender);
        $message->setRecipientType('all_organizers');
        $message->setSubject($subject);
        $message->setBody($body);
        $message->setRecipientCount($count);

        $this->messageRepository->save($message, true);

        $this->logger->info('Admin message sent to all organizers', [
            'count' => $count,
            'subject' => $subject,
        ]);

        return $count;
    }

    /**
     * Find all organizers (users with ROLE_ORGANIZER).
     *
     * @return User[]
     */
    public function findOrganizers(): array
    {
        return $this->userRepository->createQueryBuilder('u')
            ->where('u.roles LIKE :role')
            ->setParameter('role', '%ROLE_ORGANIZER%')
            ->orderBy('u.pseudo', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Get message history with pagination.
     *
     * @return array{messages: AdminMessage[], total: int, page: int, totalPages: int}
     */
    public function getMessageHistory(int $page = 1, int $limit = 20): array
    {
        $messages = $this->messageRepository->findPaginated($page, $limit);
        $total = $this->messageRepository->countAll();
        $totalPages = (int) ceil($total / $limit);

        return [
            'messages' => $messages,
            'total' => $total,
            'page' => $page,
            'totalPages' => $totalPages,
        ];
    }

    /**
     * Get a specific message by ID.
     */
    public function getMessage(int $id): ?AdminMessage
    {
        return $this->messageRepository->find($id);
    }
}
