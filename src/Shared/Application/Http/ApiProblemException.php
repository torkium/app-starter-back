<?php

declare(strict_types=1);

namespace App\Shared\Application\Http;

use Symfony\Component\HttpKernel\Exception\HttpException;

class ApiProblemException extends HttpException
{
    /**
     * @param array<string, mixed> $extra
     */
    public function __construct(
        int $statusCode,
        string $detail,
        private readonly string $title = 'Request error',
        private readonly string $type = 'about:blank',
        private readonly array $extra = [],
        ?\Throwable $previous = null,
        array $headers = [],
    ) {
        parent::__construct($statusCode, $detail, $previous, $headers);
    }

    public static function badRequest(string $detail, array $extra = []): self
    {
        return new self(400, $detail, 'Bad Request', extra: $extra);
    }

    public static function unauthorized(string $detail = 'Authentication required.'): self
    {
        return new self(401, $detail, 'Unauthorized');
    }

    public static function forbidden(string $detail = 'Access denied.'): self
    {
        return new self(403, $detail, 'Forbidden');
    }

    public static function notFound(string $detail = 'Resource not found.'): self
    {
        return new self(404, $detail, 'Not Found');
    }

    public static function unprocessable(string $detail, array $extra = []): self
    {
        return new self(422, $detail, 'Unprocessable Entity', extra: $extra);
    }

    public static function tooManyRequests(string $detail = 'Rate limit exceeded.', ?int $retryAfter = null): self
    {
        $extra = [];
        $headers = [];

        if (null !== $retryAfter && $retryAfter > 0) {
            $extra['retry_after'] = $retryAfter;
            $headers['Retry-After'] = (string) $retryAfter;
        }

        return new self(429, $detail, 'Too Many Requests', extra: $extra, headers: $headers);
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getType(): string
    {
        return $this->type;
    }

    /**
     * @return array<string, mixed>
     */
    public function getExtra(): array
    {
        return $this->extra;
    }
}
