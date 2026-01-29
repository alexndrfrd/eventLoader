<?php

declare(strict_types=1);

namespace App\Application\Query;

final readonly class GetEventQuery
{
    public function __construct(
        public string $eventId
    ) {
    }
}
