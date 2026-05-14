<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use App\Tests\Factory\UserFactory;
use Symfony\Component\HttpFoundation\Response;

final class MediaFlowTest extends ApiTestCase
{
    public function testUploadCompleteListAndReadMediaThroughControlledEndpoint(): void
    {
        $user = UserFactory::createOne();
        $tokens = $this->login($user, deviceName: 'Test Device');

        $binary = base64_decode(
            'R0lGODlhAQABAIAAAAAAAP///ywAAAAAAQABAAACAUwAOw==',
            true,
        );
        self::assertIsString($binary);
        $checksum = hash('sha256', $binary);

        $intent = $this->jsonRequest('POST', '/api/media/uploads', [
            'filename' => 'avatar.gif',
            'mimeType' => 'image/gif',
            'size' => strlen($binary),
            'purpose' => 'avatar',
        ], $this->authHeaders($tokens['access_token']));

        self::assertSame(Response::HTTP_CREATED, $intent->getStatusCode(), $intent->getContent());
        $intentPayload = $this->decodeJson($intent);

        $assetId = $intentPayload['asset']['id'];
        $uploadHeaders = array_merge(
            $this->authHeaders($tokens['access_token']),
            $intentPayload['upload']['headers'],
            ['Content-Type' => 'image/gif']
        );

        $this->client->request(
            'PUT',
            $intentPayload['upload']['uploadUrl'],
            [],
            [],
            array_merge(['HTTP_ACCEPT' => 'application/json'], $this->normalizeServerHeaders($uploadHeaders)),
            $binary,
        );
        $upload = $this->client->getResponse();

        self::assertSame(Response::HTTP_OK, $upload->getStatusCode(), $upload->getContent());

        $complete = $this->jsonRequest('POST', '/api/media/uploads/complete', [
            'assetId' => $assetId,
            'checksum' => $checksum,
        ], $this->authHeaders($tokens['access_token']));

        self::assertSame(Response::HTTP_OK, $complete->getStatusCode(), $complete->getContent());
        $completed = $this->decodeJson($complete);

        self::assertSame('/api/media/assets/'.$assetId.'/content', $completed['previewUrl']);

        $list = $this->jsonRequest('GET', '/api/media/assets', null, $this->authHeaders($tokens['access_token']));
        self::assertSame(Response::HTTP_OK, $list->getStatusCode(), $list->getContent());
        $assets = $this->decodeJson($list)['items'];
        self::assertCount(1, $assets);
        self::assertArrayNotHasKey('objectKey', $assets[0]);

        $content = $this->jsonRequest('GET', '/api/media/assets/'.$assetId.'/content', null, $this->authHeaders($tokens['access_token']));
        self::assertSame(Response::HTTP_OK, $content->getStatusCode());
        self::assertSame((string) strlen($binary), $content->headers->get('Content-Length'));
        self::assertSame('image/gif', $content->headers->get('Content-Type'));
    }

    /**
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    private function normalizeServerHeaders(array $headers): array
    {
        $server = [];

        foreach ($headers as $name => $value) {
            $normalized = strtoupper(str_replace('-', '_', $name));
            if ('CONTENT_TYPE' !== $normalized && !str_starts_with($normalized, 'HTTP_')) {
                $normalized = 'HTTP_'.$normalized;
            }
            $server[$normalized] = $value;
        }

        return $server;
    }
}
