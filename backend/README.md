# backend · Laravel 11 + Filament + pgvector

PHP 8.3, Laravel 11, Filament 3 admin, PostgreSQL 16 with pgvector for
the embedding column. Talks to the `embeddings` sidecar over HTTP.

## Local commands

```bash
docker compose exec backend php artisan migrate --seed
docker compose exec backend php artisan test
docker compose exec backend php artisan tours:reindex          # rebuild all vectors
docker compose exec backend php artisan tours:reindex --id=42  # one tour
docker compose exec backend ./vendor/bin/pint                  # format
```

## Entry points

| File | Purpose |
|---|---|
| `routes/api.php` | Public catalog + admin LLM endpoint |
| `app/Http/Controllers/Api/TourController.php` | Catalog & filters |
| `app/Http/Controllers/Api/TourGenerationController.php` | `/api/admin/tours/generate` |
| `app/Services/Tours/TourSearch.php` | Filters + (semantic ⇒ ILIKE) ranking |
| `app/Services/Tours/TourIndexer.php` | Compute and persist embedding |
| `app/Services/Embeddings/HttpEmbeddingsClient.php` | FastAPI sidecar client |
| `app/Services/LLM/AnthropicTourGenerator.php` | Tour draft generation |
| `app/Filament/Resources/TourResource.php` | Admin form (incl. LLM action) |
| `database/seeders/DatabaseSeeder.php` | Seeded admin + 6 demo tours + reindex |
| `database/migrations/2024_01_02_000000_create_tours_tables.php` | pgvector DDL |

## Database

The `tours` table has a custom `embedding vector(384)` column and an
HNSW index built with `vector_cosine_ops`. Migrations use raw
`DB::statement` for those — Eloquent's blueprint can't express it.

If you change `EMBEDDINGS_DIM`, update **both**:

1. `services.embeddings.dim` in `.env` (or `config/services.php`).
2. The migration's `vector(N)` literal — and re-run
   `migrate:fresh --seed` and `tours:reindex`.

## Tests

- `tests/Feature/HealthTest.php` — sanity check that the bootstrap and
  routing work.
- `tests/Unit/AnthropicTourGeneratorTest.php` — verifies the
  `normalise()` step survives malformed LLM output.

Tests run on the `sqlite::memory:` fallback (`phpunit.xml`), so they
don't need pgvector loaded.

## Sanity checks against the running stack

```bash
curl -fsS http://localhost:8000/api/health
curl -fsS 'http://localhost:8000/api/tours?q=зимний+поход' | jq '.data[0] | {slug,score}'
curl -fsS http://localhost:8000/api/tours/filters | jq '.categories'
```
