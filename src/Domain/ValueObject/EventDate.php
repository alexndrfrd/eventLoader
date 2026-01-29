<?php

declare(strict_types=1);

namespace App\Domain\ValueObject;

/**
 * Value Object representing an Event Date
 * Immutable and self-validating
 */
final readonly class EventDate
{
    private function __construct(
        private \DateTimeImmutable $value
    ) {
    }

    public static function fromDateTime(\DateTimeImmutable $dateTime): self
    {
        return new self($dateTime);
    }

    public static function fromString(string $date): self
    {
        try {
            $dateTime = new \DateTimeImmutable($date);
        } catch (\Exception $e) {
            throw new \InvalidArgumentException(
                sprintf('Invalid date format: %s', $date),
                0,
                $e
            );
        }

        return new self($dateTime);
    }

    public static function now(): self
    {
        return new self(new \DateTimeImmutable());
    }

    public function toDateTime(): \DateTimeImmutable
    {
        return $this->value;
    }

    public function toString(string $format = 'Y-m-d H:i:s'): string
    {
        return $this->value->format($format);
    }

    public function isBefore(EventDate $other): bool
    {
        return $this->value < $other->value;
    }

    public function isAfter(EventDate $other): bool
    {
        return $this->value > $other->value;
    }

    public function equals(EventDate $other): bool
    {
        return $this->value == $other->value;
    }
}
