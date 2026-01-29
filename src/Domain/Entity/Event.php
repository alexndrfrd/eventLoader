<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Event\EventCreated;
use App\Domain\Event\EventUpdated;
use App\Domain\ValueObject\EventDate;
use App\Domain\ValueObject\EventId;
use App\Domain\ValueObject\EventName;

final class Event
{
    private array $domainEvents = [];

    private function __construct(
        private EventId $id,
        private EventName $name,
        private EventDate $scheduledDate,
        private ?string $description = null,
        private ?EventDate $createdAt = null,
        private ?EventDate $updatedAt = null
    ) {
        $this->createdAt = $createdAt ?? EventDate::now();
        $this->updatedAt = $updatedAt ?? EventDate::now();
    }

    public static function create(
        EventId $id,
        EventName $name,
        EventDate $scheduledDate,
        ?string $description = null
    ): self {
        $event = new self(
            $id,
            $name,
            $scheduledDate,
            $description
        );

        $event->recordDomainEvent(new EventCreated(
            $event->id,
            $event->name,
            $event->scheduledDate
        ));

        return $event;
    }

    public static function reconstitute(
        EventId $id,
        EventName $name,
        EventDate $scheduledDate,
        ?string $description,
        EventDate $createdAt,
        EventDate $updatedAt
    ): self {
        return new self(
            $id,
            $name,
            $scheduledDate,
            $description,
            $createdAt,
            $updatedAt
        );
    }

    public function updateDetails(EventName $name, ?string $description = null): void
    {
        $this->name = $name;
        $this->description = $description;
        $this->updatedAt = EventDate::now();

        $this->recordDomainEvent(new EventUpdated($this->id, $this->name));
    }

    public function reschedule(EventDate $newDate): void
    {
        if ($newDate->equals($this->scheduledDate)) {
            return;
        }

        $this->scheduledDate = $newDate;
        $this->updatedAt = EventDate::now();

        $this->recordDomainEvent(new EventUpdated($this->id, $this->name));
    }

    public function isScheduledInFuture(): bool
    {
        return EventDate::now()->isBefore($this->scheduledDate);
    }

    // Getters
    public function getId(): EventId
    {
        return $this->id;
    }

    public function getName(): EventName
    {
        return $this->name;
    }

    public function getScheduledDate(): EventDate
    {
        return $this->scheduledDate;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function getCreatedAt(): EventDate
    {
        return $this->createdAt;
    }

    public function getUpdatedAt(): EventDate
    {
        return $this->updatedAt;
    }

    public function pullDomainEvents(): array
    {
        $events = $this->domainEvents;
        $this->domainEvents = [];
        return $events;
    }

    private function recordDomainEvent(object $event): void
    {
        $this->domainEvents[] = $event;
    }
}
