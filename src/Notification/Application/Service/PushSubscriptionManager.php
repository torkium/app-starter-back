<?php

declare(strict_types=1);

namespace App\Notification\Application\Service;

use App\Identity\Domain\Entity\User;
use App\Notification\Domain\Entity\PushSubscription;
use App\Shared\Application\Port\ClockInterface;
use App\Shared\Application\Port\TransactionManagerInterface;
use App\Shared\Infrastructure\Outbox\OutboxRecorder;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final readonly class PushSubscriptionManager
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private TransactionManagerInterface $transactionManager,
        private OutboxRecorder $outboxRecorder,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return array<string, mixed>|null
     */
    public function view(User $user): ?array
    {
        $subscription = $this->entityManager->getRepository(PushSubscription::class)->findOneBy(['user' => $user], ['updatedAt' => 'DESC']);

        return $subscription instanceof PushSubscription ? $subscription->toView() : null;
    }

    /**
     * @return array<string, mixed>
     */
    public function subscribe(User $user, string $endpoint, ?string $p256dh, ?string $auth, ?int $expirationTime): array
    {
        $subscription = $this->entityManager->getRepository(PushSubscription::class)->findOneBy(['endpoint' => $endpoint]);

        $this->transactionManager->run(function () use ($user, $endpoint, $p256dh, $auth, $expirationTime, &$subscription): void {
            if (!$subscription instanceof PushSubscription) {
                $subscription = new PushSubscription(
                    Uuid::v7()->toRfc4122(),
                    $user,
                    $endpoint,
                    $p256dh,
                    $auth,
                    $expirationTime,
                    $this->clock->now(),
                );
                $this->entityManager->persist($subscription);
            } elseif (!$subscription->belongsTo($user)) {
                $subscription->reassignTo($user, $p256dh, $auth, $expirationTime, $this->clock->now());
            } else {
                $subscription->touch($p256dh, $auth, $expirationTime, $this->clock->now());
            }

            $this->outboxRecorder->record('notification.push_subscription_upserted', 'default', [
                'userId' => $user->getId(),
                'endpoint' => $endpoint,
            ]);
        });

        return $subscription->toView();
    }

    /**
     * @return array<string, mixed>|null
     */
    public function unsubscribe(User $user, ?string $endpoint = null): ?array
    {
        $criteria = ['user' => $user];
        if (null !== $endpoint && '' !== trim($endpoint)) {
            $criteria['endpoint'] = $endpoint;
        }

        $subscription = $this->entityManager->getRepository(PushSubscription::class)->findOneBy($criteria, ['updatedAt' => 'DESC']);
        if (!$subscription instanceof PushSubscription) {
            return null;
        }

        $view = $subscription->toView();

        $this->transactionManager->run(function () use ($subscription, $user): void {
            $this->entityManager->remove($subscription);
            $this->outboxRecorder->record('notification.push_subscription_deleted', 'default', [
                'userId' => $user->getId(),
                'endpoint' => $subscription->getEndpoint(),
            ]);
        });

        return $view;
    }
}
