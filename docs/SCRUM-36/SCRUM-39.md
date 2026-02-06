# SCRUM-39: Event Repository

**Priority:** High | **Points:** 5 | **Epic:** SCRUM-36

## Story
Implement EventRepositoryInterface using Doctrine ORM for efficient event persistence and retrieval.

## Database Schema
```sql
CREATE TABLE events (
    id SERIAL PRIMARY KEY,
    event_id INT NOT NULL,
    name VARCHAR(255) NOT NULL,
    date TIMESTAMP NOT NULL,
    source_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL,
    CONSTRAINT unique_event_source UNIQUE (event_id, source_name)
);
CREATE INDEX idx_source_event ON events (source_name, event_id);
```

## Implementation
```php
class DoctrineEventRepository implements EventRepositoryInterface {
    public function save(Event $event): void {
        try {
            $entity = new EventEntity(...);
            $this->entityManager->persist($entity);
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            // Idempotent - log and continue
        }
    }
    
    public function findBySince(?EventId $since, int $limit): array {
        // SELECT * FROM events WHERE event_id > ? ORDER BY event_id LIMIT ?
    }
    
    public function getLastProcessedId(string $sourceName): ?EventId {
        // SELECT MAX(event_id) FROM events WHERE source_name = ?
    }
}
```

## Tests
- Unit: InMemoryEventRepository
- Integration: Real database tests
- Test duplicate handling
- Performance: 10k+ events

## Links
Jira: https://alexandrubesleaga92.atlassian.net/browse/SCRUM-39
