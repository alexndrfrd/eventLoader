<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Query;

use App\Application\Query\GetEventsQuery;
use App\Application\Query\GetEventsQueryHandler;
use App\Domain\Entity\Event;
use App\Domain\ValueObject\EventDate;
use App\Domain\ValueObject\EventId;
use App\Domain\ValueObject\EventName;
use App\Infrastructure\Persistence\InMemoryEventRepository;
use PHPUnit\Framework\TestCase;

final class GetEventsQueryHandlerTest extends TestCase
{
    private InMemoryEventRepository $repository;
    private GetEventsQueryHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryEventRepository();
        $this->repository->clear(); // Reset static state
        $this->handler = new GetEventsQueryHandler($this->repository);
    }

    protected function tearDown(): void
    {
        $this->repository->clear(); // Clean up after test
    }

    public function testCanRetrieveAllEventsWhenNoSinceProvided(): void
    {
        // Create 3 events
        for ($i = 1; $i <= 3; $i++) {
            $event = Event::create(
                EventId::fromInt($i),
                EventName::fromString("Event $i"),
                EventDate::fromString('2026-12-31')
            );
            $this->repository->save($event);
        }

        $query = new GetEventsQuery(since: null, limit: 1000);
        $events = ($this->handler)($query);

        $this->assertCount(3, $events);
    }

    public function testReturnsEventsSortedByIdAscending(): void
    {
        // Create events in random order
        $this->repository->save(Event::create(
            EventId::fromInt(3),
            EventName::fromString('Third'),
            EventDate::fromString('2026-12-31')
        ));
        $this->repository->save(Event::create(
            EventId::fromInt(1),
            EventName::fromString('First'),
            EventDate::fromString('2026-12-31')
        ));
        $this->repository->save(Event::create(
            EventId::fromInt(2),
            EventName::fromString('Second'),
            EventDate::fromString('2026-12-31')
        ));

        $query = new GetEventsQuery(since: null, limit: 1000);
        $events = ($this->handler)($query);

        $this->assertEquals(1, $events[0]->getId()->toInt());
        $this->assertEquals(2, $events[1]->getId()->toInt());
        $this->assertEquals(3, $events[2]->getId()->toInt());
    }

    public function testReturnsOnlyEventsAfterSince(): void
    {
        // Create events with IDs 1-5
        for ($i = 1; $i <= 5; $i++) {
            $event = Event::create(
                EventId::fromInt($i),
                EventName::fromString("Event $i"),
                EventDate::fromString('2026-12-31')
            );
            $this->repository->save($event);
        }

        // Get events with ID > 2
        $query = new GetEventsQuery(since: '2', limit: 1000);
        $events = ($this->handler)($query);

        $this->assertCount(3, $events); // Should get 3, 4, 5
        $this->assertEquals(3, $events[0]->getId()->toInt());
        $this->assertEquals(4, $events[1]->getId()->toInt());
        $this->assertEquals(5, $events[2]->getId()->toInt());
    }

    public function testRespectsLimit(): void
    {
        // Create 10 events
        for ($i = 1; $i <= 10; $i++) {
            $event = Event::create(
                EventId::fromInt($i),
                EventName::fromString("Event $i"),
                EventDate::fromString('2026-12-31')
            );
            $this->repository->save($event);
        }

        $query = new GetEventsQuery(since: null, limit: 3);
        $events = ($this->handler)($query);

        $this->assertCount(3, $events);
        $this->assertEquals(1, $events[0]->getId()->toInt());
        $this->assertEquals(2, $events[1]->getId()->toInt());
        $this->assertEquals(3, $events[2]->getId()->toInt());
    }

    public function testCombinesSinceAndLimit(): void
    {
        // Create 10 events
        for ($i = 1; $i <= 10; $i++) {
            $event = Event::create(
                EventId::fromInt($i),
                EventName::fromString("Event $i"),
                EventDate::fromString('2026-12-31')
            );
            $this->repository->save($event);
        }

        // Get 3 events after ID 5
        $query = new GetEventsQuery(since: '5', limit: 3);
        $events = ($this->handler)($query);

        $this->assertCount(3, $events); // Should get 6, 7, 8
        $this->assertEquals(6, $events[0]->getId()->toInt());
        $this->assertEquals(7, $events[1]->getId()->toInt());
        $this->assertEquals(8, $events[2]->getId()->toInt());
    }

    public function testReturnsEmptyArrayWhenNoEventsAfterSince(): void
    {
        $event = Event::create(
            EventId::fromInt(1),
            EventName::fromString('Only Event'),
            EventDate::fromString('2026-12-31')
        );
        $this->repository->save($event);

        $query = new GetEventsQuery(since: '10', limit: 1000);
        $events = ($this->handler)($query);

        $this->assertCount(0, $events);
    }

    public function testThrowsExceptionForInvalidSinceId(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $query = new GetEventsQuery(since: 'invalid', limit: 1000);
        ($this->handler)($query);
    }
}
