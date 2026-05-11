<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260510023000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename media upload token column to hashed storage naming.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media_assets CHANGE upload_token upload_token_hash VARCHAR(64) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE media_assets CHANGE upload_token_hash upload_token VARCHAR(64) NOT NULL');
    }
}
