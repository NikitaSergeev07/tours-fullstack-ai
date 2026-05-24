# AI workflow

This document describes how the Tours project is developed with the help
of AI agents. It explains what's wired up, why, and how to drive each
piece.

## Goals of the setup

1. **Onboarding to zero in <60s.** A new agent (or a new dev) opens
   `CLAUDE.md` (or `AGENTS.md`), reads the repo map and hard rules, and
   can make a non-trivial change immediately.
2. **Bounded context windows.** Heavy reads (search, large diffs)
   happen inside sub-agents that summarise back, so the orchestrator's
   context doesn't fill up with logs.
3. **Provable verification.** No PR or change is "done" until
   `/verify` reports green. The gate runs unit tests, typecheck, and
   HTTP probes in parallel.
4. **Reproducible AI features.** The same `TourGenerator` interface is
   used by the admin button and by the `/add-tour` slash command, so
   prompt drift gets caught the same way no matter where you call from.

## Tools wired into the project

| File | What it does |
|---|---|
| `CLAUDE.md` | Bootstrap for Claude Code - hard rules, repo map, common loops |
| `AGENTS.md` | Tool-agnostic version (opencode, codex, gstack) |
| `.claude/settings.json` | Allow/deny lists for Bash, PostToolUse hook |
| `.claude/hooks/format-on-save.sh` | Runs Pint on touched PHP files |
| `.claude/agents/backend-expert.md` | Laravel/Filament/pgvector specialist |
| `.claude/agents/frontend-expert.md` | Vue/Vike/Tailwind 4 specialist |
| `.claude/agents/ai-expert.md` | Embeddings + Anthropic LLM specialist |
| `.claude/commands/dev.md` | `/dev` - bring stack up & wait for ready |
| `.claude/commands/verify.md` | `/verify` - smoke gates in parallel |
| `.claude/commands/feature.md` | `/feature` - plan + delegate + verify |
| `.claude/commands/add-tour.md` | `/add-tour` - LLM-generated tour via tinker |
| `.claude/commands/reseed.md` | `/reseed` - wipe + reseed + reindex |
| `.claude/skills/semantic-search-debug/SKILL.md` | Step-by-step diagnostic for search quality |

## A typical loop

1. **User asks**: "add an `is_featured` flag and a featured carousel on
   the homepage."
2. **Agent** reads `CLAUDE.md`, recognises the change touches backend +
   frontend, runs `/feature add is_featured flag and homepage carousel`.
3. The `/feature` command:
   - Calls `Plan` to lay out the diff (migration, API resource, JSON
     field, Vue carousel component, ssr data fetch).
   - In parallel spawns `backend-expert` (migration + resource update)
     and `frontend-expert` (carousel + data wiring).
   - Each expert finishes by running its own narrow verification
     (`php artisan test`, `vue-tsc`).
4. **Orchestrator** runs `/verify` once more end-to-end. If clean, it
   summarises in 5–10 lines and stops. If not, it surfaces the failure
   and asks the user how to proceed.

## How the LLM tour generation works

```
admin form ─┐
            ├──> POST /api/admin/tours/generate
/add-tour ──┘            │
                         ▼
              App\Services\LLM\TourGenerator (interface)
                         │
                         ▼
              AnthropicTourGenerator
                         │
                Messages API with prefilled `{`
                         ▼
                  JSON-only response
                         │
                         ▼
                  normalise() → array
```

- **Why prefill `{`?** Anthropic's Messages API doesn't have a
  built-in JSON mode, but prefilling the assistant turn with `{` is
  reliable, cheap (no extra tokens), and lets us parse the rest as a
  JSON object.
- **Why normalise instead of validate hard?** The admin always
  reviews the draft. Strict validation would surface model glitches as
  500 errors when, in practice, the admin can fix a missing
  `route_center` in 5 seconds. So we coerce and let the form's
  built-in validation catch anything truly broken.
- **Direct API vs proxy.** `AnthropicTourGenerator` chooses the auth
  header at request time:
  - `ANTHROPIC_AUTH_TOKEN` → `Authorization: Bearer …`
    (Anthropic-compatible proxy, e.g. `https://api.gngn.my/v1`).
  - `ANTHROPIC_API_KEY` → `x-api-key: …` (direct Anthropic).
  The auth-token branch wins when both are set, so a developer can
  flip between modes by editing one env line.

## How semantic search keeps working when the model is down

`HttpEmbeddingsClient::isAvailable()` probes `/health`. The catalog
search (`TourSearch::applySearch`) wraps the embedding lookup in a
try/catch: any failure falls back to `WHERE title ILIKE ? OR …`. The
fallback also boosts title hits in `orderByRaw`. This is intentional:
graceful degradation > pretty error.

To reproduce the fallback path:

```bash
docker compose stop embeddings
curl -fsS "http://localhost:8000/api/tours?q=поход" | jq '.data[0]'
docker compose start embeddings
```

You should see results without a `score` field while the sidecar is
down, and with `score` back once it's healthy.

## Adding a new sub-agent

1. Drop a markdown file in `.claude/agents/<name>.md` with frontmatter
   (`name`, `description`, `tools`, optional `model`).
2. Mention it in `CLAUDE.md` under "When you should delegate".
3. Keep the description specific - vague descriptions confuse the
   orchestrator's auto-selection.

## Cost & latency notes

- Embeddings: one HTTP round-trip per query (~50 ms after warm). The
  model is ~50 MB and runs on CPU.
- LLM: only called from the admin "generate" button or `/add-tour`.
  Each call costs ~$0.005 with `claude-haiku-4-5` and stays under
  3 seconds for a full draft.
