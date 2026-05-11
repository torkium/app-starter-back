<?php

declare(strict_types=1);

namespace App\Billing\Application\Service;

use App\Billing\Application\Port\StripeCheckoutGatewayInterface;
use App\Billing\Domain\Entity\BillingEvent;
use App\Billing\Domain\Entity\BillingPlan;
use App\Billing\Domain\Entity\UserSubscription;
use App\Identity\Domain\Entity\User;
use App\Shared\Application\Http\ApiProblemException;
use App\Shared\Application\Port\ClockInterface;
use App\Shared\Application\Port\TransactionManagerInterface;
use App\Shared\Infrastructure\Outbox\OutboxRecorder;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class BillingManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private StripeCheckoutGatewayInterface $stripeGateway,
        private TransactionManagerInterface $transactionManager,
        private OutboxRecorder $outboxRecorder,
        private ClockInterface $clock,
        private string $frontBaseUrl,
    ) {
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function listPlans(): array
    {
        $plans = $this->entityManager->getRepository(BillingPlan::class)->findBy(['active' => true], ['amountMinor' => 'ASC']);

        return array_map(
            static fn (BillingPlan $plan): array => [
                'id' => $plan->getId(),
                'code' => $plan->getCode(),
                'name' => $plan->getName(),
                'description' => $plan->getDescription(),
                'amount' => $plan->getAmountMinor(),
                'currency' => strtoupper($plan->getCurrency()),
                'period' => $plan->getIntervalUnit(),
                'features' => [],
                'highlight' => false,
            ],
            array_values(array_filter($plans, static fn (mixed $plan): bool => $plan instanceof BillingPlan)),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function createCheckoutSession(User $user, string $planCode, string $successUrl, string $cancelUrl): array
    {
        $this->assertAllowedRedirectUrl($successUrl);
        $this->assertAllowedRedirectUrl($cancelUrl);

        $plan = $this->entityManager->getRepository(BillingPlan::class)->findOneBy(['code' => $planCode, 'active' => true]);
        if (!$plan instanceof BillingPlan) {
            throw ApiProblemException::unprocessable('Plan de facturation introuvable.');
        }

        $session = $this->stripeGateway->createCheckoutSession($user, $plan, $successUrl, $cancelUrl);

        return [
            'checkoutUrl' => $session['url'] ?? null,
            'clientSecret' => $session['client_secret'] ?? null,
            'sessionId' => $session['id'] ?? null,
        ];
    }

    /**
     * @return array<string, mixed>|null
     */
    public function currentSubscription(User $user): ?array
    {
        $subscription = $this->entityManager->getRepository(UserSubscription::class)->findOneBy(['user' => $user]);

        return $subscription instanceof UserSubscription ? $subscription->toFrontView() : null;
    }

    /**
     * @return list<array<string, mixed>>
     */
    public function history(User $user): array
    {
        $events = $this->entityManager->getRepository(BillingEvent::class)->findBy(['user' => $user], ['occurredAt' => 'DESC']);

        return array_map(
            static fn (BillingEvent $event): array => $event->toView(),
            array_values(array_filter($events, static fn (mixed $event): bool => $event instanceof BillingEvent)),
        );
    }

    public function handleWebhook(string $payload, ?string $signature): void
    {
        $event = $this->stripeGateway->parseWebhook($payload, $signature);
        $eventId = (string) ($event['id'] ?? '');
        $eventType = (string) ($event['type'] ?? 'unknown');
        $data = is_array($event['data']['object'] ?? null) ? $event['data']['object'] : [];

        if ('' === $eventId) {
            throw ApiProblemException::badRequest('Stripe webhook event id is missing.');
        }

        $existing = $this->entityManager->getRepository(BillingEvent::class)->findOneBy(['externalId' => $eventId]);
        if ($existing instanceof BillingEvent) {
            return;
        }

        $occurredAt = isset($event['created']) && is_numeric($event['created'])
            ? (new \DateTimeImmutable())->setTimestamp((int) $event['created'])
            : $this->clock->now();

        try {
            $this->transactionManager->run(function () use ($eventId, $eventType, $event, $data, $occurredAt): void {
                $user = $this->resolveUserFromEventData($data);

                $billingEvent = new BillingEvent(
                    Uuid::v7()->toRfc4122(),
                    $user,
                    $eventId,
                    $eventType,
                    $event,
                    $occurredAt,
                    $this->clock->now(),
                );
                $this->entityManager->persist($billingEvent);

                if (str_starts_with($eventType, 'customer.subscription.')) {
                    $this->syncSubscriptionFromStripeData($data, $user);
                }

                if ('checkout.session.completed' === $eventType) {
                    $this->syncSubscriptionFromCheckoutData($data, $user);
                }

                $this->outboxRecorder->record('billing.webhook_received', 'default', [
                    'eventId' => $eventId,
                    'type' => $eventType,
                    'userId' => $user?->getId(),
                ]);
            });
        } catch (UniqueConstraintViolationException) {
            return;
        }
    }

    private function syncSubscriptionFromCheckoutData(array $data, ?User $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        $planCode = is_array($data['metadata'] ?? null) ? (string) ($data['metadata']['plan_code'] ?? '') : '';
        if ('' === $planCode) {
            return;
        }

        $plan = $this->entityManager->getRepository(BillingPlan::class)->findOneBy(['code' => $planCode]);
        if (!$plan instanceof BillingPlan) {
            return;
        }

        $subscription = $this->entityManager->getRepository(UserSubscription::class)->findOneBy(['user' => $user]);
        if (!$subscription instanceof UserSubscription) {
            $subscription = new UserSubscription(Uuid::v7()->toRfc4122(), $user, $plan, 'checkout_completed', $this->clock->now());
            $this->entityManager->persist($subscription);
        }

        $subscription->sync(
            $plan,
            'checkout_completed',
            is_string($data['customer'] ?? null) ? $data['customer'] : null,
            is_string($data['subscription'] ?? null) ? $data['subscription'] : null,
            null,
            null,
            false,
            $this->clock->now(),
        );

        $this->outboxRecorder->record('billing.subscription_updated', 'default', [
            'userId' => $user->getId(),
            'planCode' => $plan->getCode(),
            'status' => 'checkout_completed',
        ]);
    }

    private function syncSubscriptionFromStripeData(array $data, ?User $user): void
    {
        if (!$user instanceof User) {
            return;
        }

        $planCode = is_array($data['metadata'] ?? null) ? (string) ($data['metadata']['plan_code'] ?? '') : '';
        if ('' === $planCode) {
            return;
        }

        $plan = $this->entityManager->getRepository(BillingPlan::class)->findOneBy(['code' => $planCode]);
        if (!$plan instanceof BillingPlan) {
            return;
        }

        $subscription = $this->entityManager->getRepository(UserSubscription::class)->findOneBy(['user' => $user]);
        if (!$subscription instanceof UserSubscription) {
            $subscription = new UserSubscription(Uuid::v7()->toRfc4122(), $user, $plan, 'pending', $this->clock->now());
            $this->entityManager->persist($subscription);
        }

        $start = isset($data['current_period_start']) && is_numeric($data['current_period_start'])
            ? (new \DateTimeImmutable())->setTimestamp((int) $data['current_period_start'])
            : null;
        $end = isset($data['current_period_end']) && is_numeric($data['current_period_end'])
            ? (new \DateTimeImmutable())->setTimestamp((int) $data['current_period_end'])
            : null;

        $subscription->sync(
            $plan,
            (string) ($data['status'] ?? 'unknown'),
            is_string($data['customer'] ?? null) ? $data['customer'] : null,
            is_string($data['id'] ?? null) ? $data['id'] : null,
            $start,
            $end,
            (bool) ($data['cancel_at_period_end'] ?? false),
            $this->clock->now(),
        );

        $this->outboxRecorder->record('billing.subscription_updated', 'default', [
            'userId' => $user->getId(),
            'planCode' => $plan->getCode(),
            'status' => (string) ($data['status'] ?? 'unknown'),
        ]);
    }

    private function resolveUserFromEventData(array $data): ?User
    {
        $metadata = is_array($data['metadata'] ?? null) ? $data['metadata'] : [];
        $userId = is_string($metadata['user_id'] ?? null) ? $metadata['user_id'] : null;

        if (null === $userId && isset($data['customer_email']) && is_string($data['customer_email'])) {
            return $this->entityManager->getRepository(User::class)->findOneBy(['email' => strtolower($data['customer_email'])]);
        }

        return null !== $userId
            ? $this->entityManager->getRepository(User::class)->find($userId)
            : null;
    }

    private function assertAllowedRedirectUrl(string $url): void
    {
        $candidate = parse_url($url);
        $frontBase = parse_url($this->frontBaseUrl);

        if (!is_array($candidate) || !is_array($frontBase)) {
            throw ApiProblemException::unprocessable('URL de redirection invalide.');
        }

        $sameHost = ($candidate['host'] ?? null) === ($frontBase['host'] ?? null);
        $sameScheme = ($candidate['scheme'] ?? null) === ($frontBase['scheme'] ?? null);
        $samePort = ($candidate['port'] ?? null) === ($frontBase['port'] ?? null);

        if (!$sameHost || !$sameScheme || !$samePort) {
            throw ApiProblemException::unprocessable('Les URLs de retour Stripe doivent pointer vers le front configuré.');
        }
    }
}
