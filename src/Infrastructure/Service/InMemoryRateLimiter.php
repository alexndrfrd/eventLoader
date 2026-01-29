<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use App\Domain\Service\RateLimiterInterface;

final class InMemoryRateLimiter implements RateLimiterInterface
{
    private const MIN_INTERVAL_MS = 200;

    /**
     * @var array<string, float> Tracks last request timestamp per source (microtime)
     */
    private array $lastRequestTimes = [];

    public function isAllowed(string $sourceName): bool
    {
        if (!isset($this->lastRequestTimes[$sourceName])) {
            return true; // First request, always allowed
        }

        $now = microtime(true);
        $lastRequest = $this->lastRequestTimes[$sourceName];
        $elapsedMs = ($now - $lastRequest) * 1000;

        return $elapsedMs >= self::MIN_INTERVAL_MS;
    }

    public function recordRequest(string $sourceName): void
    {
        $this->lastRequestTimes[$sourceName] = microtime(true);
    }

    public function getWaitTime(string $sourceName): int
    {
        if (!isset($this->lastRequestTimes[$sourceName])) {
            return 0;
        }

        $now = microtime(true);
        $lastRequest = $this->lastRequestTimes[$sourceName];
        $elapsedMs = ($now - $lastRequest) * 1000;

        $waitTime = self::MIN_INTERVAL_MS - $elapsedMs;

        return max(0, (int) ceil($waitTime));
    }

    public function getMinInterval(): int
    {
        return self::MIN_INTERVAL_MS;
    }

    public function clear(): void
    {
        $this->lastRequestTimes = [];
    }
}
