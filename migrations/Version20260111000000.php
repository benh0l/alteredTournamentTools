<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Epic 2: User Authentication and Authorization
 * Creates users and reset_password_requests tables
 */
final class Version20260111000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create users and reset_password_requests tables for authentication';
    }

    public function up(Schema $schema): void
    {
        // Create users table
        $this->addSql('CREATE TABLE users (
            id SERIAL PRIMARY KEY,
            email VARCHAR(180) NOT NULL,
            password VARCHAR(255) NOT NULL,
            roles JSON NOT NULL DEFAULT \'["ROLE_USER"]\',
            pseudo VARCHAR(50) NOT NULL,
            name VARCHAR(100) DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            updated_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            is_verified BOOLEAN NOT NULL DEFAULT FALSE
        )');

        // Add unique constraints
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E9E7927C74 ON users (email)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_1483A5E986CC499D ON users (pseudo)');

        // Add index for email lookups
        $this->addSql('CREATE INDEX idx_users_email ON users (email)');

        // Add comments for immutable datetime types
        $this->addSql('COMMENT ON COLUMN users.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN users.updated_at IS \'(DC2Type:datetime_immutable)\'');

        // Create reset_password_requests table
        $this->addSql('CREATE TABLE reset_password_requests (
            id SERIAL PRIMARY KEY,
            user_id INTEGER NOT NULL,
            selector VARCHAR(20) NOT NULL,
            hashed_token VARCHAR(100) NOT NULL,
            requested_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            expires_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL
        )');

        // Add foreign key constraint
        $this->addSql('ALTER TABLE reset_password_requests
            ADD CONSTRAINT FK_27E44F8A76ED395
            FOREIGN KEY (user_id)
            REFERENCES users (id)
            ON DELETE CASCADE
            NOT DEFERRABLE INITIALLY IMMEDIATE');

        // Add index for expires_at (for cleanup queries)
        $this->addSql('CREATE INDEX idx_reset_password_expires ON reset_password_requests (expires_at)');

        // Add index for user_id
        $this->addSql('CREATE INDEX IDX_27E44F8A76ED395 ON reset_password_requests (user_id)');

        // Add comments for immutable datetime types
        $this->addSql('COMMENT ON COLUMN reset_password_requests.requested_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN reset_password_requests.expires_at IS \'(DC2Type:datetime_immutable)\'');
    }

    public function down(Schema $schema): void
    {
        // Drop reset_password_requests first (foreign key constraint)
        $this->addSql('DROP TABLE IF EXISTS reset_password_requests');

        // Drop users table
        $this->addSql('DROP TABLE IF EXISTS users');
    }
}
