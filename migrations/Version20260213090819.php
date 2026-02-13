<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260213090819 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add dropped_at column to registrations table for player drop functionality';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE registrations ADD dropped_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_registrations_dropped_at ON registrations (dropped_at)');
        $this->addSql('ALTER TABLE tournaments ALTER is_tumult DROP DEFAULT');
        $this->addSql('ALTER TABLE tournaments ALTER is_season_finals_qualifier DROP DEFAULT');
        $this->addSql('ALTER TABLE users ALTER privacy_settings DROP DEFAULT');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX idx_registrations_dropped_at');
        $this->addSql('ALTER TABLE registrations DROP dropped_at');
        $this->addSql('ALTER TABLE tournaments ALTER is_tumult SET DEFAULT false');
        $this->addSql('ALTER TABLE tournaments ALTER is_season_finals_qualifier SET DEFAULT false');
        $this->addSql('ALTER TABLE users ALTER privacy_settings SET DEFAULT \'{"show_real_name":false,"show_in_results":true,"show_match_history":true}\'');
    }
}
