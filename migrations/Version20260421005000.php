<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421005000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create offre_analysis table for storing offer analysis results';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('CREATE TABLE offre_analysis (
            id INT AUTO_INCREMENT NOT NULL,
            offre_id INT,
            score INT NOT NULL,
            evaluation LONGTEXT NOT NULL,
            points_faibles JSON NOT NULL,
            ameliorations JSON NOT NULL,
            offre_optimisee JSON NOT NULL,
            diffusion JSON NOT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY(id),
            KEY idx_offre_id (offre_id),
            KEY idx_created_at (created_at)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE offre_analysis');
    }
}
