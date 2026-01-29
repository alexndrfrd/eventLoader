<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\EventName;
use PHPUnit\Framework\TestCase;

final class EventNameTest extends TestCase
{
    public function testCanCreateValidEventName(): void
    {
        $name = 'Annual Conference 2026';
        $eventName = EventName::fromString($name);

        $this->assertEquals($name, $eventName->toString());
    }

    public function testThrowsExceptionForTooShortName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Event name must be at least 3 characters long');

        EventName::fromString('ab');
    }

    public function testThrowsExceptionForTooLongName(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Event name must not exceed 255 characters');

        EventName::fromString(str_repeat('a', 256));
    }

    public function testAcceptsMinimumLength(): void
    {
        $eventName = EventName::fromString('abc');

        $this->assertEquals('abc', $eventName->toString());
    }

    public function testAcceptsMaximumLength(): void
    {
        $name = str_repeat('a', 255);
        $eventName = EventName::fromString($name);

        $this->assertEquals($name, $eventName->toString());
    }

    public function testEqualsReturnsTrueForSameValue(): void
    {
        $name = 'Tech Summit';
        $eventName1 = EventName::fromString($name);
        $eventName2 = EventName::fromString($name);

        $this->assertTrue($eventName1->equals($eventName2));
    }

    public function testEqualsReturnsFalseForDifferentValues(): void
    {
        $eventName1 = EventName::fromString('Event One');
        $eventName2 = EventName::fromString('Event Two');

        $this->assertFalse($eventName1->equals($eventName2));
    }
}
