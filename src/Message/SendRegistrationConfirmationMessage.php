<?php

declare(strict_types=1);

namespace App\Message;

final readonly class SendRegistrationConfirmationMessage
{
    public function __construct(
        private int $registrationId
    ) {
    }

    public function getRegistrationId(): int
    {
        return $this->registrationId;
    }
}
