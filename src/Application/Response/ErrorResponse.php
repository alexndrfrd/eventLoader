<?php

declare(strict_types=1);

namespace App\Application\Response;

final readonly class ErrorResponse
{
    public function __construct(
        public string $error,
        public ?int $code = null,
        public ?array $details = null
    ) {
    }

    public static function fromException(\Throwable $exception): self
    {
        return new self(
            error: $exception->getMessage(),
            code: $exception->getCode() ?: null
        );
    }

    public static function fromMessage(string $message): self
    {
        return new self(error: $message);
    }

    public function toArray(): array
    {
        $data = ['error' => $this->error];

        if ($this->code !== null) {
            $data['code'] = $this->code;
        }

        if ($this->details !== null) {
            $data['details'] = $this->details;
        }

        return $data;
    }
}
