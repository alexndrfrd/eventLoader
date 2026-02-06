# SCRUM-44: HTTP Event Source

**Priority:** Medium | **Points:** 3

Implement HttpEventSource to fetch events from remote APIs.

## Implementation

```php
class HttpEventSource implements EventSourceInterface
{
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private string $name,
        private int $timeout = 5
    ) {}

    public function fetchEvents(?EventId $since, int $limit): array
    {
        $params = ['limit' => $limit];
        if ($since !== null) {
            $params['since'] = $since->getValue();
        }

        try {
            $response = $this->httpClient->request('GET', $this->baseUrl . '/events', [
                'query' => $params,
                'timeout' => $this->timeout
            ]);

            $data = $response->toArray();
            return $this->mapToEvents($data);
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('HTTP transport error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function getName(): string { return $this->name; }

    public function isAvailable(): bool
    {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl . '/health', [
                'timeout' => 2
            ]);
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function mapToEvents(array $data): array
    {
        return array_map(
            fn($item) => Event::create(
                new EventId($item['id']),
                new EventName($item['name']),
                EventDate::fromString($item['date']),
                $this->name
            ),
            $data['events'] ?? []
        );
    }
}
```

## Tests
- HTTP GET requests
- Error handling (4xx, 5xx)
- Retry logic
- Timeout handling
