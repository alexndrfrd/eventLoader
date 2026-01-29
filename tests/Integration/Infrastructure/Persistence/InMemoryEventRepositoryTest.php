<?php

declare(strict_types=1);

namespace App\Tests\Integration\Infrastructure\Persistence;

use App\Domain\Entity\Event;
use App\Domain\Exception\EventNotFoundException;
use App\Domain\ValueObject\EventDate;
use App\Domain\ValueObject\EventId;
use App\Domain\ValueObject\EventName;
use App\Infrastructure\Persistence\InMemoryEventRepository;
use PHPUnit\Framework\TestCase;

final class InMemoryEventRepositoryTest extends TestCase
{
    private InMemoryEventRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryEventRepository();
        $this->repository->clear(); // Reset static state
    }

    protected function tearDown(): void
    {
        $this->repository->clear();
    }

    public function testCanGenerateNextId(): void
    {
        // First ID should be 1
        $id1 = $this->repository->nextId();
        $this->assertEquals(1, $id1->toInt());
        
        // Second ID should be 2 (auto-increments)
        $id2 = $this->repository->nextId();
        $this->assertEquals(2, $id2->toInt());
        
        // Third ID should be 3
        $id3 = $this->repository->nextId();
        $this->assertEquals(3, $id3->toInt());
    }

    public function testCanSaveAndRetrieveEvent(): void
    {
        $id = $this->repository->nextId();
        $event = Event::create(
            $id,
            EventName::fromString('Test Event'),
            EventDate::fromString('2026-12-31')
        );

        $this->repository->save($event);
        $retrieved = $this->repository->findById($event->getId());

        $this->assertEquals($event->getId(), $retrieved->getId());
    }

    public function testFindByIdThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(EventNotFoundException::class);

        $this->repository->findById(EventId::fromInt(999));
    }

    public function testCanFindAllEvents(): void
    {
        // Create two events with different IDs
        $event1 = Event::create(
            EventId::fromInt(10),
            EventName::fromString('Event 1'),
            EventDate::fromString('2026-06-15')
        );
        
        $event2 = Event::create(
            EventId::fromInt(20),
            EventName::fromString('Event 2'),
            EventDate::fromString('2026-07-20')
        );

        $this->repository->save($event1);
        $this->repository->save($event2);

        $allEvents = $this->repository->findAll();

        $this->assertCount(2, $allEvents);
    }

    public function testCanFindUpcomingEvents(): void
    {
        $id1 = $this->repository->nextId();
        $pastEvent = Event::create(
            $id1,
            EventName::fromString('Past Event'),
            EventDate::fromDateTime((new \DateTimeImmutable())->modify('-1 day'))
        );
        
        $id2 = $this->repository->nextId();
        $futureEvent = Event::create(
            $id2,
            EventName::fromString('Future Event'),
            EventDate::fromDateTime((new \DateTimeImmutable())->modify('+1 day'))
        );

        $this->repository->save($pastEvent);
        $this->repository->save($futureEvent);

        $upcomingEvents = $this->repository->findUpcoming();

        $this->assertCount(1, $upcomingEvents);
        $this->assertEquals($futureEvent->getId(), $upcomingEvents[0]->getId());
    }

    public function testCanDeleteEvent(): void
    {
        $id = $this->repository->nextId();
        $event = Event::create(
            $id,
            EventName::fromString('Event to Delete'),
            EventDate::fromString('2026-12-31')
        );

        $this->repository->save($event);
        $this->assertTrue($this->repository->exists($event->getId()));

        $this->repository->delete($event->getId());
        $this->assertFalse($this->repository->exists($event->getId()));
    }

    public function testExistsReturnsTrueForExistingEvent(): void
    {
        $id = $this->repository->nextId();
        $event = Event::create(
            $id,
            EventName::fromString('Existing Event'),
            EventDate::fromString('2026-12-31')
        );

        $this->repository->save($event);

        $this->assertTrue($this->repository->exists($event->getId()));
    }

    public function testExistsReturnsFalseForNonExistingEvent(): void
    {
        $this->assertFalse($this->repository->exists(EventId::fromInt(999)));
    }
}
