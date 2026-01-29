<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\ValueObject\EventDate;
use PHPUnit\Framework\TestCase;

final class EventDateTest extends TestCase
{
    public function testCanCreateFromDateTime(): void
    {
        $dateTime = new \DateTimeImmutable('2026-06-15 10:00:00');
        $eventDate = EventDate::fromDateTime($dateTime);

        $this->assertEquals($dateTime, $eventDate->toDateTime());
    }

    public function testCanCreateFromString(): void
    {
        $dateString = '2026-06-15 10:00:00';
        $eventDate = EventDate::fromString($dateString);

        $this->assertEquals($dateString, $eventDate->toString());
    }

    public function testThrowsExceptionForInvalidDateString(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid date format');

        EventDate::fromString('invalid-date');
    }

    public function testCanCreateNow(): void
    {
        $before = new \DateTimeImmutable();
        $eventDate = EventDate::now();
        $after = new \DateTimeImmutable();

        $this->assertGreaterThanOrEqual($before, $eventDate->toDateTime());
        $this->assertLessThanOrEqual($after, $eventDate->toDateTime());
    }

    public function testIsBeforeReturnsTrueWhenDateIsEarlier(): void
    {
        $earlier = EventDate::fromString('2026-01-01');
        $later = EventDate::fromString('2026-12-31');

        $this->assertTrue($earlier->isBefore($later));
    }

    public function testIsBeforeReturnsFalseWhenDateIsLater(): void
    {
        $earlier = EventDate::fromString('2026-01-01');
        $later = EventDate::fromString('2026-12-31');

        $this->assertFalse($later->isBefore($earlier));
    }

    public function testIsAfterReturnsTrueWhenDateIsLater(): void
    {
        $earlier = EventDate::fromString('2026-01-01');
        $later = EventDate::fromString('2026-12-31');

        $this->assertTrue($later->isAfter($earlier));
    }

    public function testIsAfterReturnsFalseWhenDateIsEarlier(): void
    {
        $earlier = EventDate::fromString('2026-01-01');
        $later = EventDate::fromString('2026-12-31');

        $this->assertFalse($earlier->isAfter($later));
    }

    public function testEqualsReturnsTrueForSameDate(): void
    {
        $date1 = EventDate::fromString('2026-06-15 10:00:00');
        $date2 = EventDate::fromString('2026-06-15 10:00:00');

        $this->assertTrue($date1->equals($date2));
    }

    public function testEqualsReturnsFalseForDifferentDates(): void
    {
        $date1 = EventDate::fromString('2026-06-15');
        $date2 = EventDate::fromString('2026-06-16');

        $this->assertFalse($date1->equals($date2));
    }

    public function testCanFormatDateWithCustomFormat(): void
    {
        $eventDate = EventDate::fromString('2026-06-15 14:30:00');

        $this->assertEquals('15/06/2026', $eventDate->toString('d/m/Y'));
    }
}
