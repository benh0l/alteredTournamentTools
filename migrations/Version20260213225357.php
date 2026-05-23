<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260213225357 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add check-in functionality for tournaments';
    }

    public function up(Schema $schema): void
    {
        // Add check-in timestamp to registrations
        $this->addSql('ALTER TABLE registrations ADD checked_in_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_registrations_checked_in_at ON registrations (checked_in_at)');

        // Add check-in configuration to tournaments (default to false for existing tournaments)
        $this->addSql('ALTER TABLE tournaments ADD check_in_enabled BOOLEAN NOT NULL DEFAULT FALSE');
        $this->addSql('ALTER TABLE tournaments ADD check_in_open BOOLEAN NOT NULL DEFAULT FALSE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_registrations_checked_in_at');
        $this->addSql('ALTER TABLE registrations DROP checked_in_at');
        $this->addSql('ALTER TABLE tournaments DROP check_in_enabled');
        $this->addSql('ALTER TABLE tournaments DROP check_in_open');
    }
}
