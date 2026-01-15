<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Migration to rename 'constructed' format to 'constructed_standard'.
 */
final class Version20260114150000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename constructed format to constructed_standard';
    }

    public function up(Schema $schema): void
    {
        // Update existing tournaments with 'constructed' format to 'constructed_standard'
        $this->addSql("UPDATE tournaments SET format = 'constructed_standard' WHERE format = 'constructed'");
    }

    public function down(Schema $schema): void
    {
        // Revert 'constructed_standard' back to 'constructed'
        $this->addSql("UPDATE tournaments SET format = 'constructed' WHERE format = 'constructed_standard'");
    }
}
