<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Service;

use App\Infrastructure\Service\InMemoryRateLimiter;
use PHPUnit\Framework\TestCase;

final class InMemoryRateLimiterTest extends TestCase
{
    private InMemoryRateLimiter $rateLimiter;

    protected function setUp(): void
    {
        $this->rateLimiter = new InMemoryRateLimiter();
    }

    public function testFirstRequestIsAlwaysAllowed(): void
    {
        $this->assertTrue($this->rateLimiter->isAllowed('source-1'));
    }

    public function testSecondRequestImmediatelyAfterIsNotAllowed(): void
    {
        $this->rateLimiter->recordRequest('source-1');
        
        // Immediately after, should not be allowed
        $this->assertFalse($this->rateLimiter->isAllowed('source-1'));
    }

    public function testRequestIsAllowedAfter200ms(): void
    {
        $this->rateLimiter->recordRequest('source-1');
        
        // Wait 200ms
        usleep(200 * 1000);
        
        $this->assertTrue($this->rateLimiter->isAllowed('source-1'));
    }

    public function testGetWaitTimeReturnsCorrectValue(): void
    {
        $this->rateLimiter->recordRequest('source-1');
        
        $waitTime = $this->rateLimiter->getWaitTime('source-1');
        
        // Should be approximately 200ms (allow some variance)
        $this->assertGreaterThanOrEqual(190, $waitTime);
        $this->assertLessThanOrEqual(200, $waitTime);
    }

    public function testGetWaitTimeReturnsZeroForFirstRequest(): void
    {
        $this->assertEquals(0, $this->rateLimiter->getWaitTime('source-1'));
    }

    public function testGetWaitTimeDecreaseOverTime(): void
    {
        $this->rateLimiter->recordRequest('source-1');
        
        $waitTime1 = $this->rateLimiter->getWaitTime('source-1');
        
        usleep(50 * 1000); // Wait 50ms
        
        $waitTime2 = $this->rateLimiter->getWaitTime('source-1');
        
        $this->assertLessThan($waitTime1, $waitTime2);
    }

    public function testDifferentSourcesAreIndependent(): void
    {
        $this->rateLimiter->recordRequest('source-1');
        
        // source-2 should still be allowed
        $this->assertTrue($this->rateLimiter->isAllowed('source-2'));
        $this->assertEquals(0, $this->rateLimiter->getWaitTime('source-2'));
    }

    public function testClearResetsAllLimits(): void
    {
        $this->rateLimiter->recordRequest('source-1');
        $this->rateLimiter->recordRequest('source-2');
        
        $this->assertFalse($this->rateLimiter->isAllowed('source-1'));
        $this->assertFalse($this->rateLimiter->isAllowed('source-2'));
        
        $this->rateLimiter->clear();
        
        $this->assertTrue($this->rateLimiter->isAllowed('source-1'));
        $this->assertTrue($this->rateLimiter->isAllowed('source-2'));
    }

    public function testMinIntervalIs200ms(): void
    {
        $this->assertEquals(200, $this->rateLimiter->getMinInterval());
    }
}
