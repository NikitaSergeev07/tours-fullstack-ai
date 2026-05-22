<?php

namespace App\Services\Embeddings;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Talks to the FastAPI embeddings sidecar.
 *
 * The service mounts a small multilingual sentence-transformer (default
 * paraphrase-multilingual-MiniLM-L12-v2, dim=384) so requests/responses are
 * cheap and we can call it inline from the request lifecycle.
 */
class HttpEmbeddingsClient implements EmbeddingsClient
{
    private Client $http;

    public function __construct(
        private readonly string $baseUrl,
        private readonly int $timeoutSeconds = 30,
        private readonly int $expectedDim = 384,
    ) {
        $this->http = new Client([
            'base_uri' => rtrim($this->baseUrl, '/').'/',
            'timeout' => $this->timeoutSeconds,
            'connect_timeout' => 5,
            'http_errors' => false,
        ]);
    }

    public function embed(array $texts): array
    {
        $texts = array_values(array_map(fn ($t) => (string) $t, $texts));
        if ($texts === []) {
            return [];
        }

        try {
            $response = $this->http->post('embed', [
                'json' => ['texts' => $texts, 'normalize' => true],
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException('embeddings service unreachable: '.$e->getMessage(), 0, $e);
        }

        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('embeddings service returned '.$response->getStatusCode().': '.$response->getBody());
        }

        $payload = json_decode((string) $response->getBody(), true);
        if (! is_array($payload) || ! isset($payload['embeddings'])) {
            throw new RuntimeException('malformed embeddings response');
        }

        return $payload['embeddings'];
    }

    public function dim(): int
    {
        return $this->expectedDim;
    }

    public function isAvailable(): bool
    {
        try {
            $response = $this->http->get('health');
        } catch (GuzzleException $e) {
            Log::warning('embeddings health probe failed', ['error' => $e->getMessage()]);
            return false;
        }
        if ($response->getStatusCode() !== 200) {
            return false;
        }
        $body = json_decode((string) $response->getBody(), true) ?: [];
        return ($body['status'] ?? null) === 'ok';
    }
}
