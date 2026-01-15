<?php

declare(strict_types=1);

namespace App\Message;

/**
 * Message for sending admin emails asynchronously (FR71).
 */
final class SendAdminEmailMessage
{
    public function __construct(
        private readonly string $recipientEmail,
        private readonly string $recipientName,
        private readonly string $subject,
        private readonly string $body,
        private readonly string $senderName,
    ) {
    }

    public function getRecipientEmail(): string
    {
        return $this->recipientEmail;
    }

    public function getRecipientName(): string
    {
        return $this->recipientName;
    }

    public function getSubject(): string
    {
        return $this->subject;
    }

    public function getBody(): string
    {
        return $this->body;
    }

    public function getSenderName(): string
    {
        return $this->senderName;
    }
}
