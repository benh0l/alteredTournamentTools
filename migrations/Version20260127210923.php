<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add is_guest column to users table for guest player support.
 */
final class Version20260127210923 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add is_guest column to users table for guest player support';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users ADD is_guest BOOLEAN NOT NULL DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP COLUMN is_guest');
    }
}
