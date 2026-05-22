<?php

declare(strict_types=1);

namespace App\Service\OAuth;

class OAuthLoginException extends \RuntimeException
{
    public const EMAIL_CONFLICT = 'email_conflict';
    public const PSEUDO_CONFLICT = 'pseudo_conflict';

    private string $type;

    /** @var array<string, mixed> */
    private array $context = [];

    /**
     * @param array<string, mixed> $context
     */
    public function __construct(string $message, string $type = '', array $context = [])
    {
        parent::__construct($message);
        $this->type = $type;
        $this->context = $context;
    }

    public static function emailConflict(string $email): self
    {
        return new self(
            'Un compte existe déjà avec cet email. Connectez-vous d\'abord avec votre mot de passe puis liez votre compte Altered Re:Union depuis votre profil.',
            self::EMAIL_CONFLICT,
            ['email' => $email]
        );
    }

    /**
     * @param array<string, mixed> $claims
     */
    public static function pseudoConflict(string $pseudo, array $claims): self
    {
        return new self(
            'Ce pseudo est déjà utilisé.',
            self::PSEUDO_CONFLICT,
            ['pseudo' => $pseudo, 'claims' => $claims]
        );
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getContext(): array
    {
        return $this->context;
    }

    public function isEmailConflict(): bool
    {
        return $this->type === self::EMAIL_CONFLICT;
    }

    public function isPseudoConflict(): bool
    {
        return $this->type === self::PSEUDO_CONFLICT;
    }
}
