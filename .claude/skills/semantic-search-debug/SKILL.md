---
name: semantic-search-debug
description: Diagnose why a tour is or isn't surfacing for a given semantic query. Use when a user reports "I searched X and got irrelevant results" or when validating an embedding model change. Walks through health → vector presence → cosine score → fallback path. Do NOT use for plain catalogue bugs (e.g. wrong filter behaviour) — those belong in `backend-expert`.
---

# Semantic search debug

Goal: given a query string `Q` and an expected tour slug `S`, prove (or
disprove) that the embedding pipeline is at fault.

## Step 1 — health

```bash
curl -fsS http://localhost:8001/health | jq .
```

- `status == "loading"` → wait, model is still downloading.
- `status == "error"` → model failed to load; check
  `docker compose logs embeddings`.
- `status == "ok"` → continue.

## Step 2 — confirm vectors are populated

```sql
docker compose exec postgres psql -U tours -d tours -c \
"SELECT id, slug, (embedding IS NOT NULL) AS has_emb FROM tours;"
```

Any row with `has_emb = f` means the indexer never ran for that tour.
Fix:

```bash
docker compose exec backend php artisan tours:reindex --id=<id>
```

## Step 3 — score the candidate manually

```bash
docker compose exec backend php artisan tinker --execute='
  $q = $argv[0]; $slug = $argv[1];
  $vec = app(\App\Services\Embeddings\EmbeddingsClient::class)->embed([$q])[0];
  $row = \DB::selectOne(
    "SELECT slug, 1 - (embedding <=> ?::vector) AS score FROM tours WHERE slug = ?",
    [json_encode($vec), $slug]
  );
  dump($row);
' -- "<Q>" "<S>"
```

- score > 0.5: tour should rank in top 5. If it doesn't, suspect a
  ranking bug, not embeddings.
- 0.3 < score < 0.5: borderline; consider re-phrasing the embedding text
  (cf. `Tour::embeddingText()`).
- score < 0.3: text fields don't carry the meaning of `Q`. Either add
  more descriptive fields to `embeddingText()` or accept the miss.

## Step 4 — verify the API path

```bash
curl -fsS "http://localhost:8000/api/tours?q=$(printf %s "<Q>" | jq -sRr @uri)" \
  | jq '.data[] | {slug, score}' | head -20
```

`score` must appear on every row when `q` is non-empty. If it's
missing, the API silently fell back to ILIKE — check logs for
`"semantic search fallback"`.

## Step 5 — report

Write the findings as:

```
- health: ok (model=...)
- vectors: <N>/<M> tours have embeddings
- score for S=<slug> on Q=<q>: 0.xx
- API path: semantic OR fallback
- conclusion: <one line>
```
