<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260512123000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add retry scheduling metadata to outbox messages.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('outbox_messages');

        if (!$table->hasColumn('attempt_count')) {
            $this->addSql('ALTER TABLE outbox_messages ADD attempt_count INT NOT NULL DEFAULT 0');
        }

        if (!$table->hasColumn('next_attempt_at')) {
            $this->addSql('ALTER TABLE outbox_messages ADD next_attempt_at DATETIME DEFAULT NULL');
        }

        if (!$table->hasIndex('idx_outbox_next_attempt')) {
            $this->addSql('CREATE INDEX idx_outbox_next_attempt ON outbox_messages (next_attempt_at)');
        }

        if (!$table->hasIndex('idx_outbox_claim_token')) {
            $this->addSql('CREATE INDEX idx_outbox_claim_token ON outbox_messages (claim_token)');
        }

        if (!$table->hasIndex('idx_outbox_pending_claim')) {
            $this->addSql('CREATE INDEX idx_outbox_pending_claim ON outbox_messages (published_at, failed_at, delivery_started_at, claim_token, next_attempt_at, created_at)');
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('outbox_messages');

        if ($table->hasIndex('idx_outbox_pending_claim')) {
            $this->addSql('DROP INDEX idx_outbox_pending_claim ON outbox_messages');
        }

        if ($table->hasIndex('idx_outbox_claim_token')) {
            $this->addSql('DROP INDEX idx_outbox_claim_token ON outbox_messages');
        }

        if ($table->hasIndex('idx_outbox_next_attempt')) {
            $this->addSql('DROP INDEX idx_outbox_next_attempt ON outbox_messages');
        }

        if ($table->hasColumn('next_attempt_at')) {
            $this->addSql('ALTER TABLE outbox_messages DROP next_attempt_at');
        }

        if ($table->hasColumn('attempt_count')) {
            $this->addSql('ALTER TABLE outbox_messages DROP attempt_count');
        }
    }
}
