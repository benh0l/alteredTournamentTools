<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260123113427 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tournaments ADD is_tumult BOOLEAN NOT NULL DEFAULT false');
        $this->addSql('ALTER TABLE tournaments ADD is_season_finals_qualifier BOOLEAN NOT NULL DEFAULT false');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tournaments DROP is_tumult');
        $this->addSql('ALTER TABLE tournaments DROP is_season_finals_qualifier');
    }
}
