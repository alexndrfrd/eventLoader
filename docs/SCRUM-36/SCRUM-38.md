# As a developer, I want to implement value objects for Event domain so that data is validated and immutable

**Epic:** SCRUM-36  
**Story Key:** SCRUM-38  
**Priority:** Medium  
**Story Points:** 2

---

## Description

Implement value objects for the Event domain to ensure data validation, immutability, and type safety. Value objects encapsulate primitive types and enforce business rules at creation time.

## Acceptance Criteria

✅ **EventId** value object:
- Validate: positive integer
- Method: `getValue(): int`
- Comparable: supports comparison operators (`isGreaterThan`, `equals`)

✅ **EventName** value object:
- Validate: non-empty string, max 255 characters
- Method: `getValue(): string`

✅ **EventDate** value object:
- Validate: proper DateTime format
- Method: `getValue(): DateTimeImmutable`
- Support ISO 8601 format

✅ All value objects are readonly and immutable

✅ Validation errors throw domain exceptions

---

## Technical Implementation

### File Structure

```
src/Domain/ValueObject/
├── EventId.php
├── EventName.php
└── EventDate.php

src/Domain/Exception/
└── InvalidValueObjectException.php
```

### EventId Implementation

**File:** `src/Domain/ValueObject/EventId.php`

```php
<?php

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidValueObjectException;

final readonly class EventId
{
    public function __construct(private int $value)
    {
        if ($value <= 0) {
            throw new InvalidValueObjectException(
                sprintf('EventId must be a positive integer, got %d', $value)
            );
        }
    }

    public function getValue(): int
    {
        return $this->value;
    }

    public function isGreaterThan(EventId $other): bool
    {
        return $this->value > $other->value;
    }

    public function isLessThan(EventId $other): bool
    {
        return $this->value < $other->value;
    }

    public function equals(EventId $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return (string) $this->value;
    }

    public function jsonSerialize(): int
    {
        return $this->value;
    }
}
```

### EventName Implementation

**File:** `src/Domain/ValueObject/EventName.php`

```php
<?php

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidValueObjectException;

final readonly class EventName
{
    private const MAX_LENGTH = 255;

    public function __construct(private string $value)
    {
        if (trim($value) === '') {
            throw new InvalidValueObjectException('EventName cannot be empty');
        }

        if (mb_strlen($value) > self::MAX_LENGTH) {
            throw new InvalidValueObjectException(
                sprintf(
                    'EventName cannot exceed %d characters, got %d',
                    self::MAX_LENGTH,
                    mb_strlen($value)
                )
            );
        }
    }

    public function getValue(): string
    {
        return $this->value;
    }

    public function equals(EventName $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    public function jsonSerialize(): string
    {
        return $this->value;
    }
}
```

### EventDate Implementation

**File:** `src/Domain/ValueObject/EventDate.php`

```php
<?php

namespace App\Domain\ValueObject;

use App\Domain\Exception\InvalidValueObjectException;

final readonly class EventDate
{
    public function __construct(private \DateTimeImmutable $value)
    {
        // Validation happens via type system
        // Additional business rules can be added here
    }

    public static function fromString(string $dateString): self
    {
        try {
            $date = new \DateTimeImmutable($dateString);
            return new self($date);
        } catch (\Exception $e) {
            throw new InvalidValueObjectException(
                sprintf('Invalid date format: %s', $dateString),
                0,
                $e
            );
        }
    }

    public static function now(): self
    {
        return new self(new \DateTimeImmutable());
    }

    public function getValue(): \DateTimeImmutable
    {
        return $this->value;
    }

    public function toIso8601(): string
    {
        return $this->value->format(\DateTimeInterface::ATOM);
    }

    public function isBefore(EventDate $other): bool
    {
        return $this->value < $other->value;
    }

    public function isAfter(EventDate $other): bool
    {
        return $this->value > $other->value;
    }

    public function equals(EventDate $other): bool
    {
        return $this->value == $other->value;
    }

    public function __toString(): string
    {
        return $this->toIso8601();
    }

    public function jsonSerialize(): string
    {
        return $this->toIso8601();
    }
}
```

