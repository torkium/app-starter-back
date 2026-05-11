<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Symfony\Component\HttpFoundation\Response;

final class HealthControllerTest extends ApiTestCase
{
    public function testHealthEndpointReturnsOkPayload(): void
    {
        $response = $this->jsonRequest('GET', '/api/health');

        self::assertSame(Response::HTTP_OK, $response->getStatusCode());
        self::assertSame([
            'status' => 'ok',
            'service' => 'starter_back',
        ], $this->decodeJson($response));
    }
}
