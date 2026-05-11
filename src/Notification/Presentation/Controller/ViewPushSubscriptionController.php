<?php

declare(strict_types=1);

namespace App\Notification\Presentation\Controller;

use App\Identity\Domain\Entity\User;
use App\Notification\Application\Service\PushSubscriptionManager;
use App\Shared\Application\Http\ApiProblemException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ViewPushSubscriptionController
{
    #[Route('/api/notifications/push/subscriptions', name: 'api_notification_push_subscription_view', methods: ['GET'])]
    public function __invoke(Security $security, PushSubscriptionManager $manager): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            throw ApiProblemException::unauthorized();
        }

        return new JsonResponse($manager->view($user));
    }
}
