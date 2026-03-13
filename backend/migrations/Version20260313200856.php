<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260313200856 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE game_session (id UUID NOT NULL, status VARCHAR(20) NOT NULL, game_state JSON NOT NULL, current_turn INT NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, lobby_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_4586AAFBB6612FD9 ON game_session (lobby_id)');
        $this->addSql('CREATE TABLE lobby (id UUID NOT NULL, code VARCHAR(6) NOT NULL, name VARCHAR(128) NOT NULL, max_players INT NOT NULL, status VARCHAR(20) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, updated_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, host_player_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_CCE455F777153098 ON lobby (code)');
        $this->addSql('CREATE INDEX IDX_CCE455F76D63A972 ON lobby (host_player_id)');
        $this->addSql('CREATE TABLE lobby_player (lobby_id UUID NOT NULL, player_id UUID NOT NULL, PRIMARY KEY (lobby_id, player_id))');
        $this->addSql('CREATE INDEX IDX_7D7F3054B6612FD9 ON lobby_player (lobby_id)');
        $this->addSql('CREATE INDEX IDX_7D7F305499E6F5DF ON lobby_player (player_id)');
        $this->addSql('CREATE TABLE player (id UUID NOT NULL, name VARCHAR(64) NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, PRIMARY KEY (id))');
        $this->addSql('ALTER TABLE game_session ADD CONSTRAINT FK_4586AAFBB6612FD9 FOREIGN KEY (lobby_id) REFERENCES lobby (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE lobby ADD CONSTRAINT FK_CCE455F76D63A972 FOREIGN KEY (host_player_id) REFERENCES player (id) NOT DEFERRABLE');
        $this->addSql('ALTER TABLE lobby_player ADD CONSTRAINT FK_7D7F3054B6612FD9 FOREIGN KEY (lobby_id) REFERENCES lobby (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE lobby_player ADD CONSTRAINT FK_7D7F305499E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) ON DELETE CASCADE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE game_session DROP CONSTRAINT FK_4586AAFBB6612FD9');
        $this->addSql('ALTER TABLE lobby DROP CONSTRAINT FK_CCE455F76D63A972');
        $this->addSql('ALTER TABLE lobby_player DROP CONSTRAINT FK_7D7F3054B6612FD9');
        $this->addSql('ALTER TABLE lobby_player DROP CONSTRAINT FK_7D7F305499E6F5DF');
        $this->addSql('DROP TABLE game_session');
        $this->addSql('DROP TABLE lobby');
        $this->addSql('DROP TABLE lobby_player');
        $this->addSql('DROP TABLE player');
    }
}
