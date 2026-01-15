<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260114105219 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rounds ALTER is_elimination_round DROP DEFAULT');
        $this->addSql('ALTER TABLE users ADD theme_color VARCHAR(20) DEFAULT NULL');
        $this->addSql('ALTER TABLE users ADD theme_mode VARCHAR(10) DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE rounds ALTER is_elimination_round SET DEFAULT false');
        $this->addSql('ALTER TABLE users DROP theme_color');
        $this->addSql('ALTER TABLE users DROP theme_mode');
    }
}
