<?php

declare(strict_types=1);

namespace App\Identity\Presentation\Controller;

use App\Identity\Application\Service\IdentityManager;
use App\Identity\Domain\Entity\User;
use App\Shared\Application\Http\ApiProblemException;
use App\Shared\Application\Http\JsonRequestDecoder;
use App\Shared\Application\Http\RequestPayloadValidator;
use App\Shared\Application\Http\RequestRateLimiter;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

final class RequestEmailChangeController
{
    #[Route('/api/account/change-email/request', name: 'api_account_change_email_request', methods: ['POST'])]
    public function __invoke(
        Request $request,
        Security $security,
        IdentityManager $identityManager,
        JsonRequestDecoder $decoder,
        RequestPayloadValidator $validator,
        RequestRateLimiter $rateLimiter,
    ): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            throw ApiProblemException::unauthorized();
        }

        $payload = $decoder->decode($request);
        $validator->validate($payload, new Assert\Collection(
            fields: [
                'email' => [new Assert\NotBlank(), new Assert\Email(), new Assert\Length(max: 180)],
            ],
            allowExtraFields: false,
            allowMissingFields: false,
        ));
        $rateLimiter->consumeSensitiveAuthAction(sprintf('%s:%s', $user->getId(), $request->getClientIp() ?? 'unknown'));
        $identityManager->requestEmailChange($user, (string) $payload['email']);

        return new JsonResponse(['status' => 'email_change_requested']);
    }
}