### Exception Class

**File:** `src/Domain/Exception/InvalidValueObjectException.php`

```php
<?php

namespace App\Domain\Exception;

class InvalidValueObjectException extends \InvalidArgumentException
{
}
```

---

## Unit Tests

### EventId Tests

**File:** `tests/Unit/Domain/ValueObject/EventIdTest.php`

```php
<?php

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\Exception\InvalidValueObjectException;
use App\Domain\ValueObject\EventId;
use PHPUnit\Framework\TestCase;

class EventIdTest extends TestCase
{
    public function testCanCreateEventIdWithPositiveInteger(): void
    {
        $eventId = new EventId(42);
        $this->assertEquals(42, $eventId->getValue());
    }

    public function testThrowsExceptionForZeroValue(): void
    {
        $this->expectException(InvalidValueObjectException::class);
        $this->expectExceptionMessage('EventId must be a positive integer');
        new EventId(0);
    }

    public function testThrowsExceptionForNegativeValue(): void
    {
        $this->expectException(InvalidValueObjectException::class);
        new EventId(-1);
    }

    public function testIsGreaterThan(): void
    {
        $id1 = new EventId(10);
        $id2 = new EventId(5);
        
        $this->assertTrue($id1->isGreaterThan($id2));
        $this->assertFalse($id2->isGreaterThan($id1));
    }

    public function testEquals(): void
    {
        $id1 = new EventId(42);
        $id2 = new EventId(42);
        $id3 = new EventId(100);
        
        $this->assertTrue($id1->equals($id2));
        $this->assertFalse($id1->equals($id3));
    }

    public function testToString(): void
    {
        $eventId = new EventId(123);
        $this->assertEquals('123', (string) $eventId);
    }

    public function testJsonSerialize(): void
    {
        $eventId = new EventId(456);
        $this->assertEquals(456, $eventId->jsonSerialize());
    }

    public function testEventIdIsImmutable(): void
    {
        $eventId = new EventId(10);
        $reflection = new \ReflectionClass($eventId);
        
        // Check that class is readonly (PHP 8.1+)
        $this->assertTrue($reflection->isReadOnly());
    }
}
```

### EventName Tests

**File:** `tests/Unit/Domain/ValueObject/EventNameTest.php`

```php
<?php

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\Exception\InvalidValueObjectException;
use App\Domain\ValueObject\EventName;
use PHPUnit\Framework\TestCase;

class EventNameTest extends TestCase
{
    public function testCanCreateEventNameWithValidString(): void
    {
        $name = new EventName('UserRegistered');
        $this->assertEquals('UserRegistered', $name->getValue());
    }

    public function testThrowsExceptionForEmptyString(): void
    {
        $this->expectException(InvalidValueObjectException::class);
        $this->expectExceptionMessage('EventName cannot be empty');
        new EventName('');
    }

    public function testThrowsExceptionForWhitespaceOnlyString(): void
    {
        $this->expectException(InvalidValueObjectException::class);
        new EventName('   ');
    }

    public function testThrowsExceptionForStringExceeding255Chars(): void
    {
        $this->expectException(InvalidValueObjectException::class);
        $this->expectExceptionMessage('EventName cannot exceed 255 characters');
        new EventName(str_repeat('a', 256));
    }

    public function testAcceptsStringWith255Chars(): void
    {
        $name = new EventName(str_repeat('a', 255));
        $this->assertEquals(255, mb_strlen($name->getValue()));
    }

    public function testEquals(): void
    {
        $name1 = new EventName('EventA');
        $name2 = new EventName('EventA');
        $name3 = new EventName('EventB');
        
        $this->assertTrue($name1->equals($name2));
        $this->assertFalse($name1->equals($name3));
    }

    public function testToString(): void
    {
        $name = new EventName('TestEvent');
        $this->assertEquals('TestEvent', (string) $name);
    }
}
```

