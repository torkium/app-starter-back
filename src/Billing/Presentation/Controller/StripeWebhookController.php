<?php

declare(strict_types=1);

namespace App\Billing\Presentation\Controller;

use App\Billing\Application\Service\BillingManager;
use App\Shared\Application\Http\RequestRateLimiter;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

final class StripeWebhookController
{
    #[Route('/api/stripe/webhook', name: 'api_stripe_webhook', methods: ['POST'])]
    public function __invoke(Request $request, BillingManager $billingManager, RequestRateLimiter $rateLimiter): JsonResponse
    {
        $rateLimiter->consumeWebhook($request->getClientIp() ?? 'unknown');
        $billingManager->handleWebhook($request->getContent(), $request->headers->get('Stripe-Signature'));

        return new JsonResponse(['status' => 'accepted']);
    }
}
