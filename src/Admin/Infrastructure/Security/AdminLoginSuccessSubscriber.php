<?php

declare(strict_types=1);

namespace App\Admin\Infrastructure\Security;

use App\Admin\Domain\Entity\AdminUser;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

#[AsEventListener(event: LoginSuccessEvent::class)]
final class AdminLoginSuccessSubscriber
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function __invoke(LoginSuccessEvent $event): void
    {
        $user = $event->getAuthenticatedToken()->getUser();
        if (!$user instanceof AdminUser) {
            return;
        }

        $user->markLoggedIn(new \DateTimeImmutable());
        $this->entityManager->flush();
    }
}
