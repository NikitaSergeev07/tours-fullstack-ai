# Architecture

## Domain model

```
Tour ─┬─ TourPhoto    (1..N, ordered)
      ├─ TourDate     (1..N, future-or-past, with price & seats)
      └─ Category     (N..N via tour_category pivot)
```

Tours have a denormalised `vector(384)` column (`embedding`) computed
from a concatenation of title, descriptions, highlights, and category
names. The column has an HNSW index keyed on `vector_cosine_ops`, so a
nearest-neighbour search runs as:

```sql
SELECT *, 1 - (embedding <=> $1) AS score
FROM tours
WHERE is_published
ORDER BY embedding <=> $1
LIMIT 12;
```

## Why pgvector and not Pinecone/Qdrant?

The catalogue is bounded (hundreds-to-low-thousands of tours). Keeping
vectors in the same Postgres instance:

- avoids a second piece of infrastructure;
- lets us mix lexical filters (`WHERE category_id IN (...)`) and
  semantic ordering in one query;
- gets us index updates for free on `UPDATE` (no separate sync job).

If the catalogue grows past ~100k rows or the embedding gets bigger
than 1024 dims, revisit. Until then, pgvector HNSW is plenty.

## Why HNSW over IVFFlat?

pgvector ≥ 0.5 supports both. HNSW gives near-constant query time
without needing a `lists` parameter or an ANALYZE for the index to
become useful. IVFFlat is faster at index build time but recall depends
on tuning. For a catalogue of <10k rows, HNSW build time is sub-second.

## Why a separate embeddings service?

Three reasons:

1. **PHP doesn't ship with sentence-transformers** and bridging via FFI
   or queue worker is slower than a small HTTP service.
2. **Model weights are heavy (~50 MB).** Loading them in every web
   worker (`php-fpm` style) would balloon memory; a single FastAPI
   process reuses one copy.
3. **Language flexibility.** The model and tokenizer logic stay in
   Python where they're best-supported.

The trade-off is a 1–5 ms hop for each embedding call. Acceptable
because we only call it on writes (one per save) and reads-with-search
(one per filtered list page).

## Request lifecycle

### Reading the catalogue

```
Browser → Vike SSR ──fetch(/api/tours?…)──▶ Laravel
                                               │
                                  TourController@index
                                               │
                                  TourSearch (filters + ranking)
                                  ├─ q? → EmbeddingsClient → pgvector ORDER BY
                                  └─ no q → ORDER BY date/price/created_at
                                               │
                                  TourSummaryResource collection
                                               │
                                  ◀── JSON
Vike renders Vue components and streams HTML back to the browser.
```

### Reading a tour detail

```
Browser → Vike SSR ──fetch(/api/tours/{slug})──▶ Laravel
                                                    │
                                       TourController@show
                                       (404 if !published)
                                                    │
                                       Tour + photos + categories + dates
                                                    │
                                       TourResource
                                                    │
                                       ◀── JSON
Vue renders header, gallery, YandexMap, DateOptions.
```

### Admin: creating a tour

```
Admin → Filament form (Create / Edit Tour)
         │ optional: "Сгенерировать через LLM" → POST /api/admin/tours/generate
         │            ↓
         │   TourGenerator (Anthropic) → JSON draft → set() form fields
         │
         ▼ save
   Create/EditTour::afterCreate / afterSave
         │
         ▼
   TourIndexer::index → EmbeddingsClient → write embedding column
```

## Production checklist (not implemented in this demo)

- Replace `php artisan serve` with `php-fpm` behind `nginx`.
- Replace `npm run dev` with `vite build` + Node SSR worker behind
  `nginx` reverse-proxy.
- Switch `QUEUE_CONNECTION` to `redis` and move `TourIndexer::index`
  off the request thread.
- Set `APP_DEBUG=false`, generate a stable `APP_KEY`, terminate TLS at
  the edge.
- Pin embeddings model digest in `embeddings/requirements.txt` to
  avoid silent drift on upstream HuggingFace updates.
- Add rate limits to `/api/tours` (`throttle:api`) and to the LLM
  generation endpoint specifically.
