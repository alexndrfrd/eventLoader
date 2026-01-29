<?php

declare(strict_types=1);

namespace App\Application\Request;

use Symfony\Component\Validator\Constraints as Assert;

final class CreateEventRequest
{
    #[Assert\NotBlank(message: 'Event name is required')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Event name must be at least {{ limit }} characters long',
        maxMessage: 'Event name cannot be longer than {{ limit }} characters'
    )]
    public string $name;

    #[Assert\NotBlank(message: 'Scheduled date is required')]
    #[Assert\DateTime(message: 'Invalid date format. Expected format: Y-m-d H:i:s')]
    public string $scheduledDate;

    #[Assert\Length(
        max: 2000,
        maxMessage: 'Description cannot be longer than {{ limit }} characters'
    )]
    public ?string $description = null;

    public static function fromArray(array $data): self
    {
        $request = new self();
        $request->name = $data['name'] ?? '';
        $request->scheduledDate = $data['scheduled_date'] ?? '';
        $request->description = $data['description'] ?? null;

        return $request;
    }
}
