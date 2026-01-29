<?php

declare(strict_types=1);

namespace App\Domain\Service;

/**
 * Loader Coordination Interface
 * 
 * Ensures conflict-free execution when multiple loader instances run in parallel
 * across different servers. Prevents duplicate event requests.
 */
interface LoaderCoordinationInterface
{
    public function acquireLock(string $sourceName, int $ttlSeconds = 60): bool;

    public function releaseLock(string $sourceName): void;

    public function getLastProcessedId(string $sourceName): ?string;

    public function updateLastProcessedId(string $sourceName, string $lastId): void;

    public function isLocked(string $sourceName): bool;
}
