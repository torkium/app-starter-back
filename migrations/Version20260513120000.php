<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260513120000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Track last applied Stripe subscription event ordering on user subscriptions.';
    }

    public function up(Schema $schema): void
    {
        $table = $schema->getTable('user_subscriptions');

        if (!$table->hasColumn('last_stripe_event_created_at')) {
            $this->addSql('ALTER TABLE user_subscriptions ADD last_stripe_event_created_at DATETIME DEFAULT NULL');
            $this->addSql(<<<'SQL'
                UPDATE user_subscriptions subscription
                LEFT JOIN (
                    SELECT user_id, MAX(occurred_at) AS occurred_at
                    FROM billing_events
                    WHERE user_id IS NOT NULL
                      AND type LIKE 'customer.subscription.%'
                    GROUP BY user_id
                ) event ON event.user_id = subscription.user_id
                SET subscription.last_stripe_event_created_at = event.occurred_at
            SQL);
        }

        if (!$table->hasColumn('last_stripe_event_type_rank')) {
            $this->addSql('ALTER TABLE user_subscriptions ADD last_stripe_event_type_rank INT DEFAULT NULL');
            $this->addSql(<<<'SQL'
                UPDATE user_subscriptions subscription
                LEFT JOIN (
                    SELECT latest.user_id,
                           MAX(
                               CASE latest.type
                                   WHEN 'customer.subscription.deleted' THEN 300
                                   WHEN 'customer.subscription.updated' THEN 200
                                   WHEN 'customer.subscription.created' THEN 100
                                   ELSE 0
                               END
                           ) AS event_type_rank
                    FROM billing_events latest
                    INNER JOIN (
                        SELECT user_id, MAX(occurred_at) AS occurred_at
                        FROM billing_events
                        WHERE user_id IS NOT NULL
                          AND type LIKE 'customer.subscription.%'
                        GROUP BY user_id
                    ) event ON event.user_id = latest.user_id
                           AND event.occurred_at = latest.occurred_at
                    WHERE latest.user_id IS NOT NULL
                      AND latest.type LIKE 'customer.subscription.%'
                    GROUP BY latest.user_id
                ) event ON event.user_id = subscription.user_id
                SET subscription.last_stripe_event_type_rank = event.event_type_rank
            SQL);
        }
    }

    public function down(Schema $schema): void
    {
        $table = $schema->getTable('user_subscriptions');

        if ($table->hasColumn('last_stripe_event_created_at')) {
            $this->addSql('ALTER TABLE user_subscriptions DROP last_stripe_event_created_at');
        }

        if ($table->hasColumn('last_stripe_event_type_rank')) {
            $this->addSql('ALTER TABLE user_subscriptions DROP last_stripe_event_type_rank');
        }
    }
}
