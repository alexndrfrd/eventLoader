# As a system architect, I want to define core interfaces for the event loading system so that implementation is modular and extensible

**Epic:** SCRUM-36  
**Story Key:** SCRUM-45  
**Priority:** High  
**Story Points:** 3

---

## Description

Define the core interfaces for the event loading system following SOLID principles and Domain-Driven Design. These interfaces will form the contracts between layers and enable modular, testable implementations.

## Acceptance Criteria

✅ EventSourceInterface defined with methods:
- `fetchEvents(?EventId $since, int $limit): array`
- `getName(): string`
- `isAvailable(): bool`

✅ EventRepositoryInterface defined with methods:
- `save(Event $event): void`
- `findBySince(?EventId $since, int $limit): array`
- `getLastProcessedId(string $sourceName): ?EventId`

✅ EventLoaderInterface defined with methods:
- `load(): void`
- `stop(): void`

✅ LoaderCoordinationInterface defined with methods:
- `acquireLock(string $sourceName, int $ttl): bool`
- `releaseLock(string $sourceName): void`
- `getLastProcessedId(string $sourceName): ?EventId`
- `updateLastProcessedId(string $sourceName, EventId $eventId): void`

✅ RateLimiterInterface defined with methods:
- `canRequest(string $sourceName): bool`
- `recordRequest(string $sourceName): void`

---

## Technical Implementation Details

### File Structure

```
src/Domain/Service/
├── EventSourceInterface.php
├── EventLoaderInterface.php
├── LoaderCoordinationInterface.php
└── RateLimiterInterface.php

src/Domain/Repository/
└── EventRepositoryInterface.php
```

### Interface Definitions

#### EventSourceInterface

**Location:** `src/Domain/Service/EventSourceInterface.php`

```php
<?php

namespace App\Domain\Service;

use App\Domain\Entity\Event;
use App\Domain\ValueObject\EventId;

interface EventSourceInterface
{
    /**
     * Fetch events from the source since the given event ID.
     *
     * @param EventId|null $since The last processed event ID (null = from beginning)
     * @param int $limit Maximum number of events to fetch (max 1000)
     * @return Event[] Array of events sorted by ID ascending
     * @throws \RuntimeException If source is unavailable
     */
    public function fetchEvents(?EventId $since, int $limit): array;

    /**
     * Get the unique name of this event source.
     *
     * @return string Source identifier (e.g., 'source-1', 'api-events')
     */
    public function getName(): string;

    /**
     * Check if the event source is currently available.
     *
     * @return bool True if source can be queried, false otherwise
     */
    public function isAvailable(): bool;
}
```

#### EventRepositoryInterface

**Location:** `src/Domain/Repository/EventRepositoryInterface.php`

```php
<?php

namespace App\Domain\Repository;

use App\Domain\Entity\Event;
use App\Domain\ValueObject\EventId;

interface EventRepositoryInterface
{
    /**
     * Persist an event to storage.
     *
     * @param Event $event The event to save
     * @throws \RuntimeException If save operation fails
     */
    public function save(Event $event): void;

    /**
     * Find events from a source since a given event ID.
     *
     * @param EventId|null $since Last processed event ID (null = from beginning)
     * @param int $limit Maximum number of events to return
     * @return Event[] Events sorted by ID ascending
     */
    public function findBySince(?EventId $since, int $limit): array;

    /**
     * Get the ID of the last processed event for a source.
     *
     * @param string $sourceName The event source name
     * @return EventId|null The last event ID, or null if no events processed
     */
    public function getLastProcessedId(string $sourceName): ?EventId;
}
```

#### EventLoaderInterface

**Location:** `src/Domain/Service/EventLoaderInterface.php`

```php
<?php

namespace App\Domain\Service;

interface EventLoaderInterface
{
    /**
     * Start loading events from all sources.
     * This method runs in a loop (infinite or limited by configuration).
     */
    public function load(): void;

    /**
     * Stop the event loading process gracefully.
     * Should complete the current iteration before stopping.
     */
    public function stop(): void;
}
```

#### LoaderCoordinationInterface

**Location:** `src/Domain/Service/LoaderCoordinationInterface.php`

```php
<?php

namespace App\Domain\Service;

use App\Domain\ValueObject\EventId;

interface LoaderCoordinationInterface
{
    /**
     * Acquire a distributed lock for a source.
     *
     * @param string $sourceName The event source name
     * @param int $ttl Time-to-live in seconds (lock expiration)
     * @return bool True if lock acquired, false otherwise
     */
    public function acquireLock(string $sourceName, int $ttl): bool;

    /**
     * Release the lock for a source.
     *
     * @param string $sourceName The event source name
     */
    public function releaseLock(string $sourceName): void;

    /**
     * Get the last processed event ID for a source (shared state).
     *
     * @param string $sourceName The event source name
     * @return EventId|null The last processed event ID, or null if none
     */
    public function getLastProcessedId(string $sourceName): ?EventId;

    /**
     * Update the last processed event ID for a source (shared state).
     *
     * @param string $sourceName The event source name
     * @param EventId $eventId The new last processed event ID
     */
    public function updateLastProcessedId(string $sourceName, EventId $eventId): void;
}
```

