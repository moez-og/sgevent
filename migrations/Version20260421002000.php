<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260421002000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add the nb_tickets column to the inscription table.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inscription ADD nb_tickets INT NOT NULL DEFAULT 1');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE inscription DROP nb_tickets');
    }
}