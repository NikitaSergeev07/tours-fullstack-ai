<?php

return [
    'embeddings' => [
        'url' => env('EMBEDDINGS_URL', 'http://embeddings:8000'),
        'dim' => (int) env('EMBEDDINGS_DIM', 384),
        'timeout' => (int) env('EMBEDDINGS_TIMEOUT', 30),
    ],

    'llm' => [
        'provider' => env('LLM_PROVIDER', 'anthropic'),
        'anthropic' => [
            // Direct Anthropic auth (header: `x-api-key`).
            'api_key' => env('ANTHROPIC_API_KEY'),
            // Anthropic-compatible proxy auth (header: `Authorization: Bearer …`).
            // Takes precedence over api_key when set so users can leave both
            // populated and just swap which one is empty.
            'auth_token' => env('ANTHROPIC_AUTH_TOKEN'),
            'model' => env('ANTHROPIC_MODEL', 'claude-haiku-4-5'),
            'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com/v1'),
        ],
    ],

    'yandex' => [
        'maps_api_key' => env('YANDEX_MAPS_API_KEY'),
    ],
];
