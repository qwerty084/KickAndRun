<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260315120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add player_order column to lobby for color selection';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lobby ADD player_order JSON DEFAULT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE lobby DROP COLUMN player_order');
    }
}
