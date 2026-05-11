<?php

declare(strict_types=1);

namespace App\Billing\Presentation\Controller;

use App\Billing\Application\Service\BillingManager;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ListBillingPlansController
{
    #[Route('/api/billing/plans', name: 'api_billing_plans', methods: ['GET'])]
    public function __invoke(BillingManager $billingManager): JsonResponse
    {
        return new JsonResponse($billingManager->listPlans());
    }
}
