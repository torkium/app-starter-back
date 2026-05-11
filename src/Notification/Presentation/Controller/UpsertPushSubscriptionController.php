<?php

declare(strict_types=1);

namespace App\Notification\Presentation\Controller;

use App\Identity\Domain\Entity\User;
use App\Notification\Application\Service\PushSubscriptionManager;
use App\Shared\Application\Http\ApiProblemException;
use App\Shared\Application\Http\JsonRequestDecoder;
use App\Shared\Application\Http\RequestPayloadValidator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

final class UpsertPushSubscriptionController
{
    #[Route('/api/notifications/push/subscriptions', name: 'api_notification_push_subscription_upsert', methods: ['POST'])]
    public function __invoke(
        Request $request,
        Security $security,
        PushSubscriptionManager $manager,
        JsonRequestDecoder $decoder,
        RequestPayloadValidator $validator,
    ): JsonResponse {
        $user = $security->getUser();
        if (!$user instanceof User) {
            throw ApiProblemException::unauthorized();
        }

        $payload = $decoder->decode($request);
        $validator->validate($payload, new Assert\Collection(
            fields: [
                'endpoint' => [new Assert\NotBlank(), new Assert\Length(max: 500), new Assert\Url()],
                'expirationTime' => new Assert\Optional([new Assert\Type('numeric')]),
                'keys' => new Assert\Optional([
                    new Assert\Collection(
                        fields: [
                            'p256dh' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 255)]),
                            'auth' => new Assert\Optional([new Assert\Type('string'), new Assert\Length(max: 255)]),
                        ],
                        allowExtraFields: false,
                        allowMissingFields: true,
                    ),
                ]),
            ],
            allowExtraFields: false,
            allowMissingFields: false,
        ));

        $keys = is_array($payload['keys'] ?? null) ? $payload['keys'] : [];

        return new JsonResponse($manager->subscribe(
            $user,
            (string) $payload['endpoint'],
            is_string($keys['p256dh'] ?? null) ? $keys['p256dh'] : null,
            is_string($keys['auth'] ?? null) ? $keys['auth'] : null,
            isset($payload['expirationTime']) ? (int) $payload['expirationTime'] : null,
        ));
    }
}
