<?php

declare(strict_types=1);

namespace App\Infrastructure\Service;

use App\Domain\Service\LoaderCoordinationInterface;

final class RedisLoaderCoordination implements LoaderCoordinationInterface
{
    private const LOCK_PREFIX = 'loader_lock:';
    private const LAST_ID_PREFIX = 'last_processed_id:';
    private const INSTANCE_ID_PREFIX = 'loader_instance:';

    public function __construct(
        private readonly object $redis,
        private readonly string $instanceId
    ) {
    }

    public function acquireLock(string $sourceName, int $ttlSeconds = 60): bool
    {
        $lockKey = self::LOCK_PREFIX . $sourceName;
        $instanceKey = self::INSTANCE_ID_PREFIX . $sourceName;

        $acquired = $this->redis->set($lockKey, $this->instanceId, 'EX', $ttlSeconds, 'NX');

        if ($acquired) {
            $this->redis->set($instanceKey, $this->instanceId, 'EX', $ttlSeconds);
            return true;
        }

        return false;
    }

    public function releaseLock(string $sourceName): void
    {
        $lockKey = self::LOCK_PREFIX . $sourceName;
        $instanceKey = self::INSTANCE_ID_PREFIX . $sourceName;

        // Only release if we own the lock
        $currentOwner = $this->redis->get($lockKey);
        if ($currentOwner === $this->instanceId) {
            $this->redis->del([$lockKey, $instanceKey]);
        }
    }

    public function getLastProcessedId(string $sourceName): ?string
    {
        $key = self::LAST_ID_PREFIX . $sourceName;
        $lastId = $this->redis->get($key);

        return $lastId !== null ? (string) $lastId : null;
    }

    public function updateLastProcessedId(string $sourceName, string $lastId): void
    {
        $key = self::LAST_ID_PREFIX . $sourceName;
        $this->redis->set($key, $lastId);
    }

    public function isLocked(string $sourceName): bool
    {
        $lockKey = self::LOCK_PREFIX . $sourceName;
        $currentOwner = $this->redis->get($lockKey);

        return $currentOwner !== null && $currentOwner !== $this->instanceId;
    }
}
