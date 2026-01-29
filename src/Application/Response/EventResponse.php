<?php

declare(strict_types=1);

namespace App\Application\Response;

use App\Domain\Entity\Event;

final readonly class EventResponse
{
    public function __construct(
        public string $id,
        public string $name,
        public string $scheduledDate,
        public ?string $description,
        public bool $isUpcoming,
        public string $createdAt,
        public string $updatedAt
    ) {
    }

    public static function fromEntity(Event $event): self
    {
        return new self(
            id: $event->getId()->toString(),
            name: $event->getName()->toString(),
            scheduledDate: $event->getScheduledDate()->toString(),
            description: $event->getDescription(),
            isUpcoming: $event->isScheduledInFuture(),
            createdAt: $event->getCreatedAt()->toString(),
            updatedAt: $event->getUpdatedAt()->toString()
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'scheduled_date' => $this->scheduledDate,
            'description' => $this->description,
            'is_upcoming' => $this->isUpcoming,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
