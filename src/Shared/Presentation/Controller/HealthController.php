<?php

declare(strict_types=1);

namespace App\Shared\Presentation\Controller;

use Doctrine\DBAL\Connection;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

final readonly class HealthController
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Route('/api/health', name: 'api_health', methods: ['GET'])]
    public function __invoke(): JsonResponse
    {
        return new JsonResponse([
            'status' => 'ok',
            'service' => 'starter_back',
        ]);
    }

    #[Route('/api/ready', name: 'api_ready', methods: ['GET'])]
    public function ready(): JsonResponse
    {
        try {
            $this->connection->executeQuery('SELECT 1')->fetchOne();
        } catch (\Throwable) {
            return new JsonResponse([
                'status' => 'unavailable',
                'service' => 'starter_back',
            ], 503);
        }

        return new JsonResponse([
            'status' => 'ok',
            'service' => 'starter_back',
        ]);
    }
}
