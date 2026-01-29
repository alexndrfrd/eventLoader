<?php

declare(strict_types=1);

namespace App\Domain\Event;

use App\Domain\ValueObject\EventDate;
use App\Domain\ValueObject\EventId;
use App\Domain\ValueObject\EventName;

/**
 * Domain Event - Represents that an Event was created
 */
final readonly class EventCreated
{
    private \DateTimeImmutable $occurredOn;

    public function __construct(
        private EventId $eventId,
        private EventName $eventName,
        private EventDate $scheduledDate
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

    public function getScheduledDate(): EventDate
    {
        return $this->scheduledDate;
    }

    public function getOccurredOn(): \DateTimeImmutable
    {
        return $this->occurredOn;
    }
}
