<?php

namespace App\Services\Tours;

use App\Models\Tour;
use App\Services\Embeddings\EmbeddingsClient;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Builds the catalogue query: filters + (optional) semantic search.
 *
 * Filters compose normally on top of the Eloquent builder. The semantic step
 * is layered as an extra `order by embedding <=> ?` and exposed `score`
 * column so the frontend can show how relevant each result is.
 *
 * Falls back to a `ILIKE` lexical match when the embeddings service is
 * unreachable, so the catalogue keeps working even without the sidecar.
 */
class TourSearch
{
    public function __construct(private readonly EmbeddingsClient $embeddings)
    {
    }

    /**
     * @param  array{
     *   q?: string|null,
     *   categories?: array<int,string>|null,
     *   duration_min?: int|null,
     *   duration_max?: int|null,
     *   price_min?: float|null,
     *   price_max?: float|null,
     *   difficulty?: string|null,
     *   date_from?: string|null,
     *   date_to?: string|null,
     *   sort?: string|null,
     * } $filters
     */
    public function paginate(array $filters, int $perPage = 12): LengthAwarePaginator
    {
        $query = Tour::query()
            ->published()
            ->with(['photos', 'categories', 'dates'])
            ->select('tours.*');

        $this->applyFilters($query, $filters);

        $q = trim((string) ($filters['q'] ?? ''));
        if ($q !== '') {
            $this->applySearch($query, $q);
        } else {
            $sort = $filters['sort'] ?? 'newest';
            $this->applySort($query, $sort);
        }

        return $query->paginate($perPage)->appends($filters);
    }

    private function applyFilters(Builder $query, array $filters): void
    {
        if (! empty($filters['categories'])) {
            $slugs = array_filter((array) $filters['categories']);
            if ($slugs) {
                $query->whereHas('categories', fn ($q) => $q->whereIn('categories.slug', $slugs));
            }
        }

        if (isset($filters['duration_min'])) {
            $query->where('duration_days', '>=', (int) $filters['duration_min']);
        }
        if (isset($filters['duration_max'])) {
            $query->where('duration_days', '<=', (int) $filters['duration_max']);
        }

        if (! empty($filters['difficulty'])) {
            $query->where('difficulty', $filters['difficulty']);
        }

        $hasPriceFilter = isset($filters['price_min']) || isset($filters['price_max']);
        $hasDateFilter = ! empty($filters['date_from']) || ! empty($filters['date_to']);

        if ($hasPriceFilter || $hasDateFilter) {
            $query->whereHas('dates', function (Builder $q) use ($filters) {
                if (isset($filters['price_min'])) {
                    $q->where('price', '>=', (float) $filters['price_min']);
                }
                if (isset($filters['price_max'])) {
                    $q->where('price', '<=', (float) $filters['price_max']);
                }
                if (! empty($filters['date_from'])) {
                    $q->where('start_date', '>=', $filters['date_from']);
                }
                if (! empty($filters['date_to'])) {
                    $q->where('start_date', '<=', $filters['date_to']);
                }
            });
        }
    }

    private function applySearch(Builder $query, string $q): void
    {
        try {
            $vector = $this->embeddings->embed([$q])[0] ?? null;
            if (! $vector) {
                throw new RuntimeException('empty vector');
            }
            // Bind as an explicit pgvector literal so we don't rely on
            // implicit __toString on the Vector object - that has bitten
            // teams with older pgvector-php versions before.
            $literal = '['.implode(',', array_map(static fn ($f) => (float) $f, $vector)).']';
            // 1 - cosine_distance is similarity (0..1). Exposed as `score`.
            $query
                ->whereNotNull('embedding')
                ->addSelect(DB::raw('1 - (embedding <=> ?::vector) as score'))
                ->addBinding($literal, 'select')
                ->orderByRaw('embedding <=> ?::vector', [$literal]);
        } catch (\Throwable $e) {
            Log::warning('semantic search fallback', ['error' => $e->getMessage()]);
            $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
            $query
                ->where(function (Builder $b) use ($like) {
                    $b->where('title', 'ilike', $like)
                        ->orWhere('short_description', 'ilike', $like)
                        ->orWhere('description', 'ilike', $like);
                })
                ->orderByRaw('CASE WHEN title ILIKE ? THEN 0 ELSE 1 END', [$like])
                ->orderByDesc('updated_at');
        }
    }

    private function applySort(Builder $query, string $sort): void
    {
        match ($sort) {
            'price_asc' => $query
                ->leftJoinSub(
                    DB::table('tour_dates')->select('tour_id', DB::raw('MIN(price) as min_price'))->groupBy('tour_id'),
                    'p', 'p.tour_id', '=', 'tours.id'
                )
                ->orderByRaw('p.min_price asc nulls last'),
            'price_desc' => $query
                ->leftJoinSub(
                    DB::table('tour_dates')->select('tour_id', DB::raw('MAX(price) as max_price'))->groupBy('tour_id'),
                    'p', 'p.tour_id', '=', 'tours.id'
                )
                ->orderByRaw('p.max_price desc nulls last'),
            'duration_asc' => $query->orderBy('duration_days', 'asc'),
            'duration_desc' => $query->orderBy('duration_days', 'desc'),
            default => $query->orderByDesc('tours.created_at'),
        };
    }
}
