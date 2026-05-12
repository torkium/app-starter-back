<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Outbox;

final readonly class OutboxPayloadProtector
{
    private const MARKER = '_encrypted';
    private const CIPHER = 'aes-256-gcm';

    public function __construct(
        private string $secret,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function protect(array $payload): array
    {
        $plaintext = json_encode($payload, JSON_THROW_ON_ERROR);
        $nonce = random_bytes(12);
        $tag = '';
        $ciphertext = openssl_encrypt($plaintext, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $nonce, $tag);
        if (false === $ciphertext) {
            throw new \RuntimeException('Unable to encrypt outbox payload.');
        }

        return [
            self::MARKER => true,
            'cipher' => self::CIPHER,
            'nonce' => base64_encode($nonce),
            'tag' => base64_encode($tag),
            'ciphertext' => base64_encode($ciphertext),
        ];
    }

    /**
     * @param array<string, mixed> $payload
     * @return array<string, mixed>
     */
    public function reveal(array $payload): array
    {
        if (!$this->isProtected($payload)) {
            return $payload;
        }

        foreach (['nonce', 'tag', 'ciphertext'] as $key) {
            if (!isset($payload[$key]) || !is_string($payload[$key])) {
                throw new \InvalidArgumentException('Encrypted outbox payload is malformed.');
            }
        }

        $nonce = base64_decode($payload['nonce'], true);
        $tag = base64_decode($payload['tag'], true);
        $ciphertext = base64_decode($payload['ciphertext'], true);
        if (false === $nonce || false === $tag || false === $ciphertext) {
            throw new \InvalidArgumentException('Encrypted outbox payload contains invalid base64 data.');
        }

        $plaintext = openssl_decrypt($ciphertext, self::CIPHER, $this->key(), OPENSSL_RAW_DATA, $nonce, $tag);
        if (false === $plaintext) {
            throw new \InvalidArgumentException('Encrypted outbox payload cannot be decrypted.');
        }

        $decoded = json_decode($plaintext, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($decoded)) {
            throw new \InvalidArgumentException('Encrypted outbox payload did not decode to an object.');
        }

        return $decoded;
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function isProtected(array $payload): bool
    {
        return ($payload[self::MARKER] ?? false) === true;
    }

    private function key(): string
    {
        if ('' === trim($this->secret)) {
            throw new \RuntimeException('APP_SECRET must be configured to protect outbox payloads.');
        }

        return hash('sha256', $this->secret, true);
    }
}
