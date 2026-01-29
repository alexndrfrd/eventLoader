<?php

declare(strict_types=1);

namespace App\Application\Query;

/**
 * Query to get multiple events (event sourcing / listing)
 * 
 * Supports pagination via "since" (last known ID) and "limit"
 */
final readonly class GetEventsQuery
{
    public function __construct(
        public ?string $since = null,
        public int $limit = 1000
    ) {
        if ($this->limit < 1) {
            throw new \InvalidArgumentException('Limit must be at least 1');
        }
        
        // Event sources can provide up to 1,000 events per request
        if ($this->limit > 1000) {
            throw new \InvalidArgumentException('Limit cannot exceed 1000 (event source constraint)');
        }
    }
}
