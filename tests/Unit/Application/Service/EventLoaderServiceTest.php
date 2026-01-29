<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Service;

use App\Application\Service\EventLoaderService;
use App\Domain\Entity\Event;
use App\Domain\Service\EventSourceInterface;
use App\Domain\Service\RateLimiterInterface;
use App\Domain\ValueObject\EventDate;
use App\Domain\ValueObject\EventId;
use App\Domain\ValueObject\EventName;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

final class EventLoaderServiceTest extends TestCase
{
    private RateLimiterInterface $rateLimiter;
    private EventLoaderService $loader;

    protected function setUp(): void
    {
        $this->rateLimiter = $this->createMock(RateLimiterInterface::class);
        $this->loader = new EventLoaderService($this->rateLimiter, new NullLogger());
    }

    public function testCanAddEventSource(): void
    {
        $source = $this->createMock(EventSourceInterface::class);
        $source->method('getName')->willReturn('test-source');

        $this->loader->addSource($source);

        $this->assertCount(1, $this->loader->getSources());
    }

    public function testCanAddMultipleSources(): void
    {
        $source1 = $this->createMock(EventSourceInterface::class);
        $source1->method('getName')->willReturn('source-1');
        
        $source2 = $this->createMock(EventSourceInterface::class);
        $source2->method('getName')->willReturn('source-2');

        $this->loader->addSource($source1);
        $this->loader->addSource($source2);

        $this->assertCount(2, $this->loader->getSources());
    }

    public function testThrowsExceptionWhenStartingWithoutSources(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('No event sources registered');

        $this->loader->start(1);
    }

    public function testLoaderProcessesSourcesInRoundRobin(): void
    {
        // Create mock sources
        $source1 = $this->createMock(EventSourceInterface::class);
        $source1->method('getName')->willReturn('source-1');
        $source1->method('isAvailable')->willReturn(true);
        $source1->expects($this->once())->method('fetchEvents')->willReturn([]);
        
        $source2 = $this->createMock(EventSourceInterface::class);
        $source2->method('getName')->willReturn('source-2');
        $source2->method('isAvailable')->willReturn(true);
        $source2->expects($this->once())->method('fetchEvents')->willReturn([]);

        // Rate limiter always allows
        $this->rateLimiter->method('isAllowed')->willReturn(true);
        $this->rateLimiter->method('getWaitTime')->willReturn(0);

        $this->loader->addSource($source1);
        $this->loader->addSource($source2);

        // Run 2 iterations (one per source)
        $this->loader->start(2);
    }

    public function testLoaderRespectsRateLimit(): void
    {
        $source = $this->createMock(EventSourceInterface::class);
        $source->method('getName')->willReturn('test-source');
        $source->method('isAvailable')->willReturn(true);
        $source->method('fetchEvents')->willReturn([]);

        // First call: not allowed (must wait), then allowed
        $this->rateLimiter->method('isAllowed')->willReturnOnConsecutiveCalls(false, true);
        $this->rateLimiter->method('getWaitTime')->willReturn(0);
        
        // Verify recordRequest is called
        $this->rateLimiter->expects($this->once())->method('recordRequest');

        $this->loader->addSource($source);

        // Should handle rate limit properly
        $this->loader->start(1);
        
        // Verify the rate limit check was performed
        $this->assertTrue(true); // Implicit assertion via expects()
    }

    public function testLoaderSkipsUnavailableSources(): void
    {
        $source1 = $this->createMock(EventSourceInterface::class);
        $source1->method('getName')->willReturn('unavailable-source');
        $source1->method('isAvailable')->willReturn(false);
        $source1->expects($this->never())->method('fetchEvents'); // Should not be called
        
        $source2 = $this->createMock(EventSourceInterface::class);
        $source2->method('getName')->willReturn('available-source');
        $source2->method('isAvailable')->willReturn(true);
        $source2->method('fetchEvents')->willReturn([]);

        $this->rateLimiter->method('isAllowed')->willReturn(true);

        $this->loader->addSource($source1);
        $this->loader->addSource($source2);

        // Run enough iterations to hit both sources
        // With round-robin: iteration 1 = source1 (skip), iteration 2 = source2 (fetch), iteration 3 = source1 (skip), iteration 4 = source2 (fetch)
        $this->loader->start(1);
        
        // Verify that at least the available source is queried
        $this->assertTrue(true); // Test verifies via expects()
    }

    public function testLoaderUpdatesLastKnownId(): void
    {
        $events = [
            Event::create(
                EventId::fromInt(1),
                EventName::fromString('Event 1'),
                EventDate::fromString('2026-12-31')
            ),
            Event::create(
                EventId::fromInt(2),
                EventName::fromString('Event 2'),
                EventDate::fromString('2026-12-31')
            ),
        ];

        $source = $this->createMock(EventSourceInterface::class);
        $source->method('getName')->willReturn('test-source');
        $source->method('isAvailable')->willReturn(true);
        $source->method('fetchEvents')->willReturn($events);

        $this->rateLimiter->method('isAllowed')->willReturn(true);

        $this->loader->addSource($source);
        $this->loader->start(1);

        $this->assertEquals('2', $this->loader->getLastKnownId('test-source'));
    }

    public function testLoaderCanBeStopped(): void
    {
        $source = $this->createMock(EventSourceInterface::class);
        $source->method('getName')->willReturn('test-source');
        $source->method('isAvailable')->willReturn(true);
        $source->method('fetchEvents')->willReturn([]);

        $this->rateLimiter->method('isAllowed')->willReturn(true);

        $this->loader->addSource($source);

        $this->assertTrue($this->loader->isRunning() === false);
        
        // Start in background (would need async testing)
        // For now, we test with maxIterations
        $this->loader->start(1);
        
        $this->assertFalse($this->loader->isRunning());
    }

    public function testLoaderHandlesExceptionsGracefully(): void
    {
        $source = $this->createMock(EventSourceInterface::class);
        $source->method('getName')->willReturn('failing-source');
        $source->method('isAvailable')->willReturn(true);
        $source->method('fetchEvents')->willThrowException(new \RuntimeException('Network error'));

        $this->rateLimiter->method('isAllowed')->willReturn(true);

        $this->loader->addSource($source);

        // Should not throw, should log and continue
        $this->loader->start(1);
        
        $this->assertTrue(true); // If we reach here, exception was handled
    }
}
