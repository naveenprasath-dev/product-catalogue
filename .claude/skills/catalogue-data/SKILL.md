---
name: catalogue-data
description: Operate the local catalogue data - run or force catalogue:import, refresh the committed Fake Store JSON snapshot, reset the Postgres database, inspect tables. Use when the user asks to seed, re-sync, reset or check product data.
---

# Catalogue data operations (local, Docker)

| Task | Command |
|---|---|
| Import (Fake Store, with the snapshot as fallback) | `docker compose exec api php artisan catalogue:import` |
| Import from the snapshot only (offline) | `docker compose exec api php artisan catalogue:import --source=file` |
| Wipe everything and re-seed from scratch | `docker compose down -v && docker compose up --build` |
| Re-run migrations and seed | `docker compose exec api php artisan migrate:fresh --seed` |
| Refresh exchange rates from Frankfurter (same as the admin "Sync live rates") | `docker compose exec api php artisan currencies:sync-rates` |
| Sync rates only if none was ever synced or edited (what the entrypoint runs) | `docker compose exec api php artisan currencies:sync-rates --if-never-updated` |
| Inspect currencies | `docker compose exec db psql -U catalogue -d catalogue -c 'select code, rate, enabled, is_default, rate_updated_at from currencies order by code;'` |
| Inspect the DB | `docker compose exec db psql -U catalogue -d catalogue -c 'select count(*) from products;'` (the local defaults; with custom `DB_*` use `docker compose exec db sh -c 'psql -U "$POSTGRES_USER" -d "$POSTGRES_DB" -c "select count(*) from products;"'` so the variables expand inside the container, not in your host shell) |


`docker compose exec` shells don't see the persisted `APP_KEY` (the entrypoint exports it only to the
server). The import and rate-sync commands don't encrypt anything, so they normally run as above; if a
command fails with `MissingAppKeyException`, run it as
`docker compose exec api sh -c 'APP_KEY="$(cat storage/app/.app-key)" php artisan …'` (ISSUES.md #11).

## Exchange rates
- A sync updates only enabled non-base currencies, in one transaction. Codes Frankfurter has no rate for
  are listed and keep their rate. If Frankfurter fails, the command prints the reason, exits 1 and
  changes **nothing**.
- The migration seeds USD (default, rate 1), EUR, GBP and INR with starting rates and
  `rate_updated_at` null; the first start replaces them once (`--if-never-updated`). Admin edits and
  syncs set `rate_updated_at`, so later starts never overwrite them.
- To go back to the seeded rates, reset the database (below); there is no "reset rates" command.

## Refreshing the snapshot
```bash
curl -fsS https://fakestoreapi.com/products -o api/database/data/fakestore-products.json
```
Then check the file is a valid JSON array (`jq length`), run the import tests (the snapshot is their
fixture), and commit it with a message saying why it was refreshed.

## Cautions
- `down -v` and `migrate:fresh` **delete enquiries too** (and admin-added currencies and rates).
  Confirm with the user before running them.
- `currencies:sync-rates` calls the network and overwrites stored rates; tests never call it for real
  (they use `Http::fake()`).
- Never point tests at the dev database. Tests use `catalogue_test`.
