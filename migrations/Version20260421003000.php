<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421003000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the reservation_offre table required by the offer detail and reservation flow.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE reservation_offre (
                id INT AUTO_INCREMENT NOT NULL,
                user_id INT NOT NULL,
                offre_id INT NOT NULL,
                lieu_id INT DEFAULT NULL,
                date_reservation DATE NOT NULL,
                nombre_personnes INT NOT NULL,
                statut VARCHAR(255) NOT NULL,
                note LONGTEXT DEFAULT NULL,
                created_at DATETIME NOT NULL,
                INDEX IDX_A6B3C8E8A76ED395 (user_id),
                INDEX IDX_A6B3C8E88594C45C (offre_id),
                INDEX IDX_A6B3C8E88AC62AF2 (lieu_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE reservation_offre');
    }
}