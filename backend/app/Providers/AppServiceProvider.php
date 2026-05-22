<?php

namespace App\Providers;

use App\Services\Embeddings\EmbeddingsClient;
use App\Services\Embeddings\HttpEmbeddingsClient;
use App\Services\LLM\AnthropicTourGenerator;
use App\Services\LLM\TourGenerator;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EmbeddingsClient::class, function ($app) {
            return new HttpEmbeddingsClient(
                baseUrl: (string) config('services.embeddings.url'),
                timeoutSeconds: (int) config('services.embeddings.timeout'),
                expectedDim: (int) config('services.embeddings.dim'),
            );
        });

        $this->app->singleton(TourGenerator::class, function ($app) {
            return new AnthropicTourGenerator(
                apiKey: (string) config('services.llm.anthropic.api_key'),
                authToken: (string) config('services.llm.anthropic.auth_token'),
                model: (string) config('services.llm.anthropic.model'),
                baseUrl: (string) config('services.llm.anthropic.base_url'),
            );
        });
    }

    public function boot(): void
    {
        // pgvector helpers are registered globally via the package.
    }
}
