<?php

declare(strict_types=1);

namespace App\Tests\Unit\Infrastructure\Service;

use App\Infrastructure\Service\RedisLoaderCoordination;
use PHPUnit\Framework\TestCase;

/**
 * Unit tests for RedisLoaderCoordination
 * 
 * Uses mocks to test coordination logic without requiring Redis
 * 
 * Note: Requires Predis to be installed (composer require predis/predis)
 * These tests will be skipped if Predis is not available
 */
final class RedisLoaderCoordinationTest extends TestCase
{
    private $redis;
    private RedisLoaderCoordination $coordination;

    protected function setUp(): void
    {
        // Create a mock object that implements the methods RedisLoaderCoordination needs
        // RedisLoaderCoordination uses object type hint, so we create a mock with the required methods
        $this->redis = $this->getMockBuilder(\stdClass::class)
            ->addMethods(['set', 'get', 'del'])
            ->getMock();
        
        $this->coordination = new RedisLoaderCoordination($this->redis, 'instance-1');
    }

    public function testAcquireLockReturnsTrueWhenLockAvailable(): void
    {
        // First call: acquire lock with NX (returns true)
        $this->redis->expects($this->exactly(2))
            ->method('set')
            ->willReturnCallback(function ($key, $value, ...$args) {
                if ($key === 'loader_lock:test-source' && count($args) === 3 && $args[2] === 'NX') {
                    return true; // Lock acquired
                }
                if ($key === 'loader_instance:test-source') {
                    return true; // Instance ID set
                }
                return false;
            });

        $result = $this->coordination->acquireLock('test-source');
        
        $this->assertTrue($result);
    }

    public function testAcquireLockReturnsFalseWhenLockTaken(): void
    {
        $this->redis->expects($this->once())
            ->method('set')
            ->with(
                'loader_lock:test-source',
                'instance-1',
                'EX',
                60,
                'NX'
            )
            ->willReturn(false);

        $result = $this->coordination->acquireLock('test-source');
        
        $this->assertFalse($result);
    }

    public function testReleaseLockOnlyReleasesIfOwned(): void
    {
        // Lock is owned by this instance
        $this->redis->expects($this->once())
            ->method('get')
            ->with('loader_lock:test-source')
            ->willReturn('instance-1');

        $this->redis->expects($this->once())
            ->method('del')
            ->with(['loader_lock:test-source', 'loader_instance:test-source']);

        $this->coordination->releaseLock('test-source');
    }

    public function testReleaseLockDoesNotReleaseIfNotOwned(): void
    {
        // Lock is owned by another instance
        $this->redis->expects($this->once())
            ->method('get')
            ->with('loader_lock:test-source')
            ->willReturn('instance-2');

        $this->redis->expects($this->never())
            ->method('del');

        $this->coordination->releaseLock('test-source');
    }

    public function testGetLastProcessedIdReturnsNullWhenNotSet(): void
    {
        $this->redis->expects($this->once())
            ->method('get')
            ->with('last_processed_id:test-source')
            ->willReturn(null);

        $result = $this->coordination->getLastProcessedId('test-source');
        
        $this->assertNull($result);
    }

    public function testGetLastProcessedIdReturnsStringWhenSet(): void
    {
        $this->redis->expects($this->once())
            ->method('get')
            ->with('last_processed_id:test-source')
            ->willReturn('1000');

        $result = $this->coordination->getLastProcessedId('test-source');
        
        $this->assertEquals('1000', $result);
    }

    public function testUpdateLastProcessedId(): void
    {
        $this->redis->expects($this->once())
            ->method('set')
            ->with('last_processed_id:test-source', '2000');

        $this->coordination->updateLastProcessedId('test-source', '2000');
    }

    public function testIsLockedReturnsTrueWhenLockedByAnother(): void
    {
        $this->redis->expects($this->once())
            ->method('get')
            ->with('loader_lock:test-source')
            ->willReturn('instance-2');

        $result = $this->coordination->isLocked('test-source');
        
        $this->assertTrue($result);
    }

    public function testIsLockedReturnsFalseWhenNotLocked(): void
    {
        $this->redis->expects($this->once())
            ->method('get')
            ->with('loader_lock:test-source')
            ->willReturn(null);

        $result = $this->coordination->isLocked('test-source');
        
        $this->assertFalse($result);
    }

    public function testIsLockedReturnsFalseWhenLockedBySelf(): void
    {
        $this->redis->expects($this->once())
            ->method('get')
            ->with('loader_lock:test-source')
            ->willReturn('instance-1');

        $result = $this->coordination->isLocked('test-source');
        
        $this->assertFalse($result);
    }
}
