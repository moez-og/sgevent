<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421000000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Create the notifications table required by App\\Entity\\Notification.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE notifications (
                id INT AUTO_INCREMENT NOT NULL,
                receiver_id INT DEFAULT NULL,
                sender_id INT DEFAULT NULL,
                type VARCHAR(255) NOT NULL,
                title VARCHAR(255) NOT NULL,
                body LONGTEXT NOT NULL,
                entity_type VARCHAR(255) NOT NULL,
                entity_id INT NOT NULL,
                created_at DATETIME NOT NULL,
                read_at DATETIME DEFAULT NULL,
                metadata_json LONGTEXT DEFAULT NULL,
                UNIQUE INDEX UNIQ_72F777248C79FD5F (receiver_id),
                INDEX IDX_72F777248DE7A1C8 (sender_id),
                PRIMARY KEY(id)
            ) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB
        SQL);

        $this->addSql(<<<'SQL'
            ALTER TABLE notifications
                ADD CONSTRAINT FK_72F777248C79FD5F FOREIGN KEY (receiver_id) REFERENCES user (id) ON DELETE SET NULL,
                ADD CONSTRAINT FK_72F777248DE7A1C8 FOREIGN KEY (sender_id) REFERENCES user (id) ON DELETE SET NULL
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE notifications');
    }
}