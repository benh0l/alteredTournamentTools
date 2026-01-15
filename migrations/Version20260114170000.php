<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to increase format column length from 20 to 30 characters.
 */
final class Version20260114170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Increase format column length from 20 to 30 characters for longer format names';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tournaments ALTER COLUMN format TYPE VARCHAR(30)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tournaments ALTER COLUMN format TYPE VARCHAR(20)');
    }
}
