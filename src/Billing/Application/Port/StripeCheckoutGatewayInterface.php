<?php

declare(strict_types=1);

namespace App\Billing\Application\Port;

use App\Billing\Domain\Entity\BillingPlan;
use App\Identity\Domain\Entity\User;

interface StripeCheckoutGatewayInterface
{
    /**
     * @return array<string, mixed>
     */
    public function createCheckoutSession(User $user, BillingPlan $plan, string $successUrl, string $cancelUrl): array;

    /**
     * @return array<string, mixed>
     */
    public function parseWebhook(string $payload, ?string $signature): array;

    /**
     * @return array<string, mixed>|null
     */
    public function retrieveSubscription(string $subscriptionId): ?array;
}
