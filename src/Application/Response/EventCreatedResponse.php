<?php

declare(strict_types=1);

namespace App\Application\Response;

use App\Domain\ValueObject\EventId;

final readonly class EventCreatedResponse
{
    public function __construct(
        public string $id,
        public string $message
    ) {
    }

    public static function fromEventId(EventId $eventId): self
    {
        return new self(
            id: $eventId->toString(),
            message: 'Event created successfully'
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'message' => $this->message,
        ];
    }
}
