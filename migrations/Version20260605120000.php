<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260605120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add recurrence_group_id to tournaments for recurring tournament series';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tournaments ADD recurrence_group_id VARCHAR(36) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_tournaments_recurrence_group ON tournaments (recurrence_group_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_tournaments_recurrence_group');
        $this->addSql('ALTER TABLE tournaments DROP COLUMN recurrence_group_id');
    }
}
