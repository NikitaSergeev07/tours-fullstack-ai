# AGENTS.md

Generic agent guide (Claude Code, opencode, codex, gstack). Keep
instructions tool-agnostic; project-specific Claude Code hooks live in
`.claude/`.

## Project at a glance

- Russian-language tours catalogue with a Filament admin.
- Backend: PHP 8.3 / Laravel 11, PostgreSQL 16 + pgvector.
- Frontend: Vue 3 + Vike SSR + Tailwind 4 + PrimeVue.
- AI sidecars: FastAPI embeddings (sentence-transformers MiniLM,
  384-dim) + Claude haiku LLM (used only for the admin "auto-fill tour"
  draft generation). The LLM call supports direct Anthropic
  (`ANTHROPIC_API_KEY`, `x-api-key` header) AND Anthropic-compatible
  proxies (`ANTHROPIC_AUTH_TOKEN`, `Authorization: Bearer …`). See
  `app/Services/LLM/AnthropicTourGenerator::authHeaders`.
- Everything ships as one `docker compose up`.

## What the agent must know up-front

1. **Embeddings are denormalised — keep them fresh.** Anything that mutates
   a Tour's text must call `TourIndexer::index` after the save. The Filament
   pages already do this; in scripts/seeders do it explicitly. There is also
   a fallback `php artisan tours:reindex` command.
2. **pgvector schema is custom DDL.** Look at
   `backend/database/migrations/2024_01_02_000000_create_tours_tables.php`
   for the pattern: add the embedding column with `DB::statement` and a
   matching HNSW index. The model casts `embedding` to `Pgvector\Laravel\Vector`.
3. **SSR vs client URL split.** `frontend/api/client.ts` picks
   `API_URL_SSR` server-side and `PUBLIC_API_URL` client-side. Don't
   hardcode `http://localhost:8000` in components — it breaks inside Docker.
4. **Sliding fallback for search.** If the embeddings sidecar is down,
   `TourSearch::applySearch` falls back to `ILIKE` so the catalogue keeps
   working. Don't remove the fallback "just to clean up".

## Decision log

- **Filament 3** for the admin instead of Laravel Nova or a custom panel —
  it has built-in repeaters (used heavily for photos/dates/route) and
  multi-step form validation out of the box.
- **HNSW index, not IVFFlat.** pgvector ≥ 0.5 ships with HNSW; for a small
  catalogue it gives sub-ms latency without needing list-tuning.
- **paraphrase-multilingual-MiniLM-L12-v2** (384-dim) for embeddings. Small
  enough to load on CPU in <1 GB, supports Russian + English search.
- **Custom Vike integration** (not vike-vue plugin) for transparency and
  smaller deps; the wiring lives in `frontend/pages/app.ts`.

## Workflows by task

### Add a tour by hand
1. Open `http://localhost:8000/admin/tours/create`.
2. Fill the form. The "Сгенерировать через LLM" button drafts a tour from
   a free-form prompt; the admin can override anything before saving.
3. On save, `TourIndexer` recomputes the embedding automatically.

### Add a tour from a script
```php
$tour = Tour::create([...]);
$tour->categories()->sync($ids);
$tour->dates()->createMany([...]);
app(\App\Services\Tours\TourIndexer::class)->index($tour);
```

### Add a new field to Tour
1. Migration with raw `DB::statement` if it touches `vector(N)`; otherwise
   normal Blueprint.
2. Update the `$fillable`, casts, and `embeddingText()` if the field should
   contribute to semantic search.
3. Add the field to `TourResource` / `TourSummaryResource`.
4. Reflect in `TourResource` (Filament form/table).
5. Add the UI binding in `frontend/pages/tour/@slug/+Page.vue`.
6. Run `tours:reindex` if the field affects the embedding.

### Add a new sub-agent
1. Create `.claude/agents/<name>.md` with frontmatter (`name`,
   `description`, `tools`, `model`).
2. Mention it in `CLAUDE.md` under "When you should delegate".

## Anti-patterns

- Don't mock the DB in tests; use the existing pgsql connection or the
  `:memory:` sqlite shim in `phpunit.xml`. Mocked tests have hidden us
  from real pgvector behaviour before.
- Don't add `tailwind.config.js`. Tokens go into the `@theme` block in
  `frontend/styles/global.css`.
- Don't `git push --force` or `--no-verify`. If a hook fails, fix root
  cause.
