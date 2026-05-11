<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260509234807 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql(<<<'SQL'
            CREATE TABLE billing_events (
              id VARCHAR(36) NOT NULL,
              external_id VARCHAR(180) NOT NULL,
              type VARCHAR(120) NOT NULL,
              payload JSON NOT NULL,
              occurred_at DATETIME NOT NULL,
              created_at DATETIME NOT NULL,
              user_id VARCHAR(36) DEFAULT NULL,
              INDEX IDX_165D741AA76ED395 (user_id),
              UNIQUE INDEX uniq_billing_event_external_id (external_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE billing_plans (
              id VARCHAR(36) NOT NULL,
              code VARCHAR(80) NOT NULL,
              name VARCHAR(180) NOT NULL,
              description LONGTEXT DEFAULT NULL,
              amount_minor INT NOT NULL,
              currency VARCHAR(3) NOT NULL,
              interval_unit VARCHAR(20) NOT NULL,
              stripe_price_id VARCHAR(180) DEFAULT NULL,
              active TINYINT NOT NULL,
              created_at DATETIME NOT NULL,
              UNIQUE INDEX uniq_billing_plan_code (code),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE legal_documents (
              id VARCHAR(36) NOT NULL,
              code VARCHAR(80) NOT NULL,
              version VARCHAR(32) NOT NULL,
              locale VARCHAR(10) NOT NULL,
              title VARCHAR(180) NOT NULL,
              content LONGTEXT NOT NULL,
              active TINYINT NOT NULL,
              published_at DATETIME NOT NULL,
              created_at DATETIME NOT NULL,
              UNIQUE INDEX uniq_legal_document_code_version_locale (code, version, locale),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE media_assets (
              id VARCHAR(36) NOT NULL,
              filename VARCHAR(180) NOT NULL,
              mime_type VARCHAR(120) NOT NULL,
              size INT NOT NULL,
              object_key VARCHAR(255) NOT NULL,
              status VARCHAR(40) NOT NULL,
              purpose VARCHAR(80) DEFAULT NULL,
              upload_token VARCHAR(64) NOT NULL,
              upload_token_expires_at DATETIME NOT NULL,
              preview_url VARCHAR(500) DEFAULT NULL,
              uploaded_at DATETIME DEFAULT NULL,
              completed_at DATETIME DEFAULT NULL,
              checksum_sha256 VARCHAR(64) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              user_id VARCHAR(36) NOT NULL,
              UNIQUE INDEX UNIQ_C5A8E7504DDB172C (object_key),
              INDEX IDX_C5A8E750A76ED395 (user_id),
              INDEX idx_media_asset_user_created (user_id, created_at),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE outbox_messages (
              id VARCHAR(36) NOT NULL,
              topic VARCHAR(180) NOT NULL,
              channel VARCHAR(32) NOT NULL,
              payload JSON NOT NULL,
              created_at DATETIME NOT NULL,
              claim_token VARCHAR(36) DEFAULT NULL,
              claimed_at DATETIME DEFAULT NULL,
              published_at DATETIME DEFAULT NULL,
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE push_subscriptions (
              id VARCHAR(36) NOT NULL,
              endpoint VARCHAR(500) NOT NULL,
              p256dh VARCHAR(255) DEFAULT NULL,
              auth VARCHAR(255) DEFAULT NULL,
              expiration_time INT DEFAULT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              user_id VARCHAR(36) NOT NULL,
              INDEX IDX_3FEC449DA76ED395 (user_id),
              UNIQUE INDEX uniq_push_subscription_endpoint (endpoint),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE refresh_tokens (
              id VARCHAR(36) NOT NULL,
              token_hash VARCHAR(64) NOT NULL,
              expires_at DATETIME NOT NULL,
              revoked_at DATETIME DEFAULT NULL,
              device_name VARCHAR(120) DEFAULT NULL,
              last_used_user_agent VARCHAR(500) DEFAULT NULL,
              last_used_ip VARCHAR(45) DEFAULT NULL,
              created_at DATETIME NOT NULL,
              last_used_at DATETIME DEFAULT NULL,
              revoked_reason VARCHAR(120) DEFAULT NULL,
              user_id VARCHAR(36) NOT NULL,
              UNIQUE INDEX uniq_refresh_token_hash (token_hash),
              INDEX IDX_9BACE7E1A76ED395 (user_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_action_tokens (
              id VARCHAR(36) NOT NULL,
              token_hash VARCHAR(64) NOT NULL,
              type VARCHAR(32) NOT NULL,
              payload JSON DEFAULT NULL,
              expires_at DATETIME NOT NULL,
              used_at DATETIME DEFAULT NULL,
              user_id VARCHAR(36) NOT NULL,
              UNIQUE INDEX uniq_user_action_token_hash (token_hash),
              INDEX IDX_86E48DB5A76ED395 (user_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_consents (
              id VARCHAR(36) NOT NULL,
              ip_address VARCHAR(45) DEFAULT NULL,
              user_agent VARCHAR(500) DEFAULT NULL,
              accepted_at DATETIME NOT NULL,
              user_id VARCHAR(36) NOT NULL,
              document_id VARCHAR(36) NOT NULL,
              INDEX IDX_E6572967A76ED395 (user_id),
              INDEX IDX_E6572967C33F7837 (document_id),
              UNIQUE INDEX uniq_user_consent_document (user_id, document_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE user_subscriptions (
              id VARCHAR(36) NOT NULL,
              status VARCHAR(40) NOT NULL,
              stripe_customer_id VARCHAR(180) DEFAULT NULL,
              stripe_subscription_id VARCHAR(180) DEFAULT NULL,
              current_period_start DATETIME DEFAULT NULL,
              current_period_end DATETIME DEFAULT NULL,
              cancel_at_period_end TINYINT NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              user_id VARCHAR(36) NOT NULL,
              plan_id VARCHAR(36) NOT NULL,
              INDEX IDX_EAF92751E899029B (plan_id),
              UNIQUE INDEX uniq_subscription_user (user_id),
              UNIQUE INDEX uniq_subscription_stripe_subscription (stripe_subscription_id),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            CREATE TABLE users (
              id VARCHAR(36) NOT NULL,
              email VARCHAR(180) NOT NULL,
              password_hash VARCHAR(255) NOT NULL,
              first_name VARCHAR(120) NOT NULL,
              last_name VARCHAR(120) NOT NULL,
              roles JSON NOT NULL,
              email_verified TINYINT NOT NULL,
              created_at DATETIME NOT NULL,
              updated_at DATETIME NOT NULL,
              UNIQUE INDEX uniq_user_email (email),
              PRIMARY KEY (id)
            ) DEFAULT CHARACTER SET utf8mb4
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              billing_events
            ADD
              CONSTRAINT FK_165D741AA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE
            SET
              NULL
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              media_assets
            ADD
              CONSTRAINT FK_C5A8E750A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              push_subscriptions
            ADD
              CONSTRAINT FK_3FEC449DA76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              refresh_tokens
            ADD
              CONSTRAINT FK_9BACE7E1A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              user_action_tokens
            ADD
              CONSTRAINT FK_86E48DB5A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              user_consents
            ADD
              CONSTRAINT FK_E6572967A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              user_consents
            ADD
              CONSTRAINT FK_E6572967C33F7837 FOREIGN KEY (document_id) REFERENCES legal_documents (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              user_subscriptions
            ADD
              CONSTRAINT FK_EAF92751A76ED395 FOREIGN KEY (user_id) REFERENCES users (id) ON DELETE CASCADE
        SQL);
        $this->addSql(<<<'SQL'
            ALTER TABLE
              user_subscriptions
            ADD
              CONSTRAINT FK_EAF92751E899029B FOREIGN KEY (plan_id) REFERENCES billing_plans (id) ON DELETE RESTRICT
        SQL);
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE billing_events DROP FOREIGN KEY FK_165D741AA76ED395');
        $this->addSql('ALTER TABLE media_assets DROP FOREIGN KEY FK_C5A8E750A76ED395');
        $this->addSql('ALTER TABLE push_subscriptions DROP FOREIGN KEY FK_3FEC449DA76ED395');
        $this->addSql('ALTER TABLE refresh_tokens DROP FOREIGN KEY FK_9BACE7E1A76ED395');
        $this->addSql('ALTER TABLE user_action_tokens DROP FOREIGN KEY FK_86E48DB5A76ED395');
        $this->addSql('ALTER TABLE user_consents DROP FOREIGN KEY FK_E6572967A76ED395');
        $this->addSql('ALTER TABLE user_consents DROP FOREIGN KEY FK_E6572967C33F7837');
        $this->addSql('ALTER TABLE user_subscriptions DROP FOREIGN KEY FK_EAF92751A76ED395');
        $this->addSql('ALTER TABLE user_subscriptions DROP FOREIGN KEY FK_EAF92751E899029B');
        $this->addSql('DROP TABLE billing_events');
        $this->addSql('DROP TABLE billing_plans');
        $this->addSql('DROP TABLE legal_documents');
        $this->addSql('DROP TABLE media_assets');
        $this->addSql('DROP TABLE outbox_messages');
        $this->addSql('DROP TABLE push_subscriptions');
        $this->addSql('DROP TABLE refresh_tokens');
        $this->addSql('DROP TABLE user_action_tokens');
        $this->addSql('DROP TABLE user_consents');
        $this->addSql('DROP TABLE user_subscriptions');
        $this->addSql('DROP TABLE users');
    }
}
