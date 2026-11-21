# CLAUDE.md — agent instructions for this repo

Product catalogue PoC: React + TypeScript client (`web/`), Laravel API (`api/`).
See PLAN.md for scope, TECH_STACK.md for the stack, and DECISIONS.md for assumptions. This is a foundation that will grow,
not a throwaway prototype.

## Architecture rules (non-negotiable)
- The client NEVER calls the Fake Store API (or any product source) directly. All product data
  goes through `api/`.
- API requests read products from Postgres only, through `App\Catalogue\Contracts\ProductRepository`.
  No request-time calls to Fake Store.
- Fake Store is reached only via `App\Catalogue\Contracts\ProductSource` (`FakeStoreProductSource`),
  and only the `catalogue:import` command / `CatalogueImporter` uses a source. Never call
  `Http::get('fakestoreapi...')` anywhere else.
- The JSON fallback (`JsonFileProductSource`) and the live source share `FakeStoreProductMapper`;
  keep both producing identical `Product` DTOs (same `source`, same `external_id`).
- The API returns our own shape (`ProductResource`), never raw upstream payloads.
- Email is sent only through Laravel Mail + the `EnquiryReceived` Mailable. Never hard-code
  a provider SDK; the provider is chosen by `MAIL_MAILER`.
- Controllers stay thin: validation in FormRequests, logic in `CatalogueService`.
- Shop sign-in uses Sanctum SPA cookie auth (session cookie + CSRF). Never store auth tokens in
  `localStorage`/`sessionStorage`, and don't issue bearer tokens to the shop.
- The current user's id always comes from the session (`$request->user()`), never from the request body.
- `/admin` (Inertia + React starter kit, in `api/`) goes through the same services and repositories:
  every admin route is behind `auth` + `can:admin`, controllers stay thin, admin styling uses the kit's
  token classes and never touches `web/`.
- `web/src/api/client.ts` is the only module that knows the API base URL.
- Styling goes through CSS variables (theme tokens); no hard-coded brand colours in components.

## Code conventions
- PHP 8.4, `declare(strict_types=1);`, readonly DTOs, constructor injection, typed returns.
- TypeScript strict mode; no `any`.
- Config via `config/*.php` + env vars. Every new env var goes into `.env.example`.
- Never commit secrets or `.env`.
- Don't add dependencies without stating why.
- No paid services or accounts. Everything (web, api, Postgres, Mailpit) runs in Docker with `docker compose up`.
- Database access only through Eloquent / Laravel DB; never engine-specific SQL outside migrations.

## Errors and security
- Upstream failure during import → `CatalogueUnavailable` → fall back to the JSON snapshot (if enabled);
  if every source fails, the command exits 1 and the transaction is rolled back.
- API errors are JSON with no stack traces; email send failure → 502. JSON error rendering applies
  only to `api/*` (or requests that expect JSON); `/admin` gets normal error pages.
- Admin controllers catch domain exceptions (`EnquiryNotSent`, `EnquiryNotResendable`) and flash a
  toast; the API's 502 JSON must never reach an admin page.
- Validate all input server-side; client validation is UX only.
- Escape user input in emails. Rate-limit `POST /enquiries`.

## Testing
- Every endpoint gets a feature test (data from factories, `RefreshDatabase`). Use `Http::fake()`
  for upstream (import tests) and `Mail::fake()` for mail; never hit the real network in tests.
- Run (in containers): `docker compose exec api php artisan test`, `docker compose exec web npm test`.
  API tests use the `catalogue_test` Postgres database, never the dev database.
- Don't mark work done until the tests pass.

## Working style
- Git: `main` holds only the empty initial commit; all work goes on `feature/catalogue-poc` and
  reaches `main` through the PR. Ask before pushing (the repo is public).
- Small, reviewable steps. Explain non-obvious decisions in a short comment or in DECISIONS.md.
- Use the project skills in `.claude/skills/` for those tasks: task skills (`api-endpoint`,
  `product-source`, `web-page`, `admin-page`, `catalogue-data`, `run-checks`, `pr-ready`,
  `git-workflow`, `ai-log`) and convention skills (`laravel-backend`, `laravel-database`,
  `laravel-testing`, `react-frontend`, `react-testing`). Run `run-checks` before
  calling anything done; run `ai-log` after notable decisions or AI mistakes.
- In `api/`, Laravel Boost adds version-aware guidelines (`api/CLAUDE.md`), skills and the
  `laravel-boost` MCP server (needs `docker compose up`). Use its `search-docs` tool before relying
  on a Laravel API from memory. Project rules here win over Boost's generic guidance.
- If a requirement is ambiguous, propose an assumption and record it in DECISIONS.md. Don't guess silently.
