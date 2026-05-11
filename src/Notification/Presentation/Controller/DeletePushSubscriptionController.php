<?php

declare(strict_types=1);

namespace App\Notification\Presentation\Controller;

use App\Identity\Domain\Entity\User;
use App\Notification\Application\Service\PushSubscriptionManager;
use App\Shared\Application\Http\ApiProblemException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class DeletePushSubscriptionController
{
    #[Route('/api/notifications/push/subscriptions', name: 'api_notification_push_subscription_delete', methods: ['DELETE'])]
    public function __invoke(Request $request, Security $security, PushSubscriptionManager $manager): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            throw ApiProblemException::unauthorized();
        }

        return new JsonResponse($manager->unsubscribe($user, $request->query->get('endpoint')));
    }
}
