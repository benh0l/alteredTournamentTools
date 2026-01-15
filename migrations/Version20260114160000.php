<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to add faction2 column for Bifaction format.
 */
final class Version20260114160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add faction2 column to registrations table for Bifaction format';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE registrations ADD faction2 VARCHAR(50) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE registrations DROP COLUMN faction2');
    }
}
