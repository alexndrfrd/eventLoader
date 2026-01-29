<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Entity;

use App\Domain\Entity\Event;
use App\Domain\Event\EventCreated;
use App\Domain\Event\EventUpdated;
use App\Domain\ValueObject\EventDate;
use App\Domain\ValueObject\EventId;
use App\Domain\ValueObject\EventName;
use PHPUnit\Framework\TestCase;

final class EventTest extends TestCase
{
    public function testCanCreateEvent(): void
    {
        $id = EventId::fromInt(1);
        $name = EventName::fromString('Tech Conference');
        $scheduledDate = EventDate::fromString('2026-12-31 10:00:00');
        $description = 'Annual tech conference';

        $event = Event::create($id, $name, $scheduledDate, $description);

        $this->assertInstanceOf(Event::class, $event);
        $this->assertEquals($id, $event->getId());
        $this->assertEquals($name, $event->getName());
        $this->assertEquals($scheduledDate, $event->getScheduledDate());
        $this->assertEquals($description, $event->getDescription());
    }

    public function testCreatingEventRecordsDomainEvent(): void
    {
        $id = EventId::fromInt(1);
        $name = EventName::fromString('Tech Conference');
        $scheduledDate = EventDate::fromString('2026-12-31');

        $event = Event::create($id, $name, $scheduledDate);
        $domainEvents = $event->pullDomainEvents();

        $this->assertCount(1, $domainEvents);
        $this->assertInstanceOf(EventCreated::class, $domainEvents[0]);
    }

    public function testPullingDomainEventsClearsTheList(): void
    {
        $id = EventId::fromInt(1);
        $event = Event::create(
            $id,
            EventName::fromString('Test Event'),
            EventDate::fromString('2026-12-31')
        );

        $firstPull = $event->pullDomainEvents();
        $secondPull = $event->pullDomainEvents();

        $this->assertCount(1, $firstPull);
        $this->assertCount(0, $secondPull);
    }

    public function testCanUpdateEventDetails(): void
    {
        $id = EventId::fromInt(1);
        $event = Event::create(
            $id,
            EventName::fromString('Original Name'),
            EventDate::fromString('2026-12-31')
        );
        
        $event->pullDomainEvents(); // Clear creation event

        $newName = EventName::fromString('Updated Name');
        $newDescription = 'Updated description';

        $event->updateDetails($newName, $newDescription);

        $this->assertEquals($newName, $event->getName());
        $this->assertEquals($newDescription, $event->getDescription());
    }

    public function testUpdatingDetailsRecordsDomainEvent(): void
    {
        $id = EventId::fromInt(1);
        $event = Event::create(
            $id,
            EventName::fromString('Test Event'),
            EventDate::fromString('2026-12-31')
        );
        
        $event->pullDomainEvents(); // Clear creation event

        $event->updateDetails(
            EventName::fromString('New Name'),
            'New description'
        );

        $domainEvents = $event->pullDomainEvents();

        $this->assertCount(1, $domainEvents);
        $this->assertInstanceOf(EventUpdated::class, $domainEvents[0]);
    }

    public function testCanRescheduleEvent(): void
    {
        $id = EventId::fromInt(1);
        $originalDate = EventDate::fromString('2026-06-15');
        $event = Event::create(
            $id,
            EventName::fromString('Conference'),
            $originalDate
        );

        $newDate = EventDate::fromString('2026-07-20');
        $event->reschedule($newDate);

        $this->assertEquals($newDate, $event->getScheduledDate());
    }

    public function testReschedulingToSameDateDoesNotRecordEvent(): void
    {
        $id = EventId::fromInt(1);
        $date = EventDate::fromString('2026-06-15');
        $event = Event::create(
            $id,
            EventName::fromString('Conference'),
            $date
        );
        
        $event->pullDomainEvents(); // Clear creation event

        $event->reschedule($date);
        $domainEvents = $event->pullDomainEvents();

        $this->assertCount(0, $domainEvents);
    }

    public function testIsScheduledInFutureReturnsTrueForFutureDate(): void
    {
        $id = EventId::fromInt(1);
        $futureDate = EventDate::fromDateTime(
            (new \DateTimeImmutable())->modify('+1 day')
        );
        
        $event = Event::create(
            $id,
            EventName::fromString('Future Event'),
            $futureDate
        );

        $this->assertTrue($event->isScheduledInFuture());
    }

    public function testIsScheduledInFutureReturnsFalseForPastDate(): void
    {
        $id = EventId::fromInt(1);
        $pastDate = EventDate::fromDateTime(
            (new \DateTimeImmutable())->modify('-1 day')
        );
        
        $event = Event::create(
            $id,
            EventName::fromString('Past Event'),
            $pastDate
        );

        $this->assertFalse($event->isScheduledInFuture());
    }

    public function testCanReconstituteEvent(): void
    {
        $id = EventId::fromInt(42);
        $name = EventName::fromString('Reconstituted Event');
        $scheduledDate = EventDate::fromString('2026-06-15');
        $description = 'Description';
        $createdAt = EventDate::fromString('2026-01-01');
        $updatedAt = EventDate::fromString('2026-01-02');

        $event = Event::reconstitute(
            $id,
            $name,
            $scheduledDate,
            $description,
            $createdAt,
            $updatedAt
        );

        $this->assertEquals($id, $event->getId());
        $this->assertEquals($name, $event->getName());
        $this->assertEquals($scheduledDate, $event->getScheduledDate());
        $this->assertEquals($description, $event->getDescription());
        $this->assertEquals($createdAt, $event->getCreatedAt());
        $this->assertEquals($updatedAt, $event->getUpdatedAt());
    }
}
