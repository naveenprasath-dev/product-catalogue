---
name: run-checks
description: Run every quality check in containers - api tests, Pint, web tests, ESLint, tsc - and report results. Use before saying any task is done, before committing, and when the user asks to run tests.
---

# Run all checks

Make sure the stack is up first (`docker compose ps`; if not, `docker compose up -d --build`).

```bash
docker compose exec api php artisan test
docker compose exec api ./vendor/bin/pint --test
docker compose exec web npm test -- --run
docker compose exec web npm run lint
docker compose exec web npx tsc --noEmit
# admin panel (Inertia + React, inside api/)
docker compose exec api npm run types:check
docker compose exec api npm run check
docker compose exec api npm run build
```

## Reporting
- Report each check as passed or failed. On a failure, show the relevant output, fix the cause and
  re-run that check, then the whole set.
- Never call work done while a check is failing (CLAUDE.md). Never skip or delete a test to make the
  run pass; if a test is wrong, say so and explain why.
- If a test tried to make a real network call (`preventStrayRequests` error), fix the test with
  `Http::fake()`. Don't allow the request.
