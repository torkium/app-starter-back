<?php

declare(strict_types=1);

namespace App\Shared\Application\Http;

final class ValidationProblemException extends ApiProblemException
{
    /**
     * @param array<string, array<int, string>> $violations
     */
    public function __construct(array $violations)
    {
        parent::__construct(
            422,
            'Request validation failed.',
            'Validation Failed',
            extra: ['violations' => $violations],
        );
    }
}