#### RateLimiterInterface

**Location:** `src/Domain/Service/RateLimiterInterface.php`

```php
<?php

namespace App\Domain\Service;

interface RateLimiterInterface
{
    /**
     * Check if a request to the source is allowed (rate limit check).
     *
     * @param string $sourceName The event source name
     * @return bool True if request is allowed, false if rate limited
     */
    public function canRequest(string $sourceName): bool;

    /**
     * Record that a request was made to the source.
     *
     * @param string $sourceName The event source name
     */
    public function recordRequest(string $sourceName): void;
}
```

---

## Unit Tests

### Test File Structure

```
tests/Unit/Domain/Service/
├── EventSourceInterfaceTest.php
├── EventLoaderInterfaceTest.php
├── LoaderCoordinationInterfaceTest.php
└── RateLimiterInterfaceTest.php

tests/Unit/Domain/Repository/
└── EventRepositoryInterfaceTest.php
```

### Example: EventSourceInterface Test

**File:** `tests/Unit/Domain/Service/EventSourceInterfaceTest.php`

```php
<?php

namespace App\Tests\Unit\Domain\Service;

use App\Domain\Service\EventSourceInterface;
use PHPUnit\Framework\TestCase;

class EventSourceInterfaceTest extends TestCase
{
    public function testInterfaceExists(): void
    {
        $this->assertTrue(interface_exists(EventSourceInterface::class));
    }

    public function testInterfaceHasFetchEventsMethod(): void
    {
        $reflection = new \ReflectionClass(EventSourceInterface::class);
        $this->assertTrue($reflection->hasMethod('fetchEvents'));
        
        $method = $reflection->getMethod('fetchEvents');
        $this->assertCount(2, $method->getParameters());
        $this->assertTrue($method->hasReturnType());
        $this->assertEquals('array', $method->getReturnType()->getName());
    }

    public function testInterfaceHasGetNameMethod(): void
    {
        $reflection = new \ReflectionClass(EventSourceInterface::class);
        $this->assertTrue($reflection->hasMethod('getName'));
        
        $method = $reflection->getMethod('getName');
        $this->assertCount(0, $method->getParameters());
        $this->assertEquals('string', $method->getReturnType()->getName());
    }

    public function testInterfaceHasIsAvailableMethod(): void
    {
        $reflection = new \ReflectionClass(EventSourceInterface::class);
        $this->assertTrue($reflection->hasMethod('isAvailable'));
        
        $method = $reflection->getMethod('isAvailable');
        $this->assertCount(0, $method->getParameters());
        $this->assertEquals('bool', $method->getReturnType()->getName());
    }
}
```

---

## Definition of Done

- [ ] All 5 interfaces defined in `src/Domain/` layer
- [ ] PHPDoc comments complete with parameter descriptions
- [ ] Unit tests verify interface existence and method signatures
- [ ] Code follows PSR-12 coding standards
- [ ] No dependencies on infrastructure layer
- [ ] Code review approved
- [ ] Interfaces follow SOLID principles

---

## Related Stories

- SCRUM-38: Value objects (EventId used in interfaces)
- SCRUM-43: Event entity (Event used in interfaces)
- SCRUM-42: Redis coordination (implements LoaderCoordinationInterface)
- SCRUM-46: Redis rate limiter (implements RateLimiterInterface)
- SCRUM-44: HTTP event source (implements EventSourceInterface)
- SCRUM-39: Event repository (implements EventRepositoryInterface)
- SCRUM-40: Event loader service (implements EventLoaderInterface)

---

## References

- **Jira Story:** https://alexandrubesleaga92.atlassian.net/browse/SCRUM-45
- **Epic:** SCRUM-36
- **PRD:** https://alexandrubesleaga92.atlassian.net/wiki/spaces/~557058cb14f5f9da7c43719417aef1b3e61446/pages/4423681/PRD+-+PROJECT_TEST_KEY1

---

## For Cursor AI Agents

**Implementation Order:**
1. Create interface files in `src/Domain/Service/` and `src/Domain/Repository/`
2. Add PHPDoc comments with detailed descriptions
3. Use strict type hints (PHP 8.1+)
4. Write unit tests to verify interface structure
5. Run PHPStan to ensure type safety

**Key Principles:**
- Interfaces must be in Domain layer (no infrastructure dependencies)
- Use value objects (EventId) instead of primitives
- Follow Interface Segregation Principle (ISP)
- Document all exceptions that implementations may throw
