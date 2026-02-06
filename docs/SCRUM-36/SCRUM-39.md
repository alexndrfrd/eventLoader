# As a developer, I want to implement event repository so that events can be persisted and retrieved efficiently

**Epic:** SCRUM-36  
**Story Key:** SCRUM-39  
**Priority:** High  
**Story Points:** 5

---

## Description

Implement the EventRepositoryInterface using Doctrine ORM to persist and retrieve events efficiently. The repository must ensure data integrity with composite unique constraints and support efficient queries for event loading.

## Acceptance Criteria

✅ Implement EventRepositoryInterface
✅ Support saving events (prevent duplicates by ID + source)
✅ Fetch events since a given ID for a source
✅ Get last processed event ID per source
✅ Support pagination (limit parameter)
✅ Use database transactions for consistency
✅ Handle database errors gracefully

---

## Technical Implementation

### File Structure

```
src/Infrastructure/Persistence/
├── DoctrineEventRepository.php
└── InMemoryEventRepository.php (for testing)

src/Entity/
└── EventEntity.php (Doctrine entity mapping)

migrations/
└── Version20240101000000.php (create events table)
```

### Doctrine Entity Mapping

**File:** `src/Entity/EventEntity.php`

```php
<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'events')]
#[ORM\Index(columns: ['source_name', 'event_id'])]
#[ORM\UniqueConstraint(name: 'unique_event_source', columns: ['event_id', 'source_name'])]
class EventEntity
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'integer')]
    private int $eventId;

    #[ORM\Column(type: 'string', length: 255)]
    private string $name;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $date;

    #[ORM\Column(type: 'string', length: 255)]
    private string $sourceName;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    public function __construct(
        int $eventId,
        string $name,
        \DateTimeImmutable $date,
        string $sourceName
    ) {
        $this->eventId = $eventId;
        $this->name = $name;
        $this->date = $date;
        $this->sourceName = $sourceName;
        $this->createdAt = new \DateTimeImmutable();
    }

    // Getters...
    public function getId(): ?int { return $this->id; }
    public function getEventId(): int { return $this->eventId; }
    public function getName(): string { return $this->name; }
    public function getDate(): \DateTimeImmutable { return $this->date; }
    public function getSourceName(): string { return $this->sourceName; }
    public function getCreatedAt(): \DateTimeImmutable { return $this->createdAt; }
}
```

### Repository Implementation

**File:** `src/Infrastructure/Persistence/DoctrineEventRepository.php`

```php
<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Event;
use App\Domain\Repository\EventRepositoryInterface;
use App\Domain\ValueObject\EventId;
use App\Domain\ValueObject\EventName;
use App\Domain\ValueObject\EventDate;
use App\Entity\EventEntity;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;

class DoctrineEventRepository implements EventRepositoryInterface
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private LoggerInterface $logger
    ) {}

    public function save(Event $event): void
    {
        try {
            $entity = new EventEntity(
                $event->getId()->getValue(),
                $event->getName()->getValue(),
                $event->getDate()->getValue(),
                $event->getSourceName()
            );

            $this->entityManager->persist($entity);
            $this->entityManager->flush();

            $this->logger->info('Event saved', [
                'event_id' => $event->getId()->getValue(),
                'source' => $event->getSourceName()
            ]);
        } catch (\Doctrine\DBAL\Exception\UniqueConstraintViolationException $e) {
            // Duplicate event - log and continue (idempotent operation)
            $this->logger->warning('Duplicate event skipped', [
                'event_id' => $event->getId()->getValue(),
                'source' => $event->getSourceName()
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Failed to save event', [
                'error' => $e->getMessage(),
                'event_id' => $event->getId()->getValue()
            ]);
            throw new \RuntimeException('Database error while saving event', 0, $e);
        }
    }

    public function findBySince(?EventId $since, int $limit): array
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e')
            ->from(EventEntity::class, 'e')
            ->orderBy('e.eventId', 'ASC')
            ->setMaxResults($limit);

        if ($since !== null) {
            $qb->where('e.eventId > :since')
                ->setParameter('since', $since->getValue());
        }

        $entities = $qb->getQuery()->getResult();

        return array_map(
            fn(EventEntity $entity) => Event::create(
                new EventId($entity->getEventId()),
                new EventName($entity->getName()),
                new EventDate($entity->getDate()),
                $entity->getSourceName()
            ),
            $entities
        );
    }

    public function getLastProcessedId(string $sourceName): ?EventId
    {
        $qb = $this->entityManager->createQueryBuilder();
        $result = $qb->select('MAX(e.eventId)')
            ->from(EventEntity::class, 'e')
            ->where('e.sourceName = :source')
            ->setParameter('source', $sourceName)
            ->getQuery()
            ->getSingleScalarResult();

        return $result ? new EventId((int) $result) : null;
    }
}
```

