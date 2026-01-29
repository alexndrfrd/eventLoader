<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use App\Application\Query\GetEventsQuery;
use App\Application\Query\GetEventsQueryHandler;
use App\Domain\Entity\Event;
use App\Domain\Service\EventSourceInterface;
use App\Domain\ValueObject\EventId;

/**
 * HTTP Event Source Implementation
 * 
 * Fetches events from HTTP API endpoint
 * In this case, it's our own /api/events endpoint
 */
final class HttpEventSource implements EventSourceInterface
{
    public function __construct(
        private readonly GetEventsQueryHandler $queryHandler,
        private readonly string $name
    ) {
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function fetchEvents(?EventId $since = null, int $limit = 1000): array
    {
        $query = new GetEventsQuery(
            since: $since?->toString(),
            limit: min($limit, 1000) // Enforce max 1000
        );

        return ($this->queryHandler)($query);
    }

    public function isAvailable(): bool
    {
        /* In real implementation, would check HTTP connection
        for now always available */
        return true;
    }
}
