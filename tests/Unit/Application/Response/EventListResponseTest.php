<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Response;

use App\Application\Response\EventListResponse;
use App\Domain\Entity\Event;
use App\Domain\ValueObject\EventDate;
use App\Domain\ValueObject\EventId;
use App\Domain\ValueObject\EventName;
use PHPUnit\Framework\TestCase;

final class EventListResponseTest extends TestCase
{
    public function testCanCreateFromEvents(): void
    {
        $events = [
            Event::create(
                EventId::fromInt(1),
                EventName::fromString('Event 1'),
                EventDate::fromString('2026-12-31')
            ),
            Event::create(
                EventId::fromInt(2),
                EventName::fromString('Event 2'),
                EventDate::fromString('2026-12-31')
            ),
        ];

        $response = EventListResponse::fromEvents($events, 'test-source', 10);

        $this->assertInstanceOf(EventListResponse::class, $response);
        $this->assertCount(2, $response->events);
        $this->assertEquals('test-source', $response->source);
        $this->assertEquals(2, $response->count);
        $this->assertEquals('2', $response->lastId);
        $this->assertFalse($response->hasMore); // count < limit
    }

    public function testHasMoreIsTrueWhenCountEqualsLimit(): void
    {
        $events = [
            Event::create(
                EventId::fromInt(1),
                EventName::fromString('Event 1'),
                EventDate::fromString('2026-12-31')
            ),
            Event::create(
                EventId::fromInt(2),
                EventName::fromString('Event 2'),
                EventDate::fromString('2026-12-31')
            ),
        ];

        $response = EventListResponse::fromEvents($events, 'test-source', 2);

        $this->assertTrue($response->hasMore); // count == limit
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $events = [
            Event::create(
                EventId::fromInt(1),
                EventName::fromString('Event 1'),
                EventDate::fromString('2026-12-31')
            ),
        ];

        $response = EventListResponse::fromEvents($events, 'my-source', 10);
        $array = $response->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('source', $array);
        $this->assertArrayHasKey('count', $array);
        $this->assertArrayHasKey('last_id', $array);
        $this->assertArrayHasKey('has_more', $array);
        $this->assertArrayHasKey('events', $array);
        $this->assertEquals('my-source', $array['source']);
        $this->assertEquals(1, $array['count']);
        $this->assertEquals('1', $array['last_id']);
        $this->assertFalse($array['has_more']);
        $this->assertIsArray($array['events']);
        $this->assertCount(1, $array['events']);
    }

    public function testHandlesEmptyEventList(): void
    {
        $response = EventListResponse::fromEvents([], 'empty-source', 10);

        $this->assertCount(0, $response->events);
        $this->assertEquals(0, $response->count);
        $this->assertNull($response->lastId);
        $this->assertFalse($response->hasMore);
    }

    public function testLastIdIsStringRepresentationOfEventId(): void
    {
        $events = [
            Event::create(
                EventId::fromInt(42),
                EventName::fromString('Event'),
                EventDate::fromString('2026-12-31')
            ),
        ];

        $response = EventListResponse::fromEvents($events, 'source', 10);

        $this->assertEquals('42', $response->lastId);
        $this->assertIsString($response->lastId);
    }
}
