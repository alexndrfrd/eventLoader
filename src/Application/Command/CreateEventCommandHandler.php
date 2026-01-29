<?php

declare(strict_types=1);

namespace App\Application\Command;

use App\Domain\Entity\Event;
use App\Domain\Repository\EventRepositoryInterface;
use App\Domain\ValueObject\EventDate;
use App\Domain\ValueObject\EventId;
use App\Domain\ValueObject\EventName;

final readonly class CreateEventCommandHandler
{
    public function __construct(
        private EventRepositoryInterface $eventRepository
    ) {
    }

    public function __invoke(CreateEventCommand $command): EventId
    {
        $id = $this->eventRepository->nextId();
        
        $event = Event::create(
            $id,
            EventName::fromString($command->name),
            EventDate::fromString($command->scheduledDate),
            $command->description
        );

        $this->eventRepository->save($event);

        return $event->getId();
    }
}
