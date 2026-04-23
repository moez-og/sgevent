<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421004000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add missing user_id column to code_promo for offer detail filtering.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE code_promo ADD user_id INT DEFAULT NULL');
        $this->addSql('CREATE INDEX IDX_F3A20D6A76ED395 ON code_promo (user_id)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX IDX_F3A20D6A76ED395 ON code_promo');
        $this->addSql('ALTER TABLE code_promo DROP user_id');
    }
}