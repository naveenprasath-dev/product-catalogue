---
name: laravel-database
description: Change the Postgres schema or data layer in api/ - migrations, Eloquent models, casts, relations, factories, repository queries, upserts and transactions. Use when adding/changing a table, column, index, model, factory or ProductRepository query.
---

# Laravel database work (Postgres via Eloquent)

Schema of record: PLAN.md §4 "Database". Keep it in sync when you change anything.

## Migrations
- One migration per change; never edit a migration that has been committed — add a new one.
- Use schema builder types matching PLAN.md (`decimal('price', 10, 2)`, `timestampTz('imported_at')`,
  `foreignId('category_id')->constrained()`), plus the named unique/indexes from the plan.
- Engine-specific SQL is allowed **only** in migrations, and only if the builder can't express it.
- Always write a working `down()`.

## Models
- `final class Product extends Model` with `use HasFactory;`, explicit `$fillable` (no `$guarded = []`).
- Casts via the `casts(): array` method: `'price' => 'decimal:2'`, `'rating_rate' => 'decimal:2'`,
  `'imported_at' => 'immutable_datetime'`, `'status' => EnquiryStatus::class`.
- Relations typed: `public function category(): BelongsTo`.
- Models never leave the data layer: repositories map them to `Product` DTOs (`toDto()` private helper
  in the repository, not on the model).

## Queries (EloquentProductRepository)
- Eager load to avoid N+1: `Product::query()->with('category')`.
- Search with `whereLike` (case-insensitive, bound) inside a grouped `where(fn ($q) => …)`,
  including `orWhereHas('category', fn ($q) => $q->whereLike('name', "%{$term}%"))`. No `ILIKE`, no `DB::raw`.
- Featured: fetch `whereIn('external_id', $ids)` then re-order in PHP by the configured order; skip missing ids.
- Top rated: `orderByDesc('rating_rate')->orderBy('id')` (stable tie-break) and split in the service.

## Writes
- Importer: `DB::transaction(function () { … })`; `Category::upsert($rows, ['slug'], ['name', 'updated_at'])`
  and `Product::upsert($rows, ['source', 'external_id'], [...updatable columns])`. Upsert needs the
  unique index to exist.
- Enquiries: create as `pending`, then update status — two writes, no transaction around the mail send.

## Factories
- Every model used in tests has a factory with realistic fake data (`fake()->randomFloat(2, 1, 500)`,
  `rating_rate` between 0 and 5, unique `external_id`).
- Add states for test intent: `->topRated()`, `->forCategory($category)`.

## Verify
```bash
docker compose exec api php artisan migrate:status
docker compose exec api php artisan test
```
`migrate:fresh` wipes enquiries — ask the user before running it on the dev DB (see `catalogue-data`).
