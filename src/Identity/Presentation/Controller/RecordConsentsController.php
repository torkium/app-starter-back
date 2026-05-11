<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controller;

use App\Identity\Application\Service\IdentityManager;
use App\Identity\Domain\Entity\User;
use App\Shared\Application\Http\ApiProblemException;
use App\Shared\Application\Http\JsonRequestDecoder;
use App\Shared\Application\Http\RequestPayloadValidator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

final class RecordConsentsController
{
    #[Route('/api/account/consents', name: 'api_account_consents_record', methods: ['POST'])]
    public function __invoke(
        Request $request,
        Security $security,
        IdentityManager $identityManager,
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
                'documentIds' => [new Assert\NotBlank(), new Assert\Type('array'), new Assert\Count(min: 1)],
            ],
            allowExtraFields: false,
            allowMissingFields: false,
        ));

        $documentIds = array_values(array_filter(
            is_array($payload['documentIds']) ? $payload['documentIds'] : [],
            static fn (mixed $value): bool => is_string($value) && '' !== trim($value),
        ));

        $identityManager->recordConsents(
            $user,
            $documentIds,
            $request->getClientIp(),
            $request->headers->get('User-Agent'),
        );

        return new JsonResponse(['status' => 'accepted']);
    }
}
