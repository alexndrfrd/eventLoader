# Epic: Event Loading Mechanism with Distributed Coordination

**Epic Key:** SCRUM-36  
**Project:** PROJECT_TEST_KEY1  
**Priority:** High  
**Status:** To Do

---

## Epic Overview

Design and implement a distributed event loading system that collects events from multiple sources into centralized storage. The system must support parallel execution across multiple instances (potentially on different servers) without conflicts.

## Business Problem

Organizations need a fault-tolerant, scalable event loading system that can:
- Fetch events from multiple remote sources
- Store events in centralized database
- Run multiple loader instances simultaneously
- Prevent duplicate event processing
- Handle rate limiting per source (200ms minimum interval)

## Technical Context

**Current Implementation:**
- Symfony-based PHP application (8.1+)
- Domain-Driven Design (DDD) architecture
- Redis for distributed coordination
- Event sourcing pattern
- Round-robin source processing
- Hexagonal architecture

**Repository:** https://github.com/alexndrfrd/eventLoader

## High-Level Design

### Architecture Layers

```
┌─────────────────────────────────────────────┐
│         Infrastructure Layer                 │
│  (HTTP Client, Redis, DB, Console)          │
└─────────────────────────────────────────────┘
              ↓ (implements)
┌─────────────────────────────────────────────┐
│         Application Layer                    │
│  (EventLoaderService, Commands, Handlers)   │
└─────────────────────────────────────────────┘
              ↓ (uses)
┌─────────────────────────────────────────────┐
│         Domain Layer                         │
│  (Entities, Value Objects, Interfaces)      │
└─────────────────────────────────────────────┘
```

### Core Components

1. **EventLoaderService** (Application Layer)
   - Coordinates round-robin event fetching
   - Enforces rate limiting
   - Manages distributed locks
   - Handles errors gracefully

2. **EventSourceInterface** (Domain Layer)
   - Contract for fetching events from remote sources
   - Implementations: HttpEventSource

3. **EventRepositoryInterface** (Domain Layer)
   - Contract for event persistence
   - Implementations: DoctrineEventRepository

4. **LoaderCoordinationInterface** (Domain Layer)
   - Contract for distributed coordination
   - Implementations: RedisLoaderCoordination

5. **RateLimiterInterface** (Domain Layer)
   - Contract for rate limiting
   - Implementations: RedisRateLimiter

### Data Flow

```
[Event Source API]
       ↓ (HTTP GET)
[HttpEventSource]
       ↓ (Event[])
[EventLoaderService] ←→ [RedisLoaderCoordination] (locks, state)
       ↓                        ↓
[EventRepository]        [RedisRateLimiter] (rate limit check)
       ↓
[Database]
```

### Distributed Coordination

**Redis-based Locking:**
- Lock key: `loader:lock:{sourceName}`
- TTL: 30 seconds
- Atomic SET NX EX operation
- Auto-refresh for long operations

**State Management:**
- Last processed ID: `loader:last_id:{sourceName}`
- Shared across all instances
- Atomic updates

**Rate Limiting:**
- Timestamp key: `loader:rate:{sourceName}`
- 200ms enforcement
- Redis server time for clock sync

## Key Requirements

### Functional Requirements

1. **Event Loading**
   - Fetch up to 1,000 events per request
   - Round-robin processing of sources
   - Track last processed event ID per source
   - Support infinite loop or iteration limit

2. **Distributed Coordination**
   - Multiple instances without conflicts
   - Distributed locks per source
   - Shared state management
   - Graceful handling of instance crashes

3. **Rate Limiting**
   - 200ms minimum interval per source
   - Enforced across all instances
   - Atomic operations

4. **Error Handling**
   - Network failures: Retry with backoff
   - HTTP 4xx: Skip and log
   - HTTP 5xx: Retry then skip
   - Source unavailable: Continue processing

### Non-Functional Requirements

1. **Performance**
   - Process 10,000 events in <3 minutes
   - Support 5+ sources
   - Handle 3+ concurrent instances

2. **Reliability**
   - Zero duplicate events
   - 99.9% uptime
   - Graceful degradation

3. **Scalability**
   - Horizontal scaling
   - No single point of failure
   - Stateless design

## Technology Stack

- **Language:** PHP 8.1+
- **Framework:** Symfony 6.x
- **Database:** PostgreSQL/MySQL (Doctrine ORM)
- **Cache:** Redis 6.x+
- **Testing:** PHPUnit 9.x
- **Containers:** Docker + Docker Compose

## Success Metrics

- **Data Integrity:** 0 duplicate events
- **Rate Limit Compliance:** 100% adherence to 200ms rule
- **Availability:** System runs even with 50% sources down
- **Performance:** 10k events from 5 sources in <3 minutes
- **Scalability:** 3+ concurrent instances work seamlessly

## User Stories

- **SCRUM-45:** Define core interfaces (High)
- **SCRUM-42:** Distributed coordination via Redis (High)
- **SCRUM-46:** Redis-based rate limiting (High)
- **SCRUM-39:** Event repository implementation (High)
- **SCRUM-40:** EventLoaderService implementation (High)
- **SCRUM-41:** Comprehensive testing (High)
- **SCRUM-44:** HTTP event source (Medium)
- **SCRUM-47:** Console command (Medium)
- **SCRUM-43:** Event entity (Medium)
- **SCRUM-38:** Value objects (Medium)

## Timeline

- **Week 1:** Core interfaces & domain models
- **Week 2:** Infrastructure implementation
- **Week 3:** Application services
- **Week 4:** Testing & deployment

**Total:** 4 weeks

## References

- **Jira Epic:** https://alexandrubesleaga92.atlassian.net/browse/SCRUM-36
- **PRD:** https://alexandrubesleaga92.atlassian.net/wiki/spaces/~557058cb14f5f9da7c43719417aef1b3e61446/pages/4423681/PRD+-+PROJECT_TEST_KEY1
- **Repository:** https://github.com/alexndrfrd/eventLoader

---

## For Cursor AI Agents

This Epic represents a distributed event sourcing system. When implementing stories:

1. **Follow DDD principles:** Domain layer must be infrastructure-agnostic
2. **Use dependency injection:** All dependencies through constructor
3. **Implement interfaces first:** Define contracts before implementations
4. **Test-driven development:** Write tests before implementation
5. **Redis patterns:** Use atomic operations (SET NX EX, INCR, etc.)
6. **Error handling:** Always handle Redis/DB failures gracefully
7. **Logging:** Log all errors with context (source name, event ID, timestamp)
8. **Performance:** Batch operations, use indexes, avoid N+1 queries

### Code Patterns

**Value Object Example:**
```php
final readonly class EventId
{
    public function __construct(private int $value)
    {
        if ($value <= 0) {
            throw new \InvalidArgumentException('EventId must be positive');
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
}
```

**Redis Lock Pattern:**
```php
// Acquire lock
$acquired = $redis->set(
    "loader:lock:{$sourceName}",
    $instanceId,
    ['NX', 'EX' => 30]
);

if ($acquired) {
    try {
        // Process events
    } finally {
        $redis->del("loader:lock:{$sourceName}");
    }
}
```

**Rate Limit Pattern:**
```php
$lastRequest = $redis->get("loader:rate:{$sourceName}");
if ($lastRequest && (microtime(true) - $lastRequest) < 0.2) {
    return false; // Rate limit not expired
}
$redis->set("loader:rate:{$sourceName}", microtime(true), ['EX' => 1]);
return true;
```
