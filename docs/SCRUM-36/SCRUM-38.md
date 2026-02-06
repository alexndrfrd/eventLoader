# SCRUM-38: Value Objects Implementation

**Priority:** Medium | **Points:** 2 | **Epic:** SCRUM-36

## Story
As a developer, I want to implement value objects for Event domain so that data is validated and immutable.

## Implementation

### EventId
```php
final readonly class EventId {
    public function __construct(private int $value) {
        if ($value <= 0) throw new InvalidValueObjectException('EventId must be positive');
    }
    public function getValue(): int { return $this->value; }
    public function isGreaterThan(EventId $other): bool { return $this->value > $other->value; }
    public function equals(EventId $other): bool { return $this->value === $other->value; }
}
```

### EventName
```php
final readonly class EventName {
    private const MAX_LENGTH = 255;
    public function __construct(private string $value) {
        if (trim($value) === '') throw new InvalidValueObjectException('EventName cannot be empty');
        if (mb_strlen($value) > self::MAX_LENGTH) throw new InvalidValueObjectException('EventName too long');
    }
    public function getValue(): string { return $this->value; }
}
```

### EventDate
```php
final readonly class EventDate {
    public function __construct(private \DateTimeImmutable $value) {}
    public static function fromString(string $dateString): self {
        return new self(new \DateTimeImmutable($dateString));
    }
    public function getValue(): \DateTimeImmutable { return $this->value; }
    public function toIso8601(): string { return $this->value->format(\DateTimeInterface::ATOM); }
}
```

## Unit Tests
- Test validation rules
- Test immutability
- Test comparison methods
- Test edge cases (empty, negative, too long)

## Links
- Jira: https://alexandrubesleaga92.atlassian.net/browse/SCRUM-38
- Related: SCRUM-43 (Event entity uses these VOs)
