<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Value Object representing an Event Name
 * Immutable and self-validating
 */
final readonly class EventName
{
    private const int MIN_LENGTH = 3;
    private const int MAX_LENGTH = 255;

    private function __construct(
        private string $value
    ) {
        $this->validate();
    }

    public static function fromString(string $value): self
    {
        return new self($value);
    }

    public function toString(): string
    {
        return $this->value;
    }

    public function equals(EventName $other): bool
    {
        return $this->value === $other->value;
    }

    private function validate(): void
    {
        $trimmed = trim($this->value);

        if (strlen($trimmed) < self::MIN_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Event name must be at least %d characters long', self::MIN_LENGTH)
            );
        }

        if (strlen($trimmed) > self::MAX_LENGTH) {
            throw new \InvalidArgumentException(
                sprintf('Event name must not exceed %d characters', self::MAX_LENGTH)
            );
        }
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
