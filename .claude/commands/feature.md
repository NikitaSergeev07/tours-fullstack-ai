---
description: Plan-then-execute pattern for a feature that touches multiple layers. Spawns the right experts in parallel and verifies the result.
argument-hint: "<one-line feature description>"
---

Implement the feature: **$ARGUMENTS**

Workflow:

1. **Plan** — call the `Plan` sub-agent with the full feature
   description plus the repository map from CLAUDE.md. Ask for a
   step-by-step plan that lists exactly which files in each layer
   (backend / frontend / embeddings) need to change. If the plan is
   trivial (≤ 2 files in one layer), skip this step and go straight
   to the relevant expert.
2. **Delegate in parallel.** For each layer touched by the plan,
   spawn the matching expert sub-agent
   (`backend-expert`, `frontend-expert`, `ai-expert`) with a
   self-contained prompt that includes:
   - the file paths to change,
   - what to add/remove/rename,
   - which tests/probes to run before reporting back.
3. **Stitch.** After all agents return, run `/verify` to make sure the
   integration holds end-to-end. If anything regressed, address it in
   the main thread (you have the full context).
4. **Summarise.** 5–10 lines: what changed, what was tested, what
   manual steps remain (e.g. "run `php artisan tours:reindex`").

Hard rules:
- Don't let one expert call another — orchestrate from the main thread.
- Don't accept "I think the tests pass" — quote the exit code.
- If the feature requires an env var, update both root `.env.example`
  and the layer-specific one (`backend/.env.example`,
  `frontend/.env.example`).
