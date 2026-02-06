# SCRUM-40: EventLoaderService

**Priority:** High | **Points:** 8 | **Epic:** SCRUM-36

## Story
Implement EventLoaderService that orchestrates event fetching with distributed coordination.

## Implementation
```php
class EventLoaderService implements EventLoaderInterface {
    public function __construct(
        private iterable $sources,
        private EventRepositoryInterface $repository,
        private LoaderCoordinationInterface $coordination,
        private RateLimiterInterface $rateLimiter,
        private LoggerInterface $logger
    ) {}

    public function load(): void {
        while ($this->shouldContinue()) {
            foreach ($this->sources as $source) {
                $this->processSource($source);
            }
        }
    }

    private function processSource(EventSourceInterface $source): void {
        // 1. Check if source available
        if (!$source->isAvailable()) return;
        
        // 2. Check rate limit (200ms)
        if (!$this->rateLimiter->canRequest($source->getName())) return;
        
        // 3. Acquire distributed lock
        if (!$this->coordination->acquireLock($source->getName(), 30)) return;
        
        try {
            // 4. Fetch events
            $lastId = $this->coordination->getLastProcessedId($source->getName());
            $events = $source->fetchEvents($lastId, 1000);
            
            // 5. Save events and update state
            foreach ($events as $event) {
                $this->repository->save($event);
                $this->coordination->updateLastProcessedId($source->getName(), $event->getId());
            }
            
            // 6. Record request for rate limiting
            $this->rateLimiter->recordRequest($source->getName());
        } finally {
            $this->coordination->releaseLock($source->getName());
        }
    }
}
```

## Tests
- Unit tests with mocked dependencies
- Integration: Real Redis + DB
- Test concurrent instances
- Test error handling

## Links
Jira: https://alexandrubesleaga92.atlassian.net/browse/SCRUM-40
