<?php

declare(strict_types=1);

namespace App\Application\Query;

use App\Domain\Entity\Event;
use App\Domain\Repository\EventRepositoryInterface;
use App\Domain\ValueObject\EventId;

final readonly class GetEventQueryHandler
{
    public function __construct(
        private EventRepositoryInterface $eventRepository
    ) {
    }

    public function __invoke(GetEventQuery $query): Event
    {
        return $this->eventRepository->findById(
            EventId::fromString($query->eventId)
        );
    }
}
