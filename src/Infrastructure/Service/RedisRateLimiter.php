<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use App\Domain\Service\RateLimiterInterface;

final class RedisRateLimiter implements RateLimiterInterface
{
    private const MIN_INTERVAL_MS = 200;
    private const RATE_LIMIT_PREFIX = 'rate_limit:';

    public function __construct(
        private readonly object $redis
    ) {
    }

    public function isAllowed(string $sourceName): bool
    {
        $key = self::RATE_LIMIT_PREFIX . $sourceName;
        $lastRequest = $this->redis->get($key);

        if ($lastRequest === null) {
            return true;
        }

        $now = microtime(true);
        $elapsedMs = ($now - (float) $lastRequest) * 1000;

        return $elapsedMs >= self::MIN_INTERVAL_MS;
    }

    public function recordRequest(string $sourceName): void
    {
        $key = self::RATE_LIMIT_PREFIX . $sourceName;
        $this->redis->set($key, (string) microtime(true), 'EX', 1); // TTL 1 second
    }

    public function getWaitTime(string $sourceName): int
    {
        $key = self::RATE_LIMIT_PREFIX . $sourceName;
        $lastRequest = $this->redis->get($key);

        if ($lastRequest === null) {
            return 0;
        }

        $now = microtime(true);
        $elapsedMs = ($now - (float) $lastRequest) * 1000;
        $waitTime = self::MIN_INTERVAL_MS - $elapsedMs;

        return max(0, (int) ceil($waitTime));
    }
}
