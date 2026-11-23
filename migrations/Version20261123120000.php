<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20261123120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add team tournaments (2v2): tournaments.team_size and partner fields on registrations';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE tournaments ADD team_size SMALLINT DEFAULT 1 NOT NULL');

        $this->addSql('ALTER TABLE registrations ADD partner_id INT DEFAULT NULL');
        $this->addSql('ALTER TABLE registrations ADD partner_decklist_url VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE registrations ADD partner_faction VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE registrations ADD partner_faction2 VARCHAR(50) DEFAULT NULL');
        $this->addSql('ALTER TABLE registrations ADD partner_hero VARCHAR(100) DEFAULT NULL');
        $this->addSql('ALTER TABLE registrations ADD CONSTRAINT FK_53DE51E79393F8FE FOREIGN KEY (partner_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('CREATE INDEX idx_registrations_partner ON registrations (partner_id)');
        $this->addSql('CREATE UNIQUE INDEX uniq_registration_tournament_partner ON registrations (tournament_id, partner_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX uniq_registration_tournament_partner');
        $this->addSql('DROP INDEX idx_registrations_partner');
        $this->addSql('ALTER TABLE registrations DROP CONSTRAINT FK_53DE51E79393F8FE');
        $this->addSql('ALTER TABLE registrations DROP COLUMN partner_hero');
        $this->addSql('ALTER TABLE registrations DROP COLUMN partner_faction2');
        $this->addSql('ALTER TABLE registrations DROP COLUMN partner_faction');
        $this->addSql('ALTER TABLE registrations DROP COLUMN partner_decklist_url');
        $this->addSql('ALTER TABLE registrations DROP COLUMN partner_id');

        $this->addSql('ALTER TABLE tournaments DROP COLUMN team_size');
    }
}
