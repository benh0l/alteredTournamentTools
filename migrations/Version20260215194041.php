<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260215194041 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add claim token fields to users table for bulk import feature';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tournaments ALTER check_in_enabled DROP DEFAULT');
        $this->addSql('ALTER TABLE tournaments ALTER check_in_open DROP DEFAULT');
        $this->addSql('ALTER TABLE users ADD claim_token VARCHAR(64) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD claim_token_expires_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9637A4A7D ON users (claim_token)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE tournaments ALTER check_in_enabled SET DEFAULT false');
        $this->addSql('ALTER TABLE tournaments ALTER check_in_open SET DEFAULT false');
        $this->addSql('DROP INDEX UNIQ_1483A5E9637A4A7D');
        $this->addSql('ALTER TABLE users DROP claim_token');
        $this->addSql('ALTER TABLE users DROP claim_token_expires_at');
    }
}