### EventDate Tests

**File:** `tests/Unit/Domain/ValueObject/EventDateTest.php`

```php
<?php

namespace App\Tests\Unit\Domain\ValueObject;

use App\Domain\Exception\InvalidValueObjectException;
use App\Domain\ValueObject\EventDate;
use PHPUnit\Framework\TestCase;

class EventDateTest extends TestCase
{
    public function testCanCreateEventDateFromDateTimeImmutable(): void
    {
        $date = new \DateTimeImmutable('2024-01-01 12:00:00');
        $eventDate = new EventDate($date);
        
        $this->assertEquals($date, $eventDate->getValue());
    }

    public function testCanCreateFromString(): void
    {
        $eventDate = EventDate::fromString('2024-01-01 12:00:00');
        $this->assertInstanceOf(EventDate::class, $eventDate);
    }

    public function testFromStringThrowsExceptionForInvalidFormat(): void
    {
        $this->expectException(InvalidValueObjectException::class);
        $this->expectExceptionMessage('Invalid date format');
        EventDate::fromString('invalid-date');
    }

    public function testNowReturnsCurrentTime(): void
    {
        $before = new \DateTimeImmutable();
        $eventDate = EventDate::now();
        $after = new \DateTimeImmutable();
        
        $this->assertGreaterThanOrEqual(
            $before->getTimestamp(),
            $eventDate->getValue()->getTimestamp()
        );
        $this->assertLessThanOrEqual(
            $after->getTimestamp(),
            $eventDate->getValue()->getTimestamp()
        );
    }

    public function testToIso8601(): void
    {
        $date = new \DateTimeImmutable('2024-01-01T12:00:00+00:00');
        $eventDate = new EventDate($date);
        
        $this->assertEquals(
            '2024-01-01T12:00:00+00:00',
            $eventDate->toIso8601()
        );
    }

    public function testIsBefore(): void
    {
        $date1 = EventDate::fromString('2024-01-01');
        $date2 = EventDate::fromString('2024-01-02');
        
        $this->assertTrue($date1->isBefore($date2));
        $this->assertFalse($date2->isBefore($date1));
    }

    public function testIsAfter(): void
    {
        $date1 = EventDate::fromString('2024-01-02');
        $date2 = EventDate::fromString('2024-01-01');
        
        $this->assertTrue($date1->isAfter($date2));
        $this->assertFalse($date2->isAfter($date1));
    }

    public function testEquals(): void
    {
        $date1 = EventDate::fromString('2024-01-01 12:00:00');
        $date2 = EventDate::fromString('2024-01-01 12:00:00');
        $date3 = EventDate::fromString('2024-01-02 12:00:00');
        
        $this->assertTrue($date1->equals($date2));
        $this->assertFalse($date1->equals($date3));
    }
}
```

---

## Definition of Done

- [ ] EventId, EventName, EventDate implemented
- [ ] All value objects are readonly
- [ ] Validation logic working correctly
- [ ] Unit tests with 100% coverage
- [ ] Edge cases tested
- [ ] PHPStan level 9 passes
- [ ] Documentation added
- [ ] Code review approved

---

## Related Stories

- SCRUM-43: Event entity (uses these value objects)
- SCRUM-45: Core interfaces (EventId used in interface signatures)

---

## References

- **Jira:** https://alexandrubesleaga92.atlassian.net/browse/SCRUM-38
- **Epic:** SCRUM-36

---

## For Cursor AI Agents

**Value Object Pattern:**
- Always validate in constructor
- Make objects readonly (PHP 8.1+ feature)
- Provide factory methods for complex creation
- Implement comparison methods
- Support serialization (toString, jsonSerialize)
- Throw domain exceptions, not generic ones
