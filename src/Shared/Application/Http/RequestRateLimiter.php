<?php

declare(strict_types=1);

namespace App\Shared\Application\Http;

use Symfony\Component\RateLimiter\RateLimiterFactory;

final readonly class RequestRateLimiter
{
    public function __construct(
        private RateLimiterFactory $authLoginLimiter,
        private RateLimiterFactory $authRegisterLimiter,
        private RateLimiterFactory $authForgotPasswordLimiter,
        private RateLimiterFactory $authSensitiveLimiter,
        private RateLimiterFactory $webhookLimiter,
        private RateLimiterFactory $mediaUploadLimiter,
    ) {
    }

    public function consumeAuthLogin(string $key): void
    {
        $this->consume($this->authLoginLimiter, $key);
    }

    public function consumeAuthRegister(string $key): void
    {
        $this->consume($this->authRegisterLimiter, $key);
    }

    public function consumeForgotPassword(string $key): void
    {
        $this->consume($this->authForgotPasswordLimiter, $key);
    }

    public function consumeSensitiveAuthAction(string $key): void
    {
        $this->consume($this->authSensitiveLimiter, $key);
    }

    public function consumeWebhook(string $key): void
    {
        $this->consume($this->webhookLimiter, $key);
    }

    public function consumeMediaUpload(string $key): void
    {
        $this->consume($this->mediaUploadLimiter, $key);
    }

    private function consume(RateLimiterFactory $factory, string $key): void
    {
        $limit = $factory->create($key)->consume();
        if ($limit->isAccepted()) {
            return;
        }

        $retryAfter = $limit->getRetryAfter();
        $retryAfterSeconds = null;
        if ($retryAfter instanceof \DateTimeInterface) {
            $retryAfterSeconds = max(1, $retryAfter->getTimestamp() - time());
        }

        throw ApiProblemException::tooManyRequests(retryAfter: $retryAfterSeconds);
    }
}
