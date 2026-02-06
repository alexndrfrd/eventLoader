# SCRUM-40: EventLoaderService Implementation

**Priority:** High | **Points:** 8

Implement the core EventLoaderService that orchestrates event fetching from multiple sources with distributed coordination, rate limiting, and error handling.

## Key Implementation

```php
class EventLoaderService implements EventLoaderInterface
{
    public function __construct(
        private iterable $sources, // EventSourceInterface[]
        private EventRepositoryInterface $repository,
        private LoaderCoordinationInterface $coordination,
        private RateLimiterInterface $rateLimiter,
        private LoggerInterface $logger,
        private int $maxIterations = 0
    ) {}

    public function load(): void
    {
        $iteration = 0;
        while ($this->shouldContinue($iteration)) {
            foreach ($this->sources as $source) {
                $this->processSource($source);
            }
            $iteration++;
        }
    }

    private function processSource(EventSourceInterface $source): void
    {
        if (!$source->isAvailable()) {
            $this->logger->warning('Source unavailable', ['source' => $source->getName()]);
            return;
        }

        if (!$this->rateLimiter->canRequest($source->getName())) {
            return; // Rate limit not expired
        }

        if (!$this->coordination->acquireLock($source->getName(), 30)) {
            return; // Another instance is processing this source
        }

        try {
            $lastId = $this->coordination->getLastProcessedId($source->getName());
            $events = $source->fetchEvents($lastId, 1000);

            foreach ($events as $event) {
                $this->repository->save($event);
                $this->coordination->updateLastProcessedId($source->getName(), $event->getId());
            }

            $this->rateLimiter->recordRequest($source->getName());
            $this->logger->info('Processed events', [
                'source' => $source->getName(),
                'count' => count($events)
            ]);
        } catch (\Exception $e) {
            $this->logger->error('Error processing source', [
                'source' => $source->getName(),
                'error' => $e->getMessage()
            ]);
        } finally {
            $this->coordination->releaseLock($source->getName());
        }
    }
}
```

## Tests

- Unit tests with mocked dependencies
- Integration tests with real Redis and DB
- Test race conditions and failures

See full implementation in repository.
