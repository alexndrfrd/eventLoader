<?php

declare(strict_types=1);

namespace App\Application\Response;

final readonly class ValidationErrorResponse
{
    /**
     * @param array<string, string[]> $errors
     */
    public function __construct(
        public string $message,
        public array $errors
    ) {
    }

    /**
     * @param array<string, string[]> $errors
     */
    public static function fromErrors(array $errors): self
    {
        return new self(
            message: 'Validation failed',
            errors: $errors
        );
    }

    public function toArray(): array
    {
        return [
            'message' => $this->message,
            'errors' => $this->errors,
        ];
    }
}
