<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Stripe;

use App\Billing\Application\Port\StripeCheckoutGatewayInterface;
use App\Billing\Domain\Entity\BillingPlan;
use App\Identity\Domain\Entity\User;
use App\Shared\Application\Http\ApiProblemException;
use Stripe\Checkout\Session;
use Stripe\Event;
use Stripe\Exception\SignatureVerificationException;
use Stripe\StripeClient;
use Stripe\Webhook;

final readonly class StripeCheckoutGateway implements StripeCheckoutGatewayInterface
{
    public function __construct(
        private ?string $secretKey,
        private ?string $webhookSecret,
    ) {
    }

    public function createCheckoutSession(User $user, BillingPlan $plan, string $successUrl, string $cancelUrl): array
    {
        if ('' === trim($this->secretKey ?? '')) {
            return [
                'mode' => 'stub',
                'url' => null,
                'message' => 'STRIPE_SECRET_KEY manquant. Branchez votre compte Stripe pour activer le checkout.',
                'planCode' => $plan->getCode(),
            ];
        }

        $client = new StripeClient($this->secretKey ?? '');
        $mode = 'one_time' === $plan->getIntervalUnit() ? 'payment' : 'subscription';
        $lineItems = null !== $plan->getStripePriceId()
            ? [[
                'quantity' => 1,
                'price' => $plan->getStripePriceId(),
            ]]
            : [[
                'quantity' => 1,
                'price_data' => [
                    'currency' => $plan->getCurrency(),
                    'unit_amount' => $plan->getAmountMinor(),
                    'recurring' => 'payment' === $mode ? null : ['interval' => $plan->getIntervalUnit()],
                    'product_data' => [
                        'name' => $plan->getName(),
                        'description' => $plan->getDescription(),
                    ],
                ],
            ]];

        /** @var Session $session */
        $session = $client->checkout->sessions->create([
            'mode' => $mode,
            'customer_email' => $user->getEmail(),
            'success_url' => $successUrl,
            'cancel_url' => $cancelUrl,
            'line_items' => $lineItems,
            'metadata' => [
                'user_id' => $user->getId(),
                'plan_code' => $plan->getCode(),
            ],
            'subscription_data' => 'subscription' === $mode ? [
                'metadata' => [
                    'user_id' => $user->getId(),
                    'plan_code' => $plan->getCode(),
                ],
            ] : null,
        ]);

        return [
            'mode' => 'live',
            'id' => $session->id,
            'url' => $session->url,
        ];
    }

    public function parseWebhook(string $payload, ?string $signature): array
    {
        if ('' === trim($this->secretKey ?? '')) {
            throw ApiProblemException::forbidden('Stripe webhook is disabled until Stripe is configured.');
        }

        if ('' === trim($this->webhookSecret ?? '')) {
            throw ApiProblemException::badRequest('Stripe webhook secret is required when Stripe is enabled.');
        }

        if (null === $signature || '' === trim($signature)) {
            throw ApiProblemException::badRequest('Stripe signature header is required.');
        }

        try {
            /** @var Event $event */
            $event = Webhook::constructEvent($payload, $signature, $this->webhookSecret ?? '');
        } catch (SignatureVerificationException) {
            throw ApiProblemException::badRequest('Invalid Stripe signature.');
        } catch (\JsonException|\UnexpectedValueException) {
            throw ApiProblemException::badRequest('Invalid Stripe webhook payload.');
        }

        return $event->toArray();
    }
}
