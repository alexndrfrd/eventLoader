<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Response;

use App\Application\Response\HealthResponse;
use PHPUnit\Framework\TestCase;

final class HealthResponseTest extends TestCase
{
    public function testCanCreateOkResponse(): void
    {
        $response = HealthResponse::ok('Test Service');

        $this->assertInstanceOf(HealthResponse::class, $response);
        $this->assertEquals('ok', $response->status);
        $this->assertEquals('Test Service', $response->service);
        $this->assertNotEmpty($response->timestamp);
    }

    public function testToArrayReturnsCorrectStructure(): void
    {
        $response = HealthResponse::ok('Event API');
        $array = $response->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('status', $array);
        $this->assertArrayHasKey('service', $array);
        $this->assertArrayHasKey('timestamp', $array);
        $this->assertEquals('ok', $array['status']);
        $this->assertEquals('Event API', $array['service']);
    }

    public function testTimestampIsISO8601Format(): void
    {
        $response = HealthResponse::ok('Service');

        // Verify it's a valid ISO 8601 timestamp
        $timestamp = \DateTimeImmutable::createFromFormat(\DateTimeInterface::ATOM, $response->timestamp);
        $this->assertNotFalse($timestamp, 'Timestamp should be valid ISO 8601 format');
        $this->assertInstanceOf(\DateTimeImmutable::class, $timestamp);
    }

    public function testResponseIsReadonly(): void
    {
        $response = HealthResponse::ok('Service');

        $this->expectException(\Error::class);
        $response->status = 'changed'; // @phpstan-ignore-line
    }
}
