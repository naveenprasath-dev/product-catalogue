---
name: product-source
description: Work on where product data comes from - ProductSource implementations (Fake Store, JSON snapshot, fallback), FakeStoreProductMapper, CatalogueImporter and the catalogue:import command. Use when changing the import or adding a new product source.
---

# Product sources and the import

Architecture (PLAN.md §3–4): **sources** feed the **importer**, the importer writes to Postgres, and
the API reads Postgres through `ProductRepository`. Sources are never used while the API handles a request.

## Rules
- `FakeStoreProductSource` is the only class that calls the Fake Store URL (`config('catalogue.source_url')`).
- `FakeStoreProductSource` and `JsonFileProductSource` must both map items through
  `FakeStoreProductMapper`, so they produce identical `Product` DTOs (`source = 'fakestore'`,
  same `external_id`). This is what prevents duplicates when switching between snapshot and live data.
- Any failure (HTTP error, timeout, bad JSON, missing field) → throw `CatalogueUnavailable`.
  `FallbackProductSource` catches it and logs a warning.
- `CatalogueImporter` upserts inside **one** DB transaction: categories by `slug`, products by
  `(source, external_id)`. Never delete products, because enquiries reference them.
- The source is selected only in `CatalogueServiceProvider`, using `catalogue.source` and
  `catalogue.fallback_to_file`.

## Adding a new source (e.g. CSV, a supplier API)
1. `app/Catalogue/Sources/<Name>ProductSource.php` implements `ProductSource`. If its data has a
   different shape, give it its own mapper and its own `source` value.
2. Add a `CATALOGUE_SOURCE` option in `config/catalogue.php` and the provider binding.
3. Write a unit test for the mapping and a test for the failure path, then a case in `ImportCatalogueCommandTest`.
4. Add the new env vars to `.env.example` and the TECH_STACK.md table, and the reason to DECISIONS.md.

## Tests
`Http::fake()` using `database/data/fakestore-products.json` as the response body, plus
`Http::preventStrayRequests()`. Cover: success, re-run gives no duplicates, fallback used, every
source fails → exit code 1 with the DB unchanged.
