# embeddings · FastAPI + sentence-transformers

Lightweight HTTP sidecar that turns text into 384-dim vectors. The
Laravel backend calls it on writes (to index tours) and on reads (when
a user types into the search box).

## Endpoints

| Method | Path | Body | Returns |
|---|---|---|---|
| GET | `/health` | – | `{status: "ok"\|"loading"\|"error", model, dim?}` |
| POST | `/embed` | `{texts: string[], normalize?: boolean}` | `{embeddings: number[][], dim: number, model: string}` |

The model is loaded **lazily** in a background thread so the container
boots fast; `/health` reports `loading` until the weights are ready.

## Model

Default: `sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2`
(384 dimensions). Override via `MODEL_NAME`.

Why this model:

- Multilingual (Russian + English search work).
- ~50 MB on disk, CPU-friendly inference (~5–20 ms per text on a
  modern x86 / Apple Silicon).
- Same vector geometry as the popular `all-MiniLM-L6-v2` family.

If you swap the model, update:

1. `EMBEDDINGS_DIM` in the root `.env`.
2. The `vector(N)` literal in the tour migration.
3. Re-run `docker compose exec backend php artisan tours:reindex`.

## Smoke

```bash
curl -fsS http://localhost:8001/health | jq .
curl -fsS http://localhost:8001/embed \
  -H 'content-type: application/json' \
  -d '{"texts":["зимний поход","gastro weekend in Kazan"]}' \
  | jq '.dim, (.embeddings | map(length))'
```
