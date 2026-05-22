---
description: Drop and re-seed the database, then re-embed every tour. Use after schema changes or to recover a clean demo state.
allowed-tools: Bash
---

Reset the catalogue to the seeded demo state.

```bash
docker compose exec -T backend php artisan migrate:fresh --seed --force
docker compose exec -T backend php artisan tours:reindex
```

Sanity:

```bash
curl -fsS http://localhost:8000/api/tours | jq '.data | length'
curl -fsS 'http://localhost:8000/api/tours?q=Камчатка+вулкан' | jq '.data[0] | {slug,title,score}'
```

The second query should return `camchatka` (or similar) with `score > 0.4`.
If the score is null/undefined, the embeddings sidecar isn't healthy —
check `docker compose logs embeddings` and bail with that info.
