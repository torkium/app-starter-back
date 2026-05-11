<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260510142000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add delivery_started_at and failure tracking to outbox_messages.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE outbox_messages ADD delivery_started_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE outbox_messages ADD failed_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE outbox_messages ADD failure_reason VARCHAR(255) DEFAULT NULL');
        $this->addSql('CREATE INDEX idx_outbox_delivery_started ON outbox_messages (delivery_started_at)');
        $this->addSql('CREATE INDEX idx_outbox_failed_at ON outbox_messages (failed_at)');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP INDEX idx_outbox_failed_at ON outbox_messages');
        $this->addSql('DROP INDEX idx_outbox_delivery_started ON outbox_messages');
        $this->addSql('ALTER TABLE outbox_messages DROP failed_at');
        $this->addSql('ALTER TABLE outbox_messages DROP failure_reason');
        $this->addSql('ALTER TABLE outbox_messages DROP delivery_started_at');
    }
}
