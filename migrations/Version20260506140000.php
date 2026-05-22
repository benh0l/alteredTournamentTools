<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260506140000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add OAuth support with user_oauth_link table and created_via_oauth flag';
    }

    public function up(Schema $schema): void
    {
        // Add created_via_oauth to users
        $this->addSql('ALTER TABLE users ADD created_via_oauth BOOLEAN DEFAULT false NOT NULL');

        // Create user_oauth_link table
        $this->addSql('
            CREATE TABLE user_oauth_link (
                id SERIAL PRIMARY KEY,
                user_id INT NOT NULL,
                provider VARCHAR(50) NOT NULL DEFAULT \'altered_reunion\',
                provider_user_id VARCHAR(255) NOT NULL,
                provider_username VARCHAR(255) DEFAULT NULL,
                linked_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
                CONSTRAINT FK_user_oauth_link_user
                    FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
            )
        ');

        // Create indexes
        $this->addSql('CREATE INDEX idx_user_oauth_link_user ON user_oauth_link (user_id)');
        $this->addSql('CREATE INDEX idx_provider_user_id ON user_oauth_link (provider_user_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_provider_user ON user_oauth_link (provider, provider_user_id)');
        $this->addSql('CREATE UNIQUE INDEX unique_user_provider ON user_oauth_link (user_id, provider)');

        // Add comment for Doctrine type
        $this->addSql('COMMENT ON COLUMN user_oauth_link.linked_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE user_oauth_link');
        $this->addSql('ALTER TABLE users DROP COLUMN created_via_oauth');
    }
}
