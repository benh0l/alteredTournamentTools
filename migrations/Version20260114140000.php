<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Add faction and hero columns to registrations table for player deck choice tracking.
 */
final class Version20260114140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add faction and hero columns to registrations table';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE registrations ADD faction VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE registrations ADD hero VARCHAR(100) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE registrations DROP faction');
        $this->addSql('ALTER TABLE registrations DROP hero');
    }
}