### In-Memory Repository (Testing)

**File:** `src/Infrastructure/Persistence/InMemoryEventRepository.php`

```php
<?php

namespace App\Infrastructure\Persistence;

use App\Domain\Entity\Event;
use App\Domain\Repository\EventRepositoryInterface;
use App\Domain\ValueObject\EventId;

class InMemoryEventRepository implements EventRepositoryInterface
{
    /** @var Event[] */
    private array $events = [];

    public function save(Event $event): void
    {
        $key = $event->getSourceName() . '-' . $event->getId()->getValue();
        
        if (isset($this->events[$key])) {
            // Duplicate - idempotent operation
            return;
        }

        $this->events[$key] = $event;
    }

    public function findBySince(?EventId $since, int $limit): array
    {
        $filtered = array_filter(
            $this->events,
            fn(Event $e) => $since === null || $e->getId()->isGreaterThan($since)
        );

        usort($filtered, fn(Event $a, Event $b) => 
            $a->getId()->getValue() <=> $b->getId()->getValue()
        );

        return array_slice($filtered, 0, $limit);
    }

    public function getLastProcessedId(string $sourceName): ?EventId
    {
        $sourceEvents = array_filter(
            $this->events,
            fn(Event $e) => $e->getSourceName() === $sourceName
        );

        if (empty($sourceEvents)) {
            return null;
        }

        usort($sourceEvents, fn(Event $a, Event $b) => 
            $b->getId()->getValue() <=> $a->getId()->getValue()
        );

        return $sourceEvents[0]->getId();
    }

    // Test helpers
    public function clear(): void
    {
        $this->events = [];
    }

    public function count(): int
    {
        return count($this->events);
    }
}
```

---

## Database Migration

**File:** `migrations/Version20240101000000.php`

```php
<?php

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20240101000000 extends AbstractMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('
            CREATE TABLE events (
                id SERIAL PRIMARY KEY,
                event_id INT NOT NULL,
                name VARCHAR(255) NOT NULL,
                date TIMESTAMP NOT NULL,
                source_name VARCHAR(255) NOT NULL,
                created_at TIMESTAMP NOT NULL,
                CONSTRAINT unique_event_source UNIQUE (event_id, source_name)
            )
        ');

        $this->addSql('
            CREATE INDEX idx_source_event ON events (source_name, event_id)
        ');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('DROP TABLE events');
    }
}
```

---

## Unit Tests

**File:** `tests/Unit/Infrastructure/Persistence/InMemoryEventRepositoryTest.php`

