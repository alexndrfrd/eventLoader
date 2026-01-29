<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Response;

use App\Application\Response\EventCreatedResponse;
use App\Domain\ValueObject\EventId;
use PHPUnit\Framework\TestCase;

final class EventCreatedResponseTest extends TestCase
{
    public function testCanCreateFromEventId(): void
    {
        $eventId = EventId::fromInt(123);
        
        $response = EventCreatedResponse::fromEventId($eventId);

        $this->assertInstanceOf(EventCreatedResponse::class, $response);
        $this->assertEquals('123', $response->id);
        $this->assertEquals('Event created successfully', $response->message);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $eventId = EventId::fromInt(456);
        $response = EventCreatedResponse::fromEventId($eventId);
        
        $array = $response->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('id', $array);
        $this->assertArrayHasKey('message', $array);
        $this->assertEquals('456', $array['id']);
        $this->assertEquals('Event created successfully', $array['message']);
    }

    public function testResponseIsReadonly(): void
    {
        $eventId = EventId::fromInt(1);
        $response = EventCreatedResponse::fromEventId($eventId);

        $this->expectException(\Error::class);
        $response->message = 'Changed'; // @phpstan-ignore-line
    }
}
