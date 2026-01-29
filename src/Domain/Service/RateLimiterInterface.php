<?php

declare(strict_types=1);

namespace App\Domain\Service;

/**
 * Rate Limiter Interface
 * 
 * Ensures minimum interval between requests to the same event source
 */
interface RateLimiterInterface
{

    public function isAllowed(string $sourceName): bool;

    public function recordRequest(string $sourceName): void;

    public function getWaitTime(string $sourceName): int;
}
