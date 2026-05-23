<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322120527 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix user deletion: Add ON DELETE SET NULL to match player references for GDPR compliance';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE matches DROP CONSTRAINT fk_62615bac0990423');
        $this->addSql('ALTER TABLE matches DROP CONSTRAINT fk_62615bad22cabcd');
        $this->addSql('ALTER TABLE matches ALTER player1_id DROP NOT NULL');
        $this->addSql('ALTER TABLE matches ADD CONSTRAINT FK_62615BAC0990423 FOREIGN KEY (player1_id) REFERENCES registrations (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE matches ADD CONSTRAINT FK_62615BAD22CABCD FOREIGN KEY (player2_id) REFERENCES registrations (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE matches DROP CONSTRAINT FK_62615BAC0990423');
        $this->addSql('ALTER TABLE matches DROP CONSTRAINT FK_62615BAD22CABCD');
        $this->addSql('ALTER TABLE matches ALTER player1_id SET NOT NULL');
        $this->addSql('ALTER TABLE matches ADD CONSTRAINT fk_62615bac0990423 FOREIGN KEY (player1_id) REFERENCES registrations (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE matches ADD CONSTRAINT fk_62615bad22cabcd FOREIGN KEY (player2_id) REFERENCES registrations (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
