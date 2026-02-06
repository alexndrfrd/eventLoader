# SCRUM-44: HTTP Event Source

**Priority:** Medium | **Points:** 3 | **Epic:** SCRUM-36

## Story
Implement HttpEventSource to fetch events from remote APIs.

## Implementation
```php
class HttpEventSource implements EventSourceInterface {
    public function __construct(
        private HttpClientInterface $httpClient,
        private string $baseUrl,
        private string $name,
        private int $timeout = 5
    ) {}

    public function fetchEvents(?EventId $since, int $limit): array {
        $params = ['limit' => $limit];
        if ($since) $params['since'] = $since->getValue();

        try {
            $response = $this->httpClient->request('GET', $this->baseUrl . '/events', [
                'query' => $params,
                'timeout' => $this->timeout
            ]);
            return $this->mapToEvents($response->toArray());
        } catch (TransportExceptionInterface $e) {
            throw new \RuntimeException('HTTP error: ' . $e->getMessage(), 0, $e);
        }
    }

    public function isAvailable(): bool {
        try {
            $response = $this->httpClient->request('GET', $this->baseUrl . '/health', [
                'timeout' => 2
            ]);
            return $response->getStatusCode() === 200;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function mapToEvents(array $data): array {
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

## API Contract
```
GET /events?since=100&limit=1000
Response: {
  "events": [
    {"id": 101, "name": "Event101", "date": "2024-01-01T12:00:00Z"},
    {"id": 102, "name": "Event102", "date": "2024-01-01T12:01:00Z"}
  ]
}
```

## Error Handling
- 4xx → Skip source, log warning
- 5xx → Retry 3 times, then skip
- Timeout → Retry 3 times, then skip
- Invalid JSON → Log error, return empty array

## Tests
- Mock HTTP client
- Test successful fetch
- Test error responses
- Test timeout handling
- Test retry logic

## Links
Jira: https://alexandrubesleaga92.atlassian.net/browse/SCRUM-44
