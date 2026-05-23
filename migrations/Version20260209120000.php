<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add privacy_settings column to users table for GDPR compliance.
 */
final class Version20260209120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add privacy_settings column to users table for GDPR profile visibility controls';
    }

    public function up(Schema $schema): void
    {
        $defaultSettings = json_encode([
            'show_real_name' => false,
            'show_in_results' => true,
            'show_match_history' => true,
        ]);

        $this->addSql("ALTER TABLE users ADD privacy_settings JSON NOT NULL DEFAULT '$defaultSettings'");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN privacy_settings');
    }
}
