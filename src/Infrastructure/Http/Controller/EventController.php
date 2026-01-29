<?php

declare(strict_types=1);

namespace App\Infrastructure\Http\Controller;

use App\Application\Command\CreateEventCommand;
use App\Application\Command\CreateEventCommandHandler;
use App\Application\Query\GetEventQuery;
use App\Application\Query\GetEventQueryHandler;
use App\Application\Query\GetEventsQuery;
use App\Application\Query\GetEventsQueryHandler;
use App\Application\Request\CreateEventRequest;
use App\Application\Response\ErrorResponse;
use App\Application\Response\EventCreatedResponse;
use App\Application\Response\EventListResponse;
use App\Application\Response\EventResponse;
use App\Application\Response\HealthResponse;
use App\Application\Response\ValidationErrorResponse;
use App\Domain\Exception\EventNotFoundException;
use App\Domain\Repository\EventRepositoryInterface;
use App\Infrastructure\Http\Validator\RequestValidator;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/events', name: 'api_events_')]
final class EventController extends AbstractController
{
    public function __construct(
        private readonly CreateEventCommandHandler $createEventHandler,
        private readonly GetEventQueryHandler $getEventHandler,
        private readonly GetEventsQueryHandler $getEventsHandler,
        private readonly EventRepositoryInterface $eventRepository,
        private readonly RequestValidator $validator
    ) {
    }

    /**
     * Health check endpoint
     * GET /api/events/health
     */
    #[Route('/health', name: 'health', methods: ['GET'])]
    public function health(): JsonResponse
    {
        $response = HealthResponse::ok('Event API');
        
        return $this->json($response->toArray());
    }

    /**
     * List events (Event Source endpoint)
     * GET /api/events?since={lastId}&limit={limit}
     * 
     * Behaves like: SELECT * FROM events WHERE id > ? ORDER BY id LIMIT ?
     * 
     * Parameters:
     * - since: Last known event ID (optional, returns events with id > since)
     * - limit: Max events to return (default: 1000, max: 1000)
     * 
     * Returns events sorted by ID ascending
     */
    #[Route('', name: 'list', methods: ['GET'])]
    public function list(Request $request): JsonResponse
    {
        try {
            $since = $request->query->get('since');
            $limit = (int) $request->query->get('limit', 1000);

            // Validate since parameter
            if ($since !== null && !ctype_digit((string) $since)) {
                $error = ErrorResponse::fromMessage('Parameter "since" must be a positive integer');
                return $this->json($error->toArray(), Response::HTTP_BAD_REQUEST);
            }
            
            $query = new GetEventsQuery(
                since: $since,
                limit: $limit
            );
            
            $events = ($this->getEventsHandler)($query);
            
            $response = EventListResponse::fromEvents(
                $events,
                $this->eventRepository->getSourceName(),
                $limit
            );
            
            return $this->json($response->toArray());
            
        } catch (\InvalidArgumentException $e) {
            $error = ErrorResponse::fromException($e);
            return $this->json($error->toArray(), Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $error = ErrorResponse::fromMessage('An error occurred while retrieving events');
            return $this->json($error->toArray(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Create a new event
     * POST /api/events
     * Requires authentication (configured via security.yaml firewall)
     */
    #[Route('', name: 'create', methods: ['POST'])]
    public function create(Request $request): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);

            if (!$data || !is_array($data)) {
                $error = ErrorResponse::fromMessage('Invalid JSON payload');
                return $this->json($error->toArray(), Response::HTTP_BAD_REQUEST);
            }

            // Create and validate Request DTO
            $requestDto = CreateEventRequest::fromArray($data);
            $validationErrors = $this->validator->validate($requestDto);

            if (!empty($validationErrors)) {
                $response = ValidationErrorResponse::fromErrors($validationErrors);
                return $this->json($response->toArray(), Response::HTTP_UNPROCESSABLE_ENTITY);
            }

            $command = new CreateEventCommand(
                name: $requestDto->name,
                scheduledDate: $requestDto->scheduledDate,
                description: $requestDto->description
            );

            $eventId = ($this->createEventHandler)($command);
            
            $response = EventCreatedResponse::fromEventId($eventId);

            return $this->json($response->toArray(), Response::HTTP_CREATED);

        } catch (\InvalidArgumentException $e) {
            $error = ErrorResponse::fromException($e);
            return $this->json($error->toArray(), Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $error = ErrorResponse::fromMessage('An error occurred while creating the event');
            return $this->json($error->toArray(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Get a single event by ID
     * GET /api/events/{id}
     * Requires authentication (configured via security.yaml firewall)
     */
    #[Route('/{id}', name: 'get', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function get(string $id): JsonResponse
    {
        try {
            if (!ctype_digit($id)) {
                $error = ErrorResponse::fromMessage('Event ID must be a positive integer');
                return $this->json($error->toArray(), Response::HTTP_BAD_REQUEST);
            }

            $query = new GetEventQuery($id);
            $event = ($this->getEventHandler)($query);
            
            $response = EventResponse::fromEntity($event);

            return $this->json($response->toArray());

        } catch (EventNotFoundException $e) {
            $error = ErrorResponse::fromException($e);
            return $this->json($error->toArray(), Response::HTTP_NOT_FOUND);
        } catch (\InvalidArgumentException $e) {
            $error = ErrorResponse::fromMessage('Invalid event ID format. Must be a positive integer.');
            return $this->json($error->toArray(), Response::HTTP_BAD_REQUEST);
        } catch (\Exception $e) {
            $error = ErrorResponse::fromMessage('An error occurred while retrieving the event');
            return $this->json($error->toArray(), Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

}
