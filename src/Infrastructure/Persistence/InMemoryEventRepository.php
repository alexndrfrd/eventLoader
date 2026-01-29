<?php

declare(strict_types=1);

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Event;
use App\Domain\Exception\EventNotFoundException;
use App\Domain\Repository\EventRepositoryInterface;
use App\Domain\ValueObject\EventDate;
use App\Domain\ValueObject\EventId;

/**
 * In-Memory Implementation of EventRepositoryInterface
 * For testing purposes - demonstrates Dependency Inversion Principle
 * Generates auto-increment integer IDs
 * 
 * Note: Uses static storage to persist across different command instances
 */
final class InMemoryEventRepository implements EventRepositoryInterface
{
    /**
     * Static storage to persist across different command instances
     * 
     * @var array<int, Event>
     */
    private static array $staticEvents = [];
    
    private static int $staticNextId = 1;
    
    /**
     * @var array<int, Event>
     */
    private array $events = [];
    
    private int $nextId = 1;
    
    public function __construct()
    {
        // Initialize from static storage if exists
        if (!empty(self::$staticEvents)) {
            $this->events = self::$staticEvents;
            $this->nextId = self::$staticNextId;
        }
    }

    private function syncToStatic(): void
    {
        self::$staticEvents = $this->events;
        self::$staticNextId = $this->nextId;
    }

    public function nextId(): EventId
    {
        if (empty($this->events) && !empty(self::$staticEvents)) {
            $this->events = self::$staticEvents;
            $this->nextId = self::$staticNextId;
        }
        
        $id = EventId::fromInt($this->nextId);
        $this->nextId++;

        self::$staticNextId = $this->nextId;
        
        return $id;
    }

    public function findById(EventId $id): Event
    {
        if (empty($this->events) && !empty(self::$staticEvents)) {
            $this->events = self::$staticEvents;
            $this->nextId = self::$staticNextId;
        }
        
        $key = $id->toInt();

        if (!isset($this->events[$key])) {
            throw EventNotFoundException::forId($id);
        }

        return $this->events[$key];
    }

    public function findAll(): array
    {
        if (empty($this->events) && !empty(self::$staticEvents)) {
            $this->events = self::$staticEvents;
            $this->nextId = self::$staticNextId;
        }
        
        return array_values($this->events);
    }

    public function findUpcoming(): array
    {
        $now = EventDate::now();

        return array_values(
            array_filter(
                $this->events,
                fn(Event $event) => $now->isBefore($event->getScheduledDate())
            )
        );
    }

    public function findSince(?EventId $since, int $limit = 1000): array
    {
        if (empty($this->events) && !empty(self::$staticEvents)) {
            $this->events = self::$staticEvents;
            $this->nextId = self::$staticNextId;
        }
        
        $events = $this->events;
        
        if ($since !== null) {
            $sinceValue = $since->toInt();
            $events = array_filter(
                $events,
                fn (Event $event): bool => $event->getId()->toInt() > $sinceValue
            );
        }
        
        usort($events, fn (Event $a, Event $b): int =>
            $a->getId()->toInt() <=> $b->getId()->toInt()
        );
        
        $events = array_slice($events, 0, $limit);
        
        return array_values($events);
    }

    public function getSourceName(): string
    {
        return 'in-memory-event-source';
    }

    public function save(Event $event): void
    {
        // Load from static if needed
        if (empty($this->events) && !empty(self::$staticEvents)) {
            $this->events = self::$staticEvents;
            $this->nextId = self::$staticNextId;
        }
        
        $key = $event->getId()->toInt();
        $this->events[$key] = $event;
        
        // Update next ID to be after this event's ID
        if ($key >= $this->nextId) {
            $this->nextId = $key + 1;
        }
        
        // Sync to static storage
        self::$staticEvents = $this->events;
        self::$staticNextId = $this->nextId;
    }

    public function delete(EventId $id): void
    {
        unset($this->events[$id->toInt()]);
    }

    public function exists(EventId $id): bool
    {
        return isset($this->events[$id->toInt()]);
    }

    public function clear(): void
    {
        $this->events = [];
        $this->nextId = 1;
        self::$staticEvents = [];
        self::$staticNextId = 1;
    }
}
