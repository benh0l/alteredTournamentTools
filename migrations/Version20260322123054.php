<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260322123054 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Fix user deletion: Add ON DELETE SET NULL to AdminMessage and MatchSubmission user references';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin_messages DROP CONSTRAINT admin_messages_sender_id_fkey');
        $this->addSql('ALTER TABLE admin_messages DROP CONSTRAINT admin_messages_recipient_user_id_fkey');
        $this->addSql('ALTER TABLE admin_messages ALTER sender_id DROP NOT NULL');
        $this->addSql('ALTER TABLE admin_messages ADD CONSTRAINT FK_4FA21990F624B39D FOREIGN KEY (sender_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE admin_messages ADD CONSTRAINT FK_4FA21990B15EFB97 FOREIGN KEY (recipient_user_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
        $this->addSql('ALTER TABLE match_submissions DROP CONSTRAINT fk_df818df79f7d87d');
        $this->addSql('ALTER TABLE match_submissions ALTER submitted_by_id DROP NOT NULL');
        $this->addSql('ALTER TABLE match_submissions ADD CONSTRAINT FK_DF818DF79F7D87D FOREIGN KEY (submitted_by_id) REFERENCES users (id) ON DELETE SET NULL NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE admin_messages DROP CONSTRAINT FK_4FA21990F624B39D');
        $this->addSql('ALTER TABLE admin_messages DROP CONSTRAINT FK_4FA21990B15EFB97');
        $this->addSql('ALTER TABLE admin_messages ALTER sender_id SET NOT NULL');
        $this->addSql('ALTER TABLE admin_messages ADD CONSTRAINT admin_messages_sender_id_fkey FOREIGN KEY (sender_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE admin_messages ADD CONSTRAINT admin_messages_recipient_user_id_fkey FOREIGN KEY (recipient_user_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
        $this->addSql('ALTER TABLE match_submissions DROP CONSTRAINT FK_DF818DF79F7D87D');
        $this->addSql('ALTER TABLE match_submissions ALTER submitted_by_id SET NOT NULL');
        $this->addSql('ALTER TABLE match_submissions ADD CONSTRAINT fk_df818df79f7d87d FOREIGN KEY (submitted_by_id) REFERENCES users (id) NOT DEFERRABLE INITIALLY IMMEDIATE');
    }
}
