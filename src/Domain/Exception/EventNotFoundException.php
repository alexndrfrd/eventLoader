<?php

declare(strict_types=1);

namespace App\Domain\Exception;

use App\Domain\ValueObject\EventId;

/**
 * Domain Exception - Thrown when an Event cannot be found
 */
final class EventNotFoundException extends \DomainException
{
    public static function forId(EventId $id): self
    {
        return new self(
            sprintf('Event with ID "%s" not found', $id->toString())
        );
    }
}
