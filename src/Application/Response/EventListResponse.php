<?php

declare(strict_types=1);

namespace App\Application\Response;

use App\Domain\Entity\Event;

final readonly class EventListResponse
{
    /**
     * @param EventResponse[] $events
     */
    public function __construct(
        public array $events,
        public string $source,
        public int $count,
        public ?string $lastId,
        public bool $hasMore
    ) {
    }

    /**
     * @param Event[] $events
     */
    public static function fromEvents(
        array $events,
        string $sourceName,
        int $requestedLimit
    ): self {
        $eventResponses = array_map(
            fn (Event $event): EventResponse => EventResponse::fromEntity($event),
            $events
        );
        
        $count = count($eventResponses);
        $lastId = $count > 0 ? $eventResponses[$count - 1]->id : null;
        
        /* hasMore = true if we got exactly the limit (might be more) */
        $hasMore = $count === $requestedLimit;
        
        return new self(
            events: $eventResponses,
            source: $sourceName,
            count: $count,
            lastId: $lastId,
            hasMore: $hasMore
        );
    }

    public function toArray(): array
    {
        return [
            'source' => $this->source,
            'count' => $this->count,
            'last_id' => $this->lastId,
            'has_more' => $this->hasMore,
            'events' => array_map(
                fn (EventResponse $event): array => $event->toArray(),
                $this->events
            ),
        ];
    }
}
