<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration for admin_messages table (FR71).
 */
final class Version20260111160000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create admin_messages table for tracking admin communications to organizers';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE admin_messages (
            id SERIAL PRIMARY KEY,
            sender_id INT NOT NULL REFERENCES users(id),
            recipient_type VARCHAR(20) NOT NULL,
            recipient_user_id INT DEFAULT NULL REFERENCES users(id),
            subject VARCHAR(255) NOT NULL,
            body TEXT NOT NULL,
            sent_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            recipient_count INT NOT NULL DEFAULT 1
        )');

        $this->addSql('CREATE INDEX idx_admin_message_sender ON admin_messages (sender_id)');
        $this->addSql('CREATE INDEX idx_admin_message_sent_at ON admin_messages (sent_at)');

        $this->addSql('COMMENT ON COLUMN admin_messages.sent_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE admin_messages');
    }
}
