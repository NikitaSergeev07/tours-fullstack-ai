<?php

namespace App\Services\LLM;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use RuntimeException;

/**
 * Generates a tour draft using the Anthropic Messages API.
 *
 * Supports two auth flavours so we can route through the official API or an
 * Anthropic-compatible proxy (e.g. gngn.my) without code changes:
 *
 *   - `ANTHROPIC_API_KEY`     → `x-api-key: <key>` (direct Anthropic).
 *   - `ANTHROPIC_AUTH_TOKEN`  → `Authorization: Bearer <token>` (proxy).
 *
 * The auth token wins when both are set - most proxy users leave the
 * direct key around as a fallback for switching back.
 *
 * The model is forced into JSON-only output via a strict system prompt and a
 * leading `{` in the assistant turn - Anthropic doesn't expose tool_choice
 * for plain Messages calls the way OpenAI does, but the prefill trick is
 * reliable and cheap. If anything still slips, we extract the first {...}
 * block before decoding.
 */
class AnthropicTourGenerator implements TourGenerator
{
    private const SYSTEM_PROMPT = <<<'TXT'
You are a tour catalog assistant for a Russian-speaking travel marketplace.
You produce a single JSON object describing a draft tour. NEVER include any
prose before or after the JSON. NEVER wrap the JSON in markdown.

Schema (all fields required, lengths/units strict):
{
  "title": "string, 5..80 chars, Russian",
  "short_description": "string, 50..280 chars",
  "description": "string, 500..2000 chars, paragraphs separated by \n\n",
  "duration_days": "integer 1..30",
  "difficulty": "one of: easy | moderate | hard",
  "highlights": ["3..6 short bullet strings, Russian"],
  "categories": ["1..3 short category names, Russian, e.g. Горы, Море, Гастро"],
  "route_points": [
    {"lat": number, "lon": number, "label": "string"}
  ],
  "route_center": {"lat": number, "lon": number, "zoom": 7..12},
  "dates": [
    {"start_date": "YYYY-MM-DD", "end_date": "YYYY-MM-DD",
     "price": number_RUB, "currency": "RUB", "seats_total": integer}
  ]
}

Rules:
- route_points must have 3..7 entries forming an ordered itinerary.
- dates must contain 2..4 future options spread over the next 6 months.
- end_date - start_date + 1 must equal duration_days.
- All currency values in RUB, realistic for the Russian market.
TXT;

    private Client $http;

    public function __construct(
        private readonly string $apiKey = '',
        private readonly string $authToken = '',
        private readonly string $model = 'claude-haiku-4-5',
        private readonly string $baseUrl = 'https://api.anthropic.com/v1',
    ) {
        $this->http = new Client([
            'base_uri' => rtrim($this->baseUrl, '/').'/',
            'timeout' => 120,
            'http_errors' => false,
        ]);
    }

    public function generate(string $prompt): array
    {
        $headers = $this->authHeaders();

        $payload = [
            'model' => $this->model,
            'max_tokens' => 2048,
            'system' => self::SYSTEM_PROMPT,
            'messages' => [
                ['role' => 'user', 'content' => $prompt],
                ['role' => 'assistant', 'content' => '{'],
            ],
        ];

        try {
            $response = $this->http->post('messages', [
                'headers' => $headers,
                'json' => $payload,
            ]);
        } catch (GuzzleException $e) {
            throw new RuntimeException('Anthropic request failed: '.$e->getMessage(), 0, $e);
        }

        $body = (string) $response->getBody();
        if ($response->getStatusCode() !== 200) {
            throw new RuntimeException('Anthropic '.$response->getStatusCode().': '.$body);
        }

        $data = json_decode($body, true);
        // Concatenate every text block - Anthropic can return multiple
        // (e.g. text + thinking when extended thinking is on). Tool-use
        // blocks are ignored: this endpoint asks for plain JSON only.
        $text = '';
        foreach ((array) ($data['content'] ?? []) as $block) {
            if (($block['type'] ?? '') === 'text') {
                $text .= (string) ($block['text'] ?? '');
            }
        }
        if ($text === '') {
            throw new RuntimeException('empty completion');
        }

        // We pre-filled `{` in the assistant turn, so the completion is the
        // rest of the object. Re-attach the opening brace for parsing.
        $json = '{'.$text;
        $decoded = json_decode($json, true);
        if (! is_array($decoded)) {
            // Fall back: extract the largest JSON object substring.
            if (preg_match('/\{.*\}/s', $body, $m)) {
                $decoded = json_decode($m[0], true);
            }
        }
        if (! is_array($decoded)) {
            throw new RuntimeException('could not decode tour JSON: '.substr($body, 0, 200));
        }

        return $this->normalise($decoded);
    }

    /**
     * Build the auth + content headers, choosing Bearer (proxy) over
     * x-api-key (direct) when both are configured.
     *
     * @return array<string,string>
     */
    private function authHeaders(): array
    {
        $headers = [
            'anthropic-version' => '2023-06-01',
            'content-type' => 'application/json',
        ];

        if ($this->authToken !== '') {
            $headers['Authorization'] = 'Bearer '.$this->authToken;
        } elseif ($this->apiKey !== '') {
            $headers['x-api-key'] = $this->apiKey;
        } else {
            throw new RuntimeException(
                'No Anthropic credentials configured - set ANTHROPIC_API_KEY (direct) '.
                'or ANTHROPIC_AUTH_TOKEN (proxy) in .env'
            );
        }

        return $headers;
    }

    /** @return array<string,mixed> */
    private function normalise(array $raw): array
    {
        // Soft-validate / coerce - the admin still reviews and edits the draft
        // so we accept "best-effort" output instead of failing hard.
        $raw['title'] = (string) ($raw['title'] ?? 'Без названия');
        $raw['short_description'] = (string) ($raw['short_description'] ?? '');
        $raw['description'] = (string) ($raw['description'] ?? '');
        $raw['duration_days'] = max(1, (int) ($raw['duration_days'] ?? 1));
        $raw['difficulty'] = in_array($raw['difficulty'] ?? 'easy', ['easy', 'moderate', 'hard'], true)
            ? $raw['difficulty'] : 'easy';
        $raw['highlights'] = array_values(array_filter((array) ($raw['highlights'] ?? []), 'is_string'));
        $raw['categories'] = array_values(array_filter((array) ($raw['categories'] ?? []), 'is_string'));
        $raw['route_points'] = array_values((array) ($raw['route_points'] ?? []));
        $raw['route_center'] = (array) ($raw['route_center'] ?? []);
        $raw['dates'] = array_values((array) ($raw['dates'] ?? []));
        return $raw;
    }
}
