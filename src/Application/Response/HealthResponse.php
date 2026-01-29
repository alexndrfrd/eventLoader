<?php

declare(strict_types=1);

namespace App\Application\Response;

final readonly class HealthResponse
{
    public function __construct(
        public string $status,
        public string $service,
        public string $timestamp
    ) {
    }

    public static function ok(string $serviceName): self
    {
        return new self(
            status: 'ok',
            service: $serviceName,
            timestamp: (new \DateTimeImmutable())->format('c')
        );
    }

    public function toArray(): array
    {
        return [
            'status' => $this->status,
            'service' => $this->service,
            'timestamp' => $this->timestamp,
        ];
    }
}
