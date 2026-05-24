<?php

namespace App\Services\Tours;

use App\Models\Tour;
use App\Services\Embeddings\EmbeddingsClient;
use Illuminate\Support\Facades\Log;
use Pgvector\Laravel\Vector;

/**
 * Recomputes a tour's embedding from its text fields.
 *
 * Called by the Filament resource after save/update and by the seeder, so
 * the catalogue has vectors immediately without requiring a separate
 * indexing job. For large catalogues this would belong on the queue.
 */
class TourIndexer
{
    public function __construct(private readonly EmbeddingsClient $embeddings)
    {
    }

    public function index(Tour $tour): bool
    {
        $tour->loadMissing('categories');
        $text = $tour->embeddingText();
        if ($text === '') {
            return false;
        }

        try {
            $vectors = $this->embeddings->embed([$text]);
        } catch (\Throwable $e) {
            // Don't break the save flow - operator can run `php artisan
            // tours:reindex` once the embeddings sidecar is healthy.
            Log::warning('tour indexing skipped', ['tour' => $tour->id, 'error' => $e->getMessage()]);
            return false;
        }

        $vec = $vectors[0] ?? null;
        if (! $vec) {
            return false;
        }

        $tour->embedding = new Vector($vec);
        $tour->saveQuietly();
        return true;
    }
}
