---
name: api-endpoint
description: Add or change a Laravel API endpoint under /api/v1 (route, thin controller, FormRequest, CatalogueService method, ProductResource, feature test). Use whenever an endpoint in api/ is created or modified.
---

# Add or change an API endpoint

Follow PLAN.md §4 (endpoints) and CLAUDE.md. One endpoint per small, reviewable step.

## Steps
1. **Test first.** Add or extend the feature test in `api/tests/Feature/<Name>Test.php`, using the
   cases listed for it in PLAN.md §7. Use `RefreshDatabase`, model factories, `Mail::fake()`, and
   `Http::preventStrayRequests()`.
2. **Route** in `api/routes/api.php` inside the `v1` prefix group. Apply `throttle` to any write route.
3. **Validation** goes in a FormRequest (`app/Http/Requests/`), never in the controller. Put limits
   (max lengths, `exists:` rules) there.
4. **Logic** goes in `App\Catalogue\CatalogueService`. It reads products only through `ProductRepository`.
   It never calls `ProductSource` or `Http`.
5. **Controller** (`app/Http/Controllers/Api/`): about 5 lines. It calls the service and returns a Resource.
6. **Response** goes through `ProductResource` (or a new Resource), never models, arrays of columns,
   or upstream payloads. No `external_id`, `source` or timestamps unless the client needs them.
7. **Errors:** unknown resource → 404 JSON; validation → 422; email failure → 502. No stack traces.

## Done when
- `docker compose exec api php artisan test --filter=<Name>Test` passes, then the full suite passes.
- PLAN.md's endpoint table still matches (update it if the contract changed).
- Any new env var is in `.env.example` and in the TECH_STACK.md §8 table.
