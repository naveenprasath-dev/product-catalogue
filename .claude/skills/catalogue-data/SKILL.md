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
| Inspect the DB | `docker compose exec db psql -U "$DB_USERNAME" -d "$DB_DATABASE" -c 'select count(*) from products;'` |

## Refreshing the snapshot
```bash
curl -fsS https://fakestoreapi.com/products -o api/database/data/fakestore-products.json
```
Then check the file is a valid JSON array (`jq length`), run the import tests (the snapshot is their
fixture), and commit it with a message saying why it was refreshed.

## Cautions
- `down -v` and `migrate:fresh` **delete enquiries too**. Confirm with the user before running them.
- Never point tests at the dev database. Tests use `catalogue_test`.
