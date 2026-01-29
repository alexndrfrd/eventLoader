<?php

declare(strict_types=1);

namespace App\Tests\Unit\Application\Response;

use App\Application\Response\ErrorResponse;
use PHPUnit\Framework\TestCase;

final class ErrorResponseTest extends TestCase
{
    public function testCanCreateFromMessage(): void
    {
        $response = ErrorResponse::fromMessage('Test error');

        $this->assertInstanceOf(ErrorResponse::class, $response);
        $this->assertEquals('Test error', $response->error);
        $this->assertNull($response->code);
        $this->assertNull($response->details);
    }

    public function testCanCreateFromException(): void
    {
        $exception = new \InvalidArgumentException('Invalid data', 400);
        
        $response = ErrorResponse::fromException($exception);

        $this->assertEquals('Invalid data', $response->error);
        $this->assertEquals(400, $response->code);
    }

    public function testToArrayWithMessageOnly(): void
    {
        $response = ErrorResponse::fromMessage('Simple error');
        $array = $response->toArray();

        $this->assertIsArray($array);
        $this->assertArrayHasKey('error', $array);
        $this->assertArrayNotHasKey('code', $array);
        $this->assertArrayNotHasKey('details', $array);
        $this->assertEquals('Simple error', $array['error']);
    }

    public function testToArrayWithCodeAndDetails(): void
    {
        $response = new ErrorResponse(
            error: 'Validation error',
            code: 422,
            details: ['field' => 'name', 'issue' => 'too short']
        );

        $array = $response->toArray();

        $this->assertArrayHasKey('error', $array);
        $this->assertArrayHasKey('code', $array);
        $this->assertArrayHasKey('details', $array);
        $this->assertEquals(422, $array['code']);
        $this->assertIsArray($array['details']);
    }

    public function testExceptionWithZeroCodeIsNull(): void
    {
        $exception = new \Exception('Error with zero code', 0);
        
        $response = ErrorResponse::fromException($exception);

        $this->assertNull($response->code);
        
        $array = $response->toArray();
        $this->assertArrayNotHasKey('code', $array);
    }
}
