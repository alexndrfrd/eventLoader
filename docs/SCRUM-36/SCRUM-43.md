# SCRUM-43: Event Entity

**Priority:** Medium | **Points:** 2 | **Epic:** SCRUM-36

## Story
Implement Event entity with proper business logic and validation.

## Implementation
```php
class Event {
    private function __construct(
        private readonly EventId $id,
        private readonly EventName $name,
        private readonly EventDate $date,
        private readonly string $sourceName
    ) {
        if (trim($sourceName) === '') {
            throw new \InvalidArgumentException('Source name cannot be empty');
        }
    }

    public static function create(
        EventId $id,
        EventName $name,
        EventDate $date,
        string $sourceName
    ): self {
        return new self($id, $name, $date, $sourceName);
    }

    // Getters
    public function getId(): EventId { return $this->id; }
    public function getName(): EventName { return $this->name; }
    public function getDate(): EventDate { return $this->date; }
    public function getSourceName(): string { return $this->sourceName; }

    // Business logic
    public function equals(Event $other): bool {
        return $this->id->equals($other->id) && $this->sourceName === $other->sourceName;
    }

    public function toArray(): array {
        return [
            'id' => $this->id->getValue(),
            'name' => $this->name->getValue(),
            'date' => $this->date->toIso8601(),
            'source' => $this->sourceName
        ];
    }
}
```

## Tests
- Creation validation
- Immutability
- Equality comparison
- Serialization
- Empty source name throws exception

## Links
Jira: https://alexandrubesleaga92.atlassian.net/browse/SCRUM-43
