<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Domain\Entity\Event;
use App\Domain\Repository\EventRepositoryInterface;
use App\Domain\ValueObject\EventId;

/**
 * Handler for GetEventsQuery
 * Retrieves events for event sourcing / listing
 */
final readonly class GetEventsQueryHandler
{
    public function __construct(
        private EventRepositoryInterface $eventRepository
    ) {
    }

    /**
     * @return Event[]
     */
    public function __invoke(GetEventsQuery $query): array
    {
        $since = null;
        
        if ($query->since !== null) {
            $since = EventId::fromString($query->since);
        }
        
        return $this->eventRepository->findSince($since, $query->limit);
    }
}
