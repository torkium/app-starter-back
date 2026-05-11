<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260511170000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Add isolated admin_users table for EasyAdmin back-office authentication.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql(<<<'SQL'
            CREATE TABLE admin_users (
              id VARCHAR(36) NOT NULL,
              email VARCHAR(180) NOT NULL,
              password_hash VARCHAR(255) NOT NULL,
              display_name VARCHAR(180) NOT NULL,
              roles JSON NOT NULL,
              active TINYINT NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              last_login_at DATETIME DEFAULT NULL,
              UNIQUE INDEX uniq_admin_user_email (email),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE admin_users');
    }
}
