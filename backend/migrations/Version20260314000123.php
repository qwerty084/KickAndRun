<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260314000123 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE chat_message (id UUID NOT NULL, content VARCHAR(500) NOT NULL, context VARCHAR(10) NOT NULL, context_id UUID NOT NULL, created_at TIMESTAMP(0) WITHOUT TIME ZONE NOT NULL, player_id UUID NOT NULL, PRIMARY KEY (id))');
        $this->addSql('CREATE INDEX IDX_FAB3FC1699E6F5DF ON chat_message (player_id)');
        $this->addSql('CREATE INDEX idx_chat_context ON chat_message (context, context_id, created_at)');
        $this->addSql('ALTER TABLE chat_message ADD CONSTRAINT FK_FAB3FC1699E6F5DF FOREIGN KEY (player_id) REFERENCES player (id) NOT DEFERRABLE');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE chat_message DROP CONSTRAINT FK_FAB3FC1699E6F5DF');
        $this->addSql('DROP TABLE chat_message');
    }
}
