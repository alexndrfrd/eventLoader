<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Query;

use App\Application\Query\GetEventQuery;
use App\Application\Query\GetEventQueryHandler;
use App\Domain\Entity\Event;
use App\Domain\Exception\EventNotFoundException;
use App\Domain\ValueObject\EventDate;
use App\Domain\ValueObject\EventId;
use App\Domain\ValueObject\EventName;
use App\Infrastructure\Persistence\InMemoryEventRepository;
use PHPUnit\Framework\TestCase;

final class GetEventQueryHandlerTest extends TestCase
{
    private InMemoryEventRepository $repository;
    private GetEventQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryEventRepository();
        $this->repository->clear(); // Reset static state
        $this->handler = new GetEventQueryHandler($this->repository);
    }

    protected function tearDown(): void
    {
        $this->repository->clear(); // Clean up after test
    }

    public function testCanRetrieveExistingEvent(): void
    {
        // Arrange
        $id = EventId::fromInt(1);
        $event = Event::create(
            $id,
            EventName::fromString('Conference'),
            EventDate::fromString('2026-12-01')
        );
        $this->repository->save($event);

        // Act
        $query = new GetEventQuery('1');
        $retrievedEvent = ($this->handler)($query);

        // Assert
        $this->assertInstanceOf(Event::class, $retrievedEvent);
        $this->assertEquals($event->getId(), $retrievedEvent->getId());
    }

    public function testThrowsExceptionWhenEventNotFound(): void
    {
        $this->expectException(EventNotFoundException::class);

        $query = new GetEventQuery('999');
        ($this->handler)($query);
    }

    public function testThrowsExceptionForInvalidEventId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $query = new GetEventQuery('invalid-id');
        ($this->handler)($query);
    }
}
