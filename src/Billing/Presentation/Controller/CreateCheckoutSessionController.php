<?php

declare(strict_types=1);

namespace App\Billing\Presentation\Controller;

use App\Billing\Application\Service\BillingManager;
use App\Identity\Domain\Entity\User;
use App\Shared\Application\Http\ApiProblemException;
use App\Shared\Application\Http\JsonRequestDecoder;
use App\Shared\Application\Http\RequestPayloadValidator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Constraints as Assert;

final class CreateCheckoutSessionController
{
    #[Route('/api/billing/checkout', name: 'api_billing_checkout_session', methods: ['POST'])]
    public function __invoke(
        Request $request,
        Security $security,
        BillingManager $billingManager,
        JsonRequestDecoder $decoder,
        RequestPayloadValidator $validator,
    ): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            throw ApiProblemException::unauthorized();
        }

        $payload = $decoder->decode($request);
        $validator->validate($payload, new Assert\Collection(
            fields: [
                'planCode' => [new Assert\NotBlank(), new Assert\Length(max: 80)],
                'successUrl' => [new Assert\NotBlank(), new Assert\Url()],
                'cancelUrl' => [new Assert\NotBlank(), new Assert\Url()],
            ],
            allowExtraFields: false,
            allowMissingFields: false,
        ));

        $successUrl = is_string($payload['successUrl'] ?? null) ? $payload['successUrl'] : 'http://localhost:3000/billing?status=success';
        $cancelUrl = is_string($payload['cancelUrl'] ?? null) ? $payload['cancelUrl'] : 'http://localhost:3000/billing?status=cancel';
        $planCode = (string) ($payload['planCode'] ?? '');

        return new JsonResponse($billingManager->createCheckoutSession($user, $planCode, $successUrl, $cancelUrl), 201);
    }
}
