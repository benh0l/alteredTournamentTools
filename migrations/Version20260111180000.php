<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Create system_alerts table for system health monitoring (FR75).
 */
final class Version20260111180000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create system_alerts table for system health monitoring';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE system_alerts (
            id SERIAL PRIMARY KEY,
            type VARCHAR(50) NOT NULL,
            severity VARCHAR(20) NOT NULL,
            message VARCHAR(255) NOT NULL,
            details JSON DEFAULT NULL,
            created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL,
            acknowledged_at TIMESTAMP(0) WITHOUT TIME ZONE DEFAULT NULL,
            acknowledged_by_id INTEGER DEFAULT NULL,
            alert_hash VARCHAR(64) DEFAULT NULL,
            CONSTRAINT fk_system_alerts_acknowledged_by FOREIGN KEY (acknowledged_by_id)
                REFERENCES users(id) ON DELETE SET NULL
        )');

        $this->addSql('COMMENT ON COLUMN system_alerts.created_at IS \'(DC2Type:datetime_immutable)\'');
        $this->addSql('COMMENT ON COLUMN system_alerts.acknowledged_at IS \'(DC2Type:datetime_immutable)\'');

        $this->addSql('CREATE INDEX idx_system_alerts_type ON system_alerts (type)');
        $this->addSql('CREATE INDEX idx_system_alerts_severity ON system_alerts (severity)');
        $this->addSql('CREATE INDEX idx_system_alerts_created ON system_alerts (created_at)');
        $this->addSql('CREATE INDEX idx_system_alerts_acknowledged_by ON system_alerts (acknowledged_by_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE system_alerts');
    }
}
