<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\ValueObject\EventId;
use App\Domain\ValueObject\EventName;

/**
 * Domain Event - Represents that an Event was updated
 */
final readonly class EventUpdated
{
    private \DateTimeImmutable $occurredOn;

    public function __construct(
        private EventId $eventId,
        private EventName $eventName
    ) {
        $this->occurredOn = new \DateTimeImmutable();
    }

    public function getEventId(): EventId
    {
        return $this->eventId;
    }

    public function getEventName(): EventName
    {
        return $this->eventName;
    }

    public function getOccurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
