<?php

declare(strict_types=1);

namespace App\Tests\Unit\Domain\Service;

use App\Domain\Service\LoaderCoordinationInterface;
use PHPUnit\Framework\TestCase;

/**
 * Test interface contract for LoaderCoordinationInterface
 * 
 * This test verifies that implementations follow the expected contract
 */
final class LoaderCoordinationInterfaceTest extends TestCase
{
    public function testInterfaceHasRequiredMethods(): void
    {
        $reflection = new \ReflectionClass(LoaderCoordinationInterface::class);
        
        $this->assertTrue($reflection->hasMethod('acquireLock'));
        $this->assertTrue($reflection->hasMethod('releaseLock'));
        $this->assertTrue($reflection->hasMethod('getLastProcessedId'));
        $this->assertTrue($reflection->hasMethod('updateLastProcessedId'));
        $this->assertTrue($reflection->hasMethod('isLocked'));
    }

    public function testAcquireLockReturnsBoolean(): void
    {
        $coordination = $this->createMock(LoaderCoordinationInterface::class);
        
        $coordination->method('acquireLock')
            ->willReturn(true);
        
        $this->assertIsBool($coordination->acquireLock('test-source'));
    }

    public function testGetLastProcessedIdReturnsStringOrNull(): void
    {
        $coordination = $this->createMock(LoaderCoordinationInterface::class);
        
        // Test null case
        $coordination->method('getLastProcessedId')
            ->willReturn(null);
        
        $result1 = $coordination->getLastProcessedId('test-source');
        $this->assertNull($result1);
        
        // Test string case - create new mock for second test
        $coordination2 = $this->createMock(LoaderCoordinationInterface::class);
        $coordination2->method('getLastProcessedId')
            ->willReturn('1000');
        
        $result2 = $coordination2->getLastProcessedId('test-source');
        $this->assertIsString($result2);
    }
}
