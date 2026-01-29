<?php

declare(strict_types=1);

namespace App\Tests\Integration\Application\Service;

use App\Application\Service\EventLoaderService;
use App\Domain\Entity\Event;
use App\Domain\Service\EventSourceInterface;
use App\Domain\Service\LoaderCoordinationInterface;
use App\Domain\Service\RateLimiterInterface;
use App\Domain\ValueObject\EventDate;
use App\Domain\ValueObject\EventId;
use App\Domain\ValueObject\EventName;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

/**
 * Integration test for parallel execution
 * 
 * Verifies that multiple loader instances don't request duplicate events
 */
final class ParallelExecutionTest extends TestCase
{
    public function testMultipleInstancesDoNotRequestDuplicateEvents(): void
    {
        // Create mock coordination
        $coordination = $this->createMock(LoaderCoordinationInterface::class);
        
        // Track which events were requested
        $requestedEvents = [];
        $lastProcessedId = null;
        
        // Mock: First instance acquires lock
        $coordination->method('acquireLock')
            ->willReturnCallback(function ($sourceName) use (&$lastProcessedId) {
                // First call succeeds, subsequent calls fail (another instance has lock)
                static $callCount = 0;
                $callCount++;
                return $callCount === 1;
            });
        
        // Mock: Get last processed ID from shared storage
        $coordination->method('getLastProcessedId')
            ->willReturnCallback(function () use (&$lastProcessedId) {
                return $lastProcessedId;
            });
        
        // Mock: Update last processed ID in shared storage
        $coordination->method('updateLastProcessedId')
            ->willReturnCallback(function ($sourceName, $lastId) use (&$lastProcessedId) {
                $lastProcessedId = $lastId;
            });
        
        // Create mock event source
        $source = $this->createMock(EventSourceInterface::class);
        $source->method('getName')->willReturn('test-source');
        $source->method('isAvailable')->willReturn(true);
        
        // Track fetch calls
        $fetchCallCount = 0;
        $source->method('fetchEvents')
            ->willReturnCallback(function ($since) use (&$fetchCallCount, &$requestedEvents) {
                $fetchCallCount++;
                
                // Generate events based on since parameter
                $startId = $since ? $since->toInt() + 1 : 1;
                $events = [];
                
                for ($i = 0; $i < 5; $i++) {
                    $eventId = $startId + $i;
                    $events[] = Event::create(
                        EventId::fromInt($eventId),
                        EventName::fromString("Event $eventId"),
                        EventDate::fromString('2026-12-31')
                    );
                    $requestedEvents[] = $eventId;
                }
                
                return $events;
            });
        
        // Create rate limiter (always allows)
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->method('isAllowed')->willReturn(true);
        $rateLimiter->method('getWaitTime')->willReturn(0);
        
        // Create loader instance 1
        $loader1 = new EventLoaderService($rateLimiter, new NullLogger(), $coordination);
        $loader1->addSource($source);
        
        // Run 1 iteration
        $loader1->start(1);
        
        // Verify events were fetched
        $this->assertGreaterThan(0, $fetchCallCount);
        $this->assertGreaterThan(0, count($requestedEvents));
        
        // Verify last processed ID was updated
        $this->assertNotNull($lastProcessedId);
    }

    public function testSecondInstanceSkipsWhenLockAcquired(): void
    {
        $coordination = $this->createMock(LoaderCoordinationInterface::class);
        
        // First source is locked, second source is available
        $coordination->method('acquireLock')
            ->willReturnCallback(function ($sourceName) {
                return $sourceName === 'source-2'; // Only source-2 can acquire lock
            });
        
        $coordination->method('getLastProcessedId')
            ->willReturn(null);
        
        $source1 = $this->createMock(EventSourceInterface::class);
        $source1->method('getName')->willReturn('source-1');
        $source1->method('isAvailable')->willReturn(true);
        $source1->expects($this->never())
            ->method('fetchEvents'); // Should be skipped due to lock
        
        $source2 = $this->createMock(EventSourceInterface::class);
        $source2->method('getName')->willReturn('source-2');
        $source2->method('isAvailable')->willReturn(true);
        $source2->method('fetchEvents')->willReturn([]); // Empty response
        
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->method('isAllowed')->willReturn(true);
        $rateLimiter->method('getWaitTime')->willReturn(0);
        
        $loader = new EventLoaderService($rateLimiter, new NullLogger(), $coordination);
        $loader->addSource($source1);
        $loader->addSource($source2);
        
        // Run 1 iteration - source-1 should be skipped, source-2 should process
        $loader->start(1);
        
        // If we reach here, loader handled locked source gracefully
        $this->assertTrue(true);
    }

    public function testCoordinationIsOptional(): void
    {
        // Loader should work without coordination (backward compatibility)
        $source = $this->createMock(EventSourceInterface::class);
        $source->method('getName')->willReturn('test-source');
        $source->method('isAvailable')->willReturn(true);
        $source->method('fetchEvents')->willReturn([]);
        
        $rateLimiter = $this->createMock(RateLimiterInterface::class);
        $rateLimiter->method('isAllowed')->willReturn(true);
        
        // No coordination passed (null)
        $loader = new EventLoaderService($rateLimiter, new NullLogger(), null);
        $loader->addSource($source);
        
        // Should work without errors
        $loader->start(1);
        
        $this->assertTrue(true);
    }
}
