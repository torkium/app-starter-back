<?php

declare(strict_types=1);

namespace App\Billing\Presentation\Controller;

use App\Billing\Application\Service\BillingManager;
use App\Identity\Domain\Entity\User;
use App\Shared\Application\Http\ApiProblemException;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final class ListBillingHistoryController
{
    #[Route('/api/billing/history', name: 'api_billing_history', methods: ['GET'])]
    public function __invoke(Security $security, BillingManager $billingManager): JsonResponse
    {
        $user = $security->getUser();
        if (!$user instanceof User) {
            throw ApiProblemException::unauthorized();
        }

        return new JsonResponse($billingManager->history($user));
    }
}
