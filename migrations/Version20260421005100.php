<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421005100 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create offre_analysis_tracking table for tracking analysis requests';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE offre_analysis_tracking (
            tracking_id VARCHAR(36) NOT NULL,
            offre_id INT,
            status VARCHAR(50) NOT NULL DEFAULT "pending",
            analysis_id INT,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            expires_at DATETIME NOT NULL,
            PRIMARY KEY(tracking_id),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE offre_analysis_tracking');
    }
}
