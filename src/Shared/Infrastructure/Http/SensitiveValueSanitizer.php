<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

final class SensitiveValueSanitizer
{
    private const SENSITIVE_KEYS = [
        'authorization',
        'cookie',
        'password',
        'passwordhash',
        'token',
        'refreshtoken',
        'secret',
        'signature',
        'apikey',
    ];

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    public function sanitizeArray(array $data): array
    {
        $sanitized = [];

        foreach ($data as $key => $value) {
            $normalizedKey = strtolower(str_replace(['-', '_'], '', (string) $key));
            if (in_array($normalizedKey, self::SENSITIVE_KEYS, true)) {
                $sanitized[$key] = '***';
                continue;
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
                continue;
            }

            $sanitized[$key] = is_string($value) && mb_strlen($value) > 500
                ? mb_substr($value, 0, 500).'...'
                : $value;
        }

        return $sanitized;
    }
}
