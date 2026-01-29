# Event Loader

Symfony application implementing an event sourcing loader with distributed coordination. The loader fetches events from multiple sources in a round-robin fashion, respecting rate limits and ensuring no duplicate processing across multiple instances.

## Quick Start

```bash
# Start containers
make up

# Run tests
make test

# Run loader with test data
make loader-with-data-distributed
```

## Testing

### Run All Tests

**Using Make:**
```bash
make test
```

**Using Docker Compose:**
```bash
docker-compose exec php bin/phpunit --testdox
```

### Run Specific Test Suites

**Unit tests only:**
```bash
make test-unit
# or
docker-compose exec php bin/phpunit --testsuite Unit --testdox
```

**Integration tests:**
```bash
make test-integration
# or
docker-compose exec php bin/phpunit --testsuite Integration --testdox
```

**Functional tests:**
```bash
docker-compose exec php bin/phpunit --testsuite Functional --testdox
```

### Test Coverage

```bash
make test-coverage
# or
docker-compose exec php bin/phpunit --coverage-html coverage
```

Coverage report will be available in `coverage/index.html`.

### Running Specific Tests

**Single test file:**
```bash
docker-compose exec php bin/phpunit tests/Unit/Domain/Entity/EventTest.php
```

**Single test method:**
```bash
docker-compose exec php bin/phpunit --filter testCanCreateEvent
```

## Architecture

### Controllers

Controllers handle HTTP requests and return JSON responses. They're located in `src/Infrastructure/Http/Controller/`.

**EventController** (`/api/events`):
- `GET /api/events` - List events with pagination (`since`, `limit`)
- `POST /api/events` - Create a new event
- `GET /api/events/{id}` - Get a specific event
- `GET /health` - Health check endpoint

Controllers use DTOs (Data Transfer Objects) for request validation and response formatting. They don't contain business logic - that's handled by command/query handlers.

### DTOs (Data Transfer Objects)

DTOs are simple objects used to transfer data between layers. They're located in `src/Application/`.

**Request DTOs:**
- `CreateEventRequest` - Validates incoming event creation requests
- `GetEventsQuery` - Represents query parameters for listing events

**Response DTOs:**
- `EventResponse` - Single event representation
- `EventListResponse` - List of events with pagination info
- `EventCreatedResponse` - Response after creating an event
- `ErrorResponse` - Error representation
- `HealthResponse` - Health check response

DTOs are readonly and immutable. They handle serialization to JSON automatically.

### Commands

Commands are Symfony console commands for running background processes.

**app:event-loader** - Main loader command:
```bash
# Basic usage
make loader-distributed

# With options
docker-compose exec php php bin/console app:event-loader \
  --distributed \
  --sources=5 \
  --iterations=20
```

**app:loader-with-data** - Creates test events and runs loader in same process:
```bash
make loader-with-data-distributed
```

**app:clear-loader-state** - Clears Redis state (locks, last processed IDs):
```bash
make clear-loader-state
```

### Event Loader Service

The `EventLoaderService` orchestrates the event loading process:

- Runs in an infinite loop (or limited iterations)
- Processes sources in round-robin fashion
- Enforces 200ms rate limit between requests to the same source
- Supports distributed coordination via Redis (locks, shared state)
- Handles errors gracefully (skips unavailable sources)

**Key features:**
- Rate limiting: 200ms minimum interval between requests to same source
- Round-robin: Cycles through all sources evenly
- Distributed: Multiple loader instances can run without conflicts
- Error handling: Continues processing even if a source fails

### Event Sources

Event sources implement `EventSourceInterface`. Currently, `HttpEventSource` fetches events by directly accessing the repository (for development/testing). In production, it would make HTTP requests to external APIs.

Each source has a unique name and provides:
- `fetchEvents(?EventId $since, int $limit)` - Fetch events since a given ID
- `isAvailable()` - Check if source is available
- `getName()` - Get source identifier

### Domain Layer

The domain layer contains core business logic:

- **Value Objects**: `EventId`, `EventName`, `EventDate` - Immutable, validated objects
- **Entities**: `Event` - Domain entity with business rules
- **Repositories**: `EventRepositoryInterface` - Abstraction for event storage
- **Services**: `EventSourceInterface`, `RateLimiterInterface`, `LoaderCoordinationInterface` - Domain service contracts

### Infrastructure Layer

Infrastructure implements domain interfaces:

- **Persistence**: `InMemoryEventRepository` - In-memory storage (for testing)
- **Services**: `HttpEventSource`, `InMemoryRateLimiter`, `RedisRateLimiter`, `RedisLoaderCoordination`
- **HTTP**: Controllers, request/response handling
- **Commands**: Console commands

## Running the Loader

### Basic Usage

**With test data (recommended for testing):**
```bash
make loader-with-data-distributed
```

This creates 200 test events and runs the loader with 5 sources for 20 iterations.

**Production mode:**
```bash
make loader-distributed
```

Runs the loader in distributed mode (requires Redis). It will run indefinitely until stopped (Ctrl+C).

### Options

- `--sources=N` - Number of event sources (default: 3)
- `--iterations=N` - Max iterations, 0 for infinite (default: 0)
- `--distributed` - Enable distributed coordination (requires Redis)
- `--verbose-logs` - Enable verbose logging

### Distributed Mode

Distributed mode uses Redis for:
- **Rate limiting**: Shared across all loader instances
- **Locks**: Prevents multiple instances from processing the same source simultaneously
- **State**: Tracks last processed ID per source across instances

To use distributed mode:
1. Ensure Redis is running: `make redis-ping`
2. Run with `--distributed` flag
3. Multiple instances can run simultaneously without conflicts

## Development

### Setup

```bash
# Build and start containers
make build
make up

# Install dependencies
make install

# Clear cache
make cache-clear
```

### Project Structure

```
src/
├── Application/          # Application layer (use cases)
│   ├── Command/         # Command handlers
│   ├── Query/           # Query handlers
│   ├── Response/        # Response DTOs
│   └── Service/          # Application services
├── Domain/              # Domain layer (business logic)
│   ├── Entity/          # Domain entities
│   ├── Repository/      # Repository interfaces
│   ├── Service/         # Domain service interfaces
│   └── ValueObject/     # Value objects
└── Infrastructure/       # Infrastructure layer (implementations)
    ├── Command/         # Console commands
    ├── Http/            # HTTP controllers
    ├── Persistence/     # Repository implementations
    └── Service/         # Service implementations

tests/
├── Unit/                # Unit tests
├── Integration/         # Integration tests
└── Functional/          # Functional tests
```

### Code Style

The project follows SOLID principles and Domain-Driven Design (DDD):
- Dependency Inversion: Domain defines interfaces, Infrastructure implements them
- Single Responsibility: Each class has one reason to change
- Open/Closed: Open for extension, closed for modification

## Makefile Commands

```bash
make help              # Show all available commands
make build             # Build Docker containers
make up                # Start containers
make down              # Stop containers
make test              # Run all tests
make test-unit         # Run unit tests
make test-integration  # Run integration tests
make loader-distributed # Run loader in distributed mode
make loader-with-data-distributed # Run loader with test data
make clear-loader-state # Clear Redis state
make redis-cli         # Access Redis CLI
```

## Requirements

- Docker & Docker Compose
- PHP 8.5+
- Redis (for distributed mode)

