<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\EventId;
use PHPUnit\Framework\TestCase;

final class EventIdTest extends TestCase
{
    public function testCanCreateFromInt(): void
    {
        $eventId = EventId::fromInt(42);

        $this->assertInstanceOf(EventId::class, $eventId);
        $this->assertEquals(42, $eventId->toInt());
    }

    public function testCanCreateFromString(): void
    {
        $eventId = EventId::fromString('123');

        $this->assertEquals(123, $eventId->toInt());
        $this->assertEquals('123', $eventId->toString());
    }

    public function testThrowsExceptionForInvalidString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Event ID must be a valid integer');

        EventId::fromString('invalid');
    }

    public function testThrowsExceptionForNegativeId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Event ID must be a positive integer');

        EventId::fromInt(-1);
    }

    public function testThrowsExceptionForZeroId(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Event ID must be a positive integer');

        EventId::fromInt(0);
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $eventId1 = EventId::fromInt(5);
        $eventId2 = EventId::fromInt(5);

        $this->assertTrue($eventId1->equals($eventId2));
    }

    public function testEqualsReturnsFalseForDifferentValues(): void
    {
        $eventId1 = EventId::fromInt(1);
        $eventId2 = EventId::fromInt(2);

        $this->assertFalse($eventId1->equals($eventId2));
    }

    public function testCanConvertToString(): void
    {
        $eventId = EventId::fromInt(99);

        $this->assertEquals('99', (string) $eventId);
    }

    public function testToIntReturnsInteger(): void
    {
        $eventId = EventId::fromInt(777);

        $this->assertIsInt($eventId->toInt());
        $this->assertEquals(777, $eventId->toInt());
    }
}
