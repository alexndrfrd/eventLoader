<?php

declare(strict_types=1);

namespace App\Domain\Service;

use App\Domain\ValueObject\EventId;

/**
 * Represents a source that provides events over the network.
 * Each source has a unique name and can provide up to 1000 events per request.
 */
interface EventSourceInterface
{
    public function getName(): string;

    public function fetchEvents(?EventId $since = null, int $limit = 1000): array;

    public function isAvailable(): bool;
}
