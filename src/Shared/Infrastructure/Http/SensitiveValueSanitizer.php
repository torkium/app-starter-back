<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Http;

final class SensitiveValueSanitizer
{
    private const SENSITIVE_KEYS = [
        'authorization',
        'cookie',
        'email',
        'jwt',
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
            foreach (self::SENSITIVE_KEYS as $sensitiveKey) {
                if (str_contains($normalizedKey, $sensitiveKey)) {
                    $sanitized[$key] = '***';
                    continue 2;
                }
            }

            if (is_array($value)) {
                $sanitized[$key] = $this->sanitizeArray($value);
                continue;
            }

            if (is_string($value)) {
                $value = preg_replace('/([?&][^=&#]*(?:token|code|secret|signature|key|credential|apikey|api_key)[^=&#]*=)[^&#]+/i', '$1***', $value) ?? $value;
                $sanitized[$key] = mb_strlen($value) > 500 ? mb_substr($value, 0, 500).'...' : $value;
                continue;
            }

            $sanitized[$key] = $value;
        }

        return $sanitized;
    }
}
