---
name: "source-command-dev"
description: "Bring up the full stack in dev mode, follow logs until each service is ready, and print the URLs."
---

# source-command-dev

Use this skill when the user asks to run the migrated source command `dev`.

## Command Template

Bring up the stack and wait for readiness.

```bash
docker compose up -d --build
echo "Waiting for backend/health..."
until curl -fsS http://localhost:8000/api/health >/dev/null 2>&1; do sleep 2; done
echo "Waiting for embeddings/health..."
until curl -fsS http://localhost:8001/health | jq -e '.status == "ok"' >/dev/null 2>&1; do sleep 3; done
echo "Waiting for frontend..."
until curl -fsS http://localhost:3000/ >/dev/null 2>&1; do sleep 2; done

cat <<EOT
Stack ready:
  Catalogue:   http://localhost:3000/
  Admin panel: http://localhost:8000/admin  (admin@tours.local / password)
  API:         http://localhost:8000/api/tours
  Embeddings:  http://localhost:8001/health
EOT
```
