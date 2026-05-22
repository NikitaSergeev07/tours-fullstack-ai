<?php

namespace App\Services\Embeddings;

interface EmbeddingsClient
{
    /**
     * Compute embeddings for a list of texts.
     *
     * @param  list<string>  $texts
     * @return list<list<float>>
     */
    public function embed(array $texts): array;

    /** Embedding dimensionality returned by the underlying model. */
    public function dim(): int;

    /** True when the upstream model is reachable and loaded. */
    public function isAvailable(): bool;
}
