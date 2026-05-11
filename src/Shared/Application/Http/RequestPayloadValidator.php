<?php

declare(strict_types=1);

namespace App\Shared\Application\Http;

use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final readonly class RequestPayloadValidator
{
    public function __construct(
        private ValidatorInterface $validator,
    ) {
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function validate(array $payload, Constraint $constraint): void
    {
        $violations = $this->validator->validate($payload, $constraint);
        if (0 === count($violations)) {
            return;
        }

        $errors = [];

        foreach ($violations as $violation) {
            $path = trim((string) $violation->getPropertyPath(), '[]');
            $key = '' !== $path ? $path : '_';
            $errors[$key] ??= [];
            $errors[$key][] = $violation->getMessage();
        }

        throw new ValidationProblemException($errors);
    }
}
