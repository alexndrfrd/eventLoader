<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Event;
use App\Domain\Exception\EventNotFoundException;
use App\Domain\ValueObject\EventId;

/**
 * Repository Interface - Dependency Inversion Principle
 * Domain layer defines the interface, Infrastructure layer implements it
 */
interface EventRepositoryInterface
{
    /**
     * Generate next auto-increment ID
     */
    public function nextId(): EventId;

    /**
     * @throws EventNotFoundException
     */
    public function findById(EventId $id): Event;

    /**
     * @return Event[]
     */
    public function findAll(): array;

    /**
     * @return Event[]
     */
    public function findUpcoming(): array;

    /**
     * Find events with ID greater than the specified ID, sorted by ID ascending
     * This enables event sourcing: client tracks last known ID and fetches new events
     * 
     * Behavior: SELECT * FROM events WHERE id > ? ORDER BY id LIMIT ?
     * 
     * @param EventId|null $since Last known event ID (null = from beginning)
     * @param int $limit Maximum number of events to return
     * @return Event[] Sorted by ID ascending
     */
    public function findSince(?EventId $since, int $limit = 1000): array;

    /**
     * Get the unique name/identifier of this event source
     */
    public function getSourceName(): string;

    public function save(Event $event): void;

    public function delete(EventId $id): void;

    public function exists(EventId $id): bool;
}
