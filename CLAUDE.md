# Tours · AI workflow guide

This monorepo is built and maintained with **Claude Code** (Anthropic CLI),
with parallel compatibility for **opencode** and **codex** through
`AGENTS.md`. Every layer of the stack — backend, frontend, embeddings,
infra — is wired so an agent can iterate on it without leaving the repo.

## TL;DR for the agent

1. **Read first**: this file, `AGENTS.md`, then the layer-specific notes
   linked below. Don't `Bash cat` them — use the `Read` tool so the harness
   tracks file state.
2. **Plan before editing.** For anything spanning more than two files, write
   a `TodoWrite` plan or call the `Plan` sub-agent, then execute the plan.
3. **Use the sub-agents.** They are scoped — they don't have write tools by
   default and they return tight summaries instead of dumping logs into
   the main context. See `.claude/agents/`.
4. **Use the slash commands.** They encode the project conventions for
   common tasks: adding a tour, regenerating embeddings, verifying a PR.
5. **Always verify.** `/verify` runs the smoke suite (typecheck +
   `php artisan test` + curl probes) and must pass before you report done.

## Repository map

```
backend/      Laravel 11 + Filament admin + pgvector. See backend/README.md
frontend/     Vue 3 + Vike (SSR) + Tailwind 4 + PrimeVue. See frontend/README.md
embeddings/   FastAPI + sentence-transformers (HF) microservice
docker/       Init scripts (pgvector extension)
docs/         Architecture, ADRs, AI workflow notes
.claude/      Sub-agents, slash commands, settings, skills
```

## Hard rules

- **Don't bypass the embeddings indexer.** Whenever you write a script that
  changes tour text fields (title, description, highlights, categories),
  call `App\Services\Tours\TourIndexer@index` afterwards — otherwise
  semantic search drifts silently. The Filament resource already does this
  in `afterCreate`/`afterSave`.
- **Never store API keys in code or commits.** Anthropic / Yandex keys live
  in `.env` (gitignored). The repo ships an `.env.example` with empty
  values. For the Claude Code session itself, see
  `.claude/settings.local.json.example` — copy it to
  `.claude/settings.local.json` (also gitignored) to route the agent
  through a proxy.
- **LLM has two auth modes.** `AnthropicTourGenerator` accepts either
  `ANTHROPIC_API_KEY` (direct, `x-api-key` header) or
  `ANTHROPIC_AUTH_TOKEN` (Bearer header — for gngn.my and other
  Anthropic-compatible proxies). The Bearer branch wins if both are set.
  `ANTHROPIC_BASE_URL` must include `/v1`.
- **pgvector migrations must be raw SQL** — Eloquent's `Blueprint` doesn't
  know `vector(N)`. See `2024_01_02_000000_create_tours_tables.php` for the
  pattern.
- **Frontend SSR uses the in-container API URL** (`http://backend:8000/api`)
  so requests don't hairpin through the host. Client-side uses
  `PUBLIC_API_URL`. The helper in `frontend/api/client.ts` handles both.
- **Don't enable Sanctum/CORS for `/api/admin/*`.** Those endpoints opt in
  to session auth via the `web` middleware group inside `routes/api.php`.
- **Tailwind 4 only.** Project uses `@tailwindcss/vite`. There is no
  `tailwind.config.js` — design tokens live in `frontend/styles/global.css`
  under `@theme { ... }`. If you want to add a colour, add it there.

## Common loops

| Goal | Command |
|---|---|
| Spin up the stack | `make up` (or `docker compose up -d --build`) |
| Tail logs | `docker compose logs -f backend frontend embeddings` |
| Re-seed | `docker compose exec backend php artisan migrate:fresh --seed` |
| Reindex embeddings | `docker compose exec backend php artisan tours:reindex` |
| Backend tests | `docker compose exec backend php artisan test` |
| Frontend typecheck | `docker compose exec frontend npm run typecheck` |
| Probe semantic search | `curl 'http://localhost:8000/api/tours?q=зимний+поход'` |

## When you should delegate to a sub-agent

- Searching the codebase for a symbol or pattern → `Explore`.
- Drafting a plan touching 3+ files → `Plan`.
- Backend-only refactor → `backend-expert` (knows Laravel/Filament/pgvector).
- Frontend-only UI tweak → `frontend-expert` (knows Vike/Tailwind 4/PrimeVue).
- Embeddings or LLM logic → `ai-expert`.
- Multi-file research without writes → `general-purpose`.

Sub-agents do **not** see your conversation. The prompt must brief them
cold: include file paths, line numbers, and what you've already ruled out.

## Verification gate

Before declaring any change complete:

```bash
docker compose exec backend php artisan test
docker compose exec frontend npm run typecheck
curl -fsS http://localhost:8000/api/health >/dev/null
curl -fsS http://localhost:3000/ >/dev/null
```

The `/verify` slash command runs all four in parallel and reports the
findings. If any fail, fix root causes — do not skip with `--no-verify` or
`xfail` markers.

## Where to write style decisions

- Code-style nits: just fix them, no need to document.
- Cross-cutting conventions (e.g. "we use ofetch for both client and SSR"):
  add to this file under "Hard rules".
- One-off project decisions (e.g. "we picked HNSW over IVFFlat"): add as a
  short ADR in `docs/adr/`.
