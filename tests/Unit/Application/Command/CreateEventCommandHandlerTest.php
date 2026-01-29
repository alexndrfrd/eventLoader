<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Command;

use App\Application\Command\CreateEventCommand;
use App\Application\Command\CreateEventCommandHandler;
use App\Domain\Entity\Event;
use App\Domain\ValueObject\EventId;
use App\Infrastructure\Persistence\InMemoryEventRepository;
use PHPUnit\Framework\TestCase;

final class CreateEventCommandHandlerTest extends TestCase
{
    private InMemoryEventRepository $repository;
    private CreateEventCommandHandler $handler;

    protected function setUp(): void
    {
        $this->repository = new InMemoryEventRepository();
        $this->repository->clear(); // Reset static state
        $this->handler = new CreateEventCommandHandler($this->repository);
    }

    protected function tearDown(): void
    {
        $this->repository->clear(); // Clean up after test
    }

    public function testCanHandleCreateEventCommand(): void
    {
        $command = new CreateEventCommand(
            name: 'Tech Summit 2026',
            scheduledDate: '2026-09-15 09:00:00',
            description: 'Annual technology summit'
        );

        $eventId = ($this->handler)($command);

        $this->assertInstanceOf(EventId::class, $eventId);
        $this->assertEquals(1, $eventId->toInt()); // First event gets ID 1
    }

    public function testCreatedEventIsStoredInRepository(): void
    {
        $command = new CreateEventCommand(
            name: 'Workshop',
            scheduledDate: '2026-10-01 14:00:00'
        );

        $eventId = ($this->handler)($command);

        $this->assertTrue($this->repository->exists($eventId));
        
        $storedEvent = $this->repository->findById($eventId);
        $this->assertInstanceOf(Event::class, $storedEvent);
        $this->assertEquals('Workshop', $storedEvent->getName()->toString());
    }

    public function testHandlerThrowsExceptionForInvalidEventName(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $command = new CreateEventCommand(
            name: 'ab', // Too short
            scheduledDate: '2026-09-15'
        );

        ($this->handler)($command);
    }

    public function testHandlerThrowsExceptionForInvalidDate(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $command = new CreateEventCommand(
            name: 'Valid Event Name',
            scheduledDate: 'invalid-date'
        );

        ($this->handler)($command);
    }

    public function testMultipleEventsGetIncrementingIds(): void
    {
        $command1 = new CreateEventCommand(
            name: 'Event One',
            scheduledDate: '2026-09-15'
        );
        $command2 = new CreateEventCommand(
            name: 'Event Two',
            scheduledDate: '2026-10-15'
        );

        $id1 = ($this->handler)($command1);
        $id2 = ($this->handler)($command2);

        $this->assertEquals(1, $id1->toInt());
        $this->assertEquals(2, $id2->toInt());
    }
}
