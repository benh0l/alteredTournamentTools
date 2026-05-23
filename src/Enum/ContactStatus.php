<?php

namespace App\Enum;

enum ContactStatus: string
{
    case NEW = 'new';
    case READ = 'read';
    case RESOLVED = 'resolved';

    public function getLabel(): string
    {
        return match ($this) {
            self::NEW => 'contact.status.new',
            self::READ => 'contact.status.read',
            self::RESOLVED => 'contact.status.resolved',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::NEW => 'bg-yellow-500',
            self::READ => 'bg-blue-500',
            self::RESOLVED => 'bg-green-500',
        };
    }
}