```php
<?php

namespace App\Tests\Unit\Infrastructure\Persistence;

use App\Domain\Entity\Event;
use App\Domain\ValueObject\EventId;
use App\Domain\ValueObject\EventName;
use App\Domain\ValueObject\EventDate;
use App\Infrastructure\Persistence\InMemoryEventRepository;
use PHPUnit\Framework\TestCase;

class InMemoryEventRepositoryTest extends TestCase
{
    private InMemoryEventRepository $repository;

    protected function setUp(): void
    {
        $this->repository = new InMemoryEventRepository();
    }

    public function testCanSaveEvent(): void
    {
        $event = Event::create(
            new EventId(1),
            new EventName('TestEvent'),
            EventDate::now(),
            'source-1'
        );

        $this->repository->save($event);
        $this->assertEquals(1, $this->repository->count());
    }

    public function testSavingDuplicateEventIsIdempotent(): void
    {
        $event = Event::create(
            new EventId(1),
            new EventName('TestEvent'),
            EventDate::now(),
            'source-1'
        );

        $this->repository->save($event);
        $this->repository->save($event); // Duplicate
        
        $this->assertEquals(1, $this->repository->count());
    }

    public function testFindBySinceReturnsEventsAfterGivenId(): void
    {
        $this->repository->save(Event::create(
            new EventId(1), new EventName('Event1'), EventDate::now(), 'source-1'
        ));
        $this->repository->save(Event::create(
            new EventId(2), new EventName('Event2'), EventDate::now(), 'source-1'
        ));
        $this->repository->save(Event::create(
            new EventId(3), new EventName('Event3'), EventDate::now(), 'source-1'
        ));

        $results = $this->repository->findBySince(new EventId(1), 10);
        
        $this->assertCount(2, $results);
        $this->assertEquals(2, $results[0]->getId()->getValue());
        $this->assertEquals(3, $results[1]->getId()->getValue());
    }

    public function testGetLastProcessedIdReturnsHighestId(): void
    {
        $this->repository->save(Event::create(
            new EventId(5), new EventName('Event5'), EventDate::now(), 'source-1'
        ));
        $this->repository->save(Event::create(
            new EventId(10), new EventName('Event10'), EventDate::now(), 'source-1'
        ));

        $lastId = $this->repository->getLastProcessedId('source-1');
        
        $this->assertNotNull($lastId);
        $this->assertEquals(10, $lastId->getValue());
    }

    public function testGetLastProcessedIdReturnsNullForUnknownSource(): void
    {
        $lastId = $this->repository->getLastProcessedId('unknown-source');
        $this->assertNull($lastId);
    }
}
```

---

## Integration Tests

**File:** `tests/Integration/Infrastructure/Persistence/DoctrineEventRepositoryTest.php`

```php
<?php

namespace App\Tests\Integration\Infrastructure\Persistence;

use App\Domain\Entity\Event;
use App\Domain\ValueObject\EventId;
use App\Domain\ValueObject\EventName;
use App\Domain\ValueObject\EventDate;
use App\Infrastructure\Persistence\DoctrineEventRepository;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class DoctrineEventRepositoryTest extends KernelTestCase
{
    private DoctrineEventRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->repository = self::getContainer()->get(DoctrineEventRepository::class);
        
        // Clear database
        $em = self::getContainer()->get('doctrine.orm.entity_manager');
        $em->createQuery('DELETE FROM App\\Entity\\EventEntity')->execute();
    }

    public function testCanPersistAndRetrieveEvent(): void
    {
        $event = Event::create(
            new EventId(100),
            new EventName('TestEvent'),
            EventDate::now(),
            'test-source'
        );

        $this->repository->save($event);

        $lastId = $this->repository->getLastProcessedId('test-source');
        $this->assertEquals(100, $lastId->getValue());
    }

    public function testDuplicateEventDoesNotThrowException(): void
    {
        $event = Event::create(
            new EventId(1),
            new EventName('DuplicateTest'),
            EventDate::now(),
            'source-1'
        );

        $this->repository->save($event);
        $this->repository->save($event); // Should not throw

        $this->assertTrue(true); // If we get here, test passes
    }
}
```

---

## Definition of Done

- [ ] DoctrineEventRepository implements EventRepositoryInterface
- [ ] Database migration created
- [ ] Unique constraint enforced (event_id + source_name)
- [ ] Index on (source_name, event_id) created
- [ ] Unit tests with InMemoryRepository pass
- [ ] Integration tests with real database pass
- [ ] Duplicate handling tested
- [ ] Performance tested with 10k+ events
- [ ] Code review approved

---

## For Cursor AI Agents

**Key Implementation Points:**
- Use Doctrine attributes (PHP 8.1+) for mapping
- Handle UniqueConstraintViolationException gracefully
- Always log database operations
- Use QueryBuilder for complex queries
- Implement InMemoryRepository for fast unit tests
- Use transactions for batch operations
