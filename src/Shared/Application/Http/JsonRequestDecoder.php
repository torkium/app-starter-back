<?php

declare(strict_types=1);

namespace App\Shared\Application\Http;

use Symfony\Component\HttpFoundation\Request;

final class JsonRequestDecoder
{
    private const MAX_JSON_BODY_BYTES = 262144;

    /**
     * @return array<string, mixed>
     */
    public function decode(Request $request): array
    {
        $content = trim($request->getContent());
        if ('' === $content) {
            return [];
        }

        if (strlen($content) > self::MAX_JSON_BODY_BYTES) {
            throw ApiProblemException::badRequest('JSON body is too large.');
        }

        try {
            $payload = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ApiProblemException::badRequest('Malformed JSON body.');
        }

        if (!is_array($payload)) {
            throw ApiProblemException::badRequest('JSON body must decode to an object.');
        }

        return $payload;
    }
}
