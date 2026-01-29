<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Value Object representing an Event ID
 * Immutable and self-validating
 * Uses integer ID (auto-increment from source)
 */
final readonly class EventId
{
    private function __construct(
        private int $value
    ) {
        $this->validate();
    }

    /**
     * Create from integer value
     */
    public static function fromInt(int $value): self
    {
        return new self($value);
    }

    /**
     * Create from string (for HTTP requests)
     */
    public static function fromString(string $value): self
    {
        if (!is_numeric($value)) {
            throw new \InvalidArgumentException(
                sprintf('Event ID must be a valid integer, got: %s', $value)
            );
        }

        return new self((int) $value);
    }

    public function toInt(): int
    {
        return $this->value;
    }

    public function toString(): string
    {
        return (string) $this->value;
    }

    public function equals(EventId $other): bool
    {
        return $this->value === $other->value;
    }

    private function validate(): void
    {
        if ($this->value <= 0) {
            throw new \InvalidArgumentException(
                sprintf('Event ID must be a positive integer, got: %d', $this->value)
            );
        }
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
