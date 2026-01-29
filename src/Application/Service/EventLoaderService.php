<?php

declare(strict_types=1);

namespace App\Application\Service;

use App\Domain\Service\EventSourceInterface;
use App\Domain\Service\LoaderCoordinationInterface;
use App\Domain\Service\RateLimiterInterface;
use App\Domain\ValueObject\EventId;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class EventLoaderService
{
    /**
     * @param EventSourceInterface[] $sources
     */
    private array $sources = [];
    
    /**
     * @var array<string, string|null> Tracks last known ID per source (local cache)
     * Note: Shared state is managed via LoaderCoordinationInterface
     */
    private array $lastKnownIds = [];
    
    private bool $running = false;
    private ?SymfonyStyle $consoleOutput = null;

    public function __construct(
        private readonly RateLimiterInterface $rateLimiter,
        private readonly LoggerInterface $logger,
        private readonly ?LoaderCoordinationInterface $coordination = null
    ) {
    }

    public function setConsoleOutput(SymfonyStyle $output): void
    {
        $this->consoleOutput = $output;
    }

    public function addSource(EventSourceInterface $source): void
    {
        $this->sources[] = $source;
        $this->lastKnownIds[$source->getName()] = null;
        
        $this->logger->info('Event source registered', [
            'source' => $source->getName(),
        ]);
    }

    /**
     * Get all registered sources
     * 
     * @return EventSourceInterface[]
     */
    public function getSources(): array
    {
        return $this->sources;
    }

    /**
     * Start the loader (infinite loop with round-robin)
     * 
     * @param int $maxIterations Max iterations (0 = infinite, for testing use finite)
     */
    public function start(int $maxIterations = 0): void
    {
        $this->validateSources();
        $this->running = true;
        
        $this->logStart($maxIterations);
        
        $iteration = 0;
        $sourceIndex = 0;

        while ($this->running) {
            $source = $this->sources[$sourceIndex];
            $sourceName = $source->getName();
            
            $this->waitForRateLimit($source);
            
            if (!$this->isSourceAvailable($source)) {
                $sourceIndex = $this->moveToNextSource($sourceIndex);
                continue;
            }

            if ($this->coordination !== null && !$this->coordination->acquireLock($sourceName)) {
                $message = sprintf(
                    '[%s] Source %s is locked by another instance, skipping',
                    date('H:i:s'),
                    $sourceName
                );
                
                $this->logger->debug('Source is being processed by another instance, skipping', [
                    'source' => $sourceName,
                ]);
                
                if ($this->consoleOutput !== null) {
                    $this->consoleOutput->writeln("  <comment>⊘</comment> $message");
                }
                
                $sourceIndex = $this->moveToNextSource($sourceIndex);
                continue;
            }

            try {
                // Record request timestamp BEFORE fetch (for rate limiting)
                // This ensures 200ms interval is measured from request start
                $this->rateLimiter->recordRequest($sourceName);
                
                $this->fetchAndProcessFromSource($source);
            } finally {
                if ($this->coordination !== null) {
                    $this->coordination->releaseLock($sourceName);
                }
            }
            
            $sourceIndex = $this->moveToNextSource($sourceIndex);
            $iteration++;
            
            if ($this->shouldStop($iteration, $maxIterations)) {
                $this->stop();
            }
        }

        $this->logger->info('Event loader stopped');
    }

    public function stop(): void
    {
        $this->running = false;
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    /**
     * Process fetched events (override in subclass or inject handler)
     * 
     * @param string $sourceName
     * @param Event[] $events
     */
    protected function processEvents(string $sourceName, array $events): void
    {
        // Default implementation: just log
        // In production, this would save to database, publish to message queue, etc
        foreach ($events as $event) {
            $this->logger->debug('Processing event', [
                'source' => $sourceName,
                'event_id' => $event->getId()->toString(),
                'event_name' => $event->getName()->toString(),
            ]);
        }
    }

    public function getLastKnownId(string $sourceName): ?string
    {
        // Check coordination first (shared state), then local cache
        if ($this->coordination !== null) {
            $coordinatedId = $this->coordination->getLastProcessedId($sourceName);
            if ($coordinatedId !== null) {
                return $coordinatedId;
            }
        }
        
        return $this->lastKnownIds[$sourceName] ?? null;
    }

    private function validateSources(): void
    {
        if (empty($this->sources)) {
            throw new \RuntimeException('No event sources registered');
        }
    }

    private function logStart(int $maxIterations): void
    {
        $this->logger->info('Event loader started', [
            'sources' => count($this->sources),
            'max_iterations' => $maxIterations === 0 ? 'infinite' : $maxIterations,
        ]);
    }

    /**
     * Wait for rate limit if needed
     */
    private function waitForRateLimit(EventSourceInterface $source): void
    {
        $sourceName = $source->getName();
        
        if (!$this->rateLimiter->isAllowed($sourceName)) {
            $waitTime = $this->rateLimiter->getWaitTime($sourceName);
            
            $message = sprintf(
                '[%s] Rate limit: waiting %dms before request to %s',
                date('H:i:s'),
                $waitTime,
                $sourceName
            );
            
            $this->logger->debug('Rate limit: waiting before next request', [
                'source' => $sourceName,
                'wait_ms' => $waitTime,
            ]);

            if ($this->consoleOutput !== null) {
                $this->consoleOutput->writeln("  <comment>$message</comment>");
            }

            if ($waitTime > 0) {
                usleep($waitTime * 1000);
            }
        } else {
            if ($this->consoleOutput !== null) {
                $message = sprintf(
                    '[%s] Rate limit OK for %s',
                    date('H:i:s'),
                    $sourceName
                );
                $this->consoleOutput->writeln("  <info>$message</info>");
            }
        }
    }

    /**
     * Check if source is available
     */
    private function isSourceAvailable(EventSourceInterface $source): bool
    {
        if (!$source->isAvailable()) {
            $this->logger->warning('Event source unavailable, skipping', [
                'source' => $source->getName(),
            ]);
            return false;
        }
        
        return true;
    }

    private function fetchAndProcessFromSource(EventSourceInterface $source): void
    {
        $sourceName = $source->getName();
        
        try {
            $events = $this->fetchEventsFromSource($source);
            
            if (!empty($events)) {
                $this->handleFetchedEvents($sourceName, $events);
            } else {
                $this->logNoNewEvents($sourceName);
            }
        } catch (\Exception $e) {
            $this->logFetchError($sourceName, $e);
        }
    }

    private function fetchEventsFromSource(EventSourceInterface $source): array
    {
        $sourceName = $source->getName();
        
        if ($this->coordination !== null) {
            $lastId = $this->coordination->getLastProcessedId($sourceName);
        } else {
            // Fallback to local cache
            $lastId = $this->lastKnownIds[$sourceName] ?? null;
        }
        
        $since = $lastId ? EventId::fromString($lastId) : null;
        $sinceStr = $since ? $since->toString() : 'none';
        
        $events = $source->fetchEvents($since, 1000);

        if ($this->consoleOutput !== null) {
            $maxLimit = 1000;
            $this->consoleOutput->writeln("  <comment>-></comment> Fetching from $sourceName (since: $sinceStr, limit: $maxLimit, found: " . count($events) . " events)");
        }
        
        return $events;
    }

    private function handleFetchedEvents(string $sourceName, array $events): void
    {
        $this->processEvents($sourceName, $events);
        $this->updateLastKnownId($sourceName, $events);
        $this->logEventsFetched($sourceName, $events);
    }

    private function updateLastKnownId(string $sourceName, array $events): void
    {
        if (empty($events)) {
            return;
        }
        
        $lastEvent = end($events);
        $lastId = $lastEvent->getId()->toString();
        
        if ($this->coordination !== null) {
            $this->coordination->updateLastProcessedId($sourceName, $lastId);
        }
        
        // Also update local cache
        $this->lastKnownIds[$sourceName] = $lastId;
    }

    private function logEventsFetched(string $sourceName, array $events): void
    {
        $lastId = $this->lastKnownIds[$sourceName] ?? 'none';
        $message = sprintf(
            '[%s] Fetched %d events from %s (last_id: %s)',
            date('H:i:s'),
            count($events),
            $sourceName,
            $lastId
        );
        
        $this->logger->info('Events fetched from source', [
            'source' => $sourceName,
            'count' => count($events),
            'last_id' => $lastId,
        ]);
        
        if ($this->consoleOutput !== null) {
            $this->consoleOutput->writeln("  <info>✓</info> $message");
        }
    }

    private function logNoNewEvents(string $sourceName): void
    {
        if ($this->coordination !== null) {
            $lastId = $this->coordination->getLastProcessedId($sourceName);
        } else {
            $lastId = $this->lastKnownIds[$sourceName] ?? null;
        }
        
        $lastIdStr = $lastId ?? 'none';
        $message = sprintf(
            '[%s] No new events from %s (since: %s)',
            date('H:i:s'),
            $sourceName,
            $lastIdStr
        );
        
        $this->logger->debug('No new events from source', [
            'source' => $sourceName,
            'since' => $lastIdStr,
        ]);
        
        if ($this->consoleOutput !== null) {
            $this->consoleOutput->writeln("  <comment>○</comment> $message");
        }
    }

    private function logFetchError(string $sourceName, \Exception $e): void
    {
        $this->logger->error('Failed to fetch events from source', [
            'source' => $sourceName,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
        ]);
    }

    private function moveToNextSource(int $currentIndex): int
    {
        return ($currentIndex + 1) % count($this->sources);
    }

    private function shouldStop(int $iteration, int $maxIterations): bool
    {
        if ($maxIterations > 0 && $iteration >= $maxIterations) {
            $this->logger->info('Max iterations reached, stopping loader', [
                'iterations' => $iteration,
            ]);
            return true;
        }
        
        return false;
    }
}
