<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Make tournament organizer nullable for RGPD user deletion compliance (FR74).
 */
final class Version20260111170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Make tournament organizer_id nullable for RGPD compliance when deleting users';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tournaments ALTER COLUMN organizer_id DROP NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // Cannot revert if there are NULL values
        $this->addSql('ALTER TABLE tournaments ALTER COLUMN organizer_id SET NOT NULL');
    }
}
