<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add missing columns to tournaments table and update foreign key constraint.
 */
final class Version20260111190000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add latitude, longitude, results_published columns to tournaments and update organizer FK';
    }

    public function up(Schema $schema): void
    {
        // Add missing columns
        $this->addSql('ALTER TABLE tournaments ADD COLUMN IF NOT EXISTS latitude NUMERIC(10, 7) DEFAULT NULL');
        $this->addSql('ALTER TABLE tournaments ADD COLUMN IF NOT EXISTS longitude NUMERIC(10, 7) DEFAULT NULL');
        $this->addSql('ALTER TABLE tournaments ADD COLUMN IF NOT EXISTS results_published BOOLEAN NOT NULL DEFAULT false');

        // Update foreign key constraint to support SET NULL on delete
        $this->addSql('ALTER TABLE tournaments DROP CONSTRAINT IF EXISTS fk_e4bcfac3876c4dda');
        $this->addSql('ALTER TABLE tournaments DROP CONSTRAINT IF EXISTS FK_E4BCFAC3876C4DDA');
        $this->addSql('ALTER TABLE tournaments ADD CONSTRAINT FK_E4BCFAC3876C4DDA FOREIGN KEY (organizer_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE INITIALLY IMMEDIATE');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tournaments DROP COLUMN IF EXISTS latitude');
        $this->addSql('ALTER TABLE tournaments DROP COLUMN IF EXISTS longitude');
        $this->addSql('ALTER TABLE tournaments DROP COLUMN IF EXISTS results_published');
    }
}
