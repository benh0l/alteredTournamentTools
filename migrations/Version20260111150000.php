<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Adds notification_preferences column to users table (FR67).
 */
final class Version20260111150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add notification_preferences column to users table (FR67)';
    }

    public function up(Schema $schema): void
    {
        // Add notification_preferences column with default values
        $this->addSql("ALTER TABLE users ADD notification_preferences JSON DEFAULT '{\"d_minus_1\": true, \"h_minus_1\": true, \"round_start\": true, \"channels\": {\"email\": true, \"push\": false}}' NOT NULL");
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE users DROP notification_preferences');
    }
}
