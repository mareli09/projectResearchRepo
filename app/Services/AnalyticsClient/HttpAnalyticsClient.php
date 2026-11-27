<?php

namespace App\Services\AnalyticsClient;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Calls the FastAPI service on 127.0.0.1 with an HMAC-SHA256 signature.
 * Header X-Timestamp: unix seconds. Header X-Signature: hex HMAC of "{timestamp}.{body}".
 */
class HttpAnalyticsClient implements AnalyticsClient
{
    public function __construct(
        private readonly string $baseUrl,
        private readonly string $secret,
        private readonly int $timeout = 30,
        private readonly int $retries = 3,
    ) {
        if ($this->secret === '') {
            throw new RuntimeException('ANALYTICS_HMAC_SECRET is not set.');
        }
    }

    public function fit(array $records): array
    {
        return $this->post('/fit', ['records' => $records]);
    }

    public function screen(array $fields, int $k = 5, int|string|null $excludeId = null): array
    {
        return $this->post('/screen', $fields + ['k' => $k, 'exclude_id' => $excludeId]);
    }

    public function related(int|string $id, int $k = 5): array
    {
        return $this->post('/related', ['id' => $id, 'k' => $k]);
    }

    public function cluster(int $kMin = 2, int $kMax = 15, ?int $k = null): array
    {
        return $this->post('/cluster', ['k_min' => $kMin, 'k_max' => $kMax, 'k' => $k]);
    }

    public function topTerms(array $fields, int $n = 5): array
    {
        return array_values($this->post('/top-terms', $fields + ['n' => $n])['terms'] ?? []);
    }

    public function health(): array
    {
        return $this->post('/health', new \stdClass);
    }

    /**
     * Build the signature headers. Public so tests and the Python side can share the format.
     *
     * @return array{X-Timestamp: string, X-Signature: string}
     */
    public static function sign(string $body, string $secret, ?int $timestamp = null): array
    {
        $timestamp ??= time();

        return [
            'X-Timestamp' => (string) $timestamp,
            'X-Signature' => hash_hmac('sha256', $timestamp.'.'.$body, $secret),
        ];
    }

    /**
     * @param  array<string, mixed>|object  $payload
     * @return array<string, mixed>
     */
    private function post(string $path, array|object $payload): array
    {
        $body = json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
        $started = microtime(true);

        try {
            $response = $this->request()
                ->withHeaders(self::sign($body, $this->secret))
                ->withBody($body, 'application/json')
                ->post($path)
                ->throw();

            return $response->json() ?? [];
        } finally {
            // Every exchange between components is logged (§3). Only the path and field names, never the text.
            Log::channel(config('logging.default'))->info('analytics_service', [
                'path' => $path,
                'fields' => is_array($payload) ? array_keys($payload) : [],
                'ms' => (int) ((microtime(true) - $started) * 1000),
            ]);
        }
    }

    private function request(): PendingRequest
    {
        return Http::baseUrl($this->baseUrl)
            ->acceptJson()
            ->timeout($this->timeout)
            // Retry with backoff only when the service is unreachable or fails (§11); 4xx answers are final.
            ->retry($this->retries, fn (int $attempt) => 200 * (2 ** ($attempt - 1)),
                fn (\Throwable $e) => $e instanceof ConnectionException || ($e instanceof RequestException && $e->response->serverError()),
                throw: true);
    }
}
