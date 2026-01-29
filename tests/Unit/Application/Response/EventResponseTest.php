<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Response;

use App\Application\Response\EventResponse;
use App\Domain\Entity\Event;
use App\Domain\ValueObject\EventDate;
use App\Domain\ValueObject\EventId;
use App\Domain\ValueObject\EventName;
use PHPUnit\Framework\TestCase;

final class EventResponseTest extends TestCase
{
    public function testCanCreateFromEntity(): void
    {
        $id = EventId::fromInt(42);
        $event = Event::create(
            $id,
            EventName::fromString('Tech Conference'),
            EventDate::fromString('2026-12-31 10:00:00'),
            'Annual conference'
        );

        $response = EventResponse::fromEntity($event);

        $this->assertInstanceOf(EventResponse::class, $response);
        $this->assertEquals('42', $response->id);
        $this->assertEquals('Tech Conference', $response->name);
        $this->assertEquals('2026-12-31 10:00:00', $response->scheduledDate);
        $this->assertEquals('Annual conference', $response->description);
        $this->assertIsBool($response->isUpcoming);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $id = EventId::fromInt(1);
        $event = Event::create(
            $id,
            EventName::fromString('Workshop'),
            EventDate::fromString('2026-06-15 14:00:00'),
            'Test description'
        );

        $response = EventResponse::fromEntity($event);
        $array = $response->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('name', $array);
        $this->assertArrayHasKey('scheduled_date', $array);
        $this->assertArrayHasKey('description', $array);
        $this->assertArrayHasKey('is_upcoming', $array);
        $this->assertArrayHasKey('created_at', $array);
        $this->assertArrayHasKey('updated_at', $array);
    }

    public function testResponseIsReadonly(): void
    {
        $id = EventId::fromInt(1);
        $event = Event::create(
            $id,
            EventName::fromString('Event'),
            EventDate::fromString('2026-12-31')
        );

        $response = EventResponse::fromEntity($event);

        // Properties are readonly - this test documents the immutability
        $this->expectException(\Error::class);
        $response->name = 'Changed'; // @phpstan-ignore-line
    }

    public function testHandlesNullDescription(): void
    {
        $id = EventId::fromInt(1);
        $event = Event::create(
            $id,
            EventName::fromString('Event'),
            EventDate::fromString('2026-12-31'),
            null // No description
        );

        $response = EventResponse::fromEntity($event);
        $array = $response->toArray();

        $this->assertNull($response->description);
        $this->assertNull($array['description']);
    }
}
