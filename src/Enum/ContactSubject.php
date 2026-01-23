<?php

namespace App\Enum;

enum ContactSubject: string
{
    case BUG = 'bug';
    case CONTACT = 'contact';

    public function getLabel(): string
    {
        return match ($this) {
            self::BUG => 'contact.subject.bug',
            self::CONTACT => 'contact.subject.contact',
        };
    }
}
