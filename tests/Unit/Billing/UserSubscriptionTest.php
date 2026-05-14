<?php

declare(strict_types=1);

namespace App\Tests\Unit\Billing;

use App\Billing\Domain\Entity\BillingPlan;
use App\Billing\Domain\Entity\UserSubscription;
use App\Identity\Domain\Entity\User;
use PHPUnit\Framework\TestCase;

final class UserSubscriptionTest extends TestCase
{
    public function testCanApplyStripeEventUsesTimestampAndTypeRankOrdering(): void
    {
        $subscription = new UserSubscription(
            '018f6f0e-7c4b-7f44-9f6a-6a69341b7f38',
            new User(
                '018f6f0e-7c4b-7f44-9f6a-6a69341b7f39',
                'user@example.test',
                'hash',
                'Ada',
                'Lovelace',
                new \DateTimeImmutable('2026-05-14T10:00:00+00:00'),
            ),
            new BillingPlan(
                '018f6f0e-7c4b-7f44-9f6a-6a69341b7f40',
                'pro',
                'Pro',
                null,
                1200,
                'eur',
                'month',
                true,
                new \DateTimeImmutable('2026-05-14T10:00:00+00:00'),
            ),
            'active',
            new \DateTimeImmutable('2026-05-14T10:00:00+00:00'),
        );

        $stripeEventCreatedAt = new \DateTimeImmutable('2026-05-14T10:30:00+00:00');
        $subscription->sync(
            $subscription->getPlan(),
            'active',
            'cus_123',
            'sub_123',
            null,
            null,
            false,
            new \DateTimeImmutable('2026-05-14T10:31:00+00:00'),
            $stripeEventCreatedAt,
            200,
        );

        self::assertFalse($subscription->canApplyStripeEvent($stripeEventCreatedAt, 200));
        self::assertFalse($subscription->canApplyStripeEvent($stripeEventCreatedAt, 100));
        self::assertTrue($subscription->canApplyStripeEvent($stripeEventCreatedAt, 300));
        self::assertTrue($subscription->canApplyStripeEvent($stripeEventCreatedAt->modify('+1 second'), 0));
        self::assertFalse($subscription->canApplyStripeEvent($stripeEventCreatedAt->modify('-1 second'), 300));
        self::assertTrue($subscription->hasAmbiguousStripeEventOrder($stripeEventCreatedAt, 200));
        self::assertTrue($subscription->hasAmbiguousStripeEventOrder($stripeEventCreatedAt, 100));
        self::assertFalse($subscription->hasAmbiguousStripeEventOrder($stripeEventCreatedAt, 300));

        $subscription->sync(
            $subscription->getPlan(),
            'active',
            'cus_123',
            'sub_123',
            null,
            null,
            false,
            new \DateTimeImmutable('2026-05-14T10:32:00+00:00'),
            $stripeEventCreatedAt,
            100,
        );

        self::assertFalse($subscription->canApplyStripeEvent($stripeEventCreatedAt, 200));
    }
}
