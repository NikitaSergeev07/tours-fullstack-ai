---
description: Run the project's smoke gates in parallel — PHP tests, frontend typecheck, API + frontend HTTP probes. Use this before saying "done".
allowed-tools: Bash
---

Run the full verification gate. Execute all four checks in parallel and
report which passed / which failed. Quote the first failing line for any
failure so the operator can jump to the cause.

Commands to run (in parallel):

```bash
docker compose exec -T backend php artisan test --without-tty
docker compose exec -T frontend npm run typecheck --silent
curl -fsS -o /dev/null -w 'api:%{http_code}\n' http://localhost:8000/api/health
curl -fsS -o /dev/null -w 'web:%{http_code}\n' http://localhost:3000/
```

Output format:

```
✓ backend tests
✓ frontend typecheck
✓ api /health (200)
✗ frontend / (502) — vite not ready, retry in 5s
```

If everything passes, finish with `All gates green.` If anything fails,
finish with `Verification failed — see above. Do not declare task done.`
and do nothing else.
