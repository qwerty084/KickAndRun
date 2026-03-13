<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260313233613 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX uniq_4586aafbb6612fd9');
        $this->addSql('CREATE INDEX IDX_4586AAFBB6612FD9 ON game_session (lobby_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX IDX_4586AAFBB6612FD9');
        $this->addSql('CREATE UNIQUE INDEX uniq_4586aafbb6612fd9 ON game_session (lobby_id)');
    }
}
