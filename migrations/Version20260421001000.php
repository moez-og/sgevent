<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421001000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the offre_badge_user table required by App\\Entity\\OffreBadgeUser.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE offre_badge_user (
                id INT AUTO_INCREMENT NOT NULL,
                user_id INT DEFAULT NULL,
                badge_code VARCHAR(255) DEFAULT NULL,
                titre VARCHAR(255) NOT NULL,
                pourcentage DOUBLE PRECISION NOT NULL,
                date_debut DATE NOT NULL,
                date_fin DATE NOT NULL,
                statut VARCHAR(255) NOT NULL,
                date_created DATETIME NOT NULL,
                UNIQUE INDEX UNIQ_A79E419B6B3CA4B (user_id),
                UNIQUE INDEX UNIQ_A79E419B7A5CB15F (badge_code),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql('CREATE INDEX IDX_A79E419B6B3CA4B ON offre_badge_user (user_id)');
        $this->addSql('CREATE INDEX IDX_A79E419B7A5CB15F ON offre_badge_user (badge_code)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE offre_badge_user');
    }
}