# Tech Stack — Product Catalogue PoC

Every language, framework, library, service and tool this project uses, and why.
Scope and architecture: PLAN.md · Assumptions: DECISIONS.md.

Exact versions are pinned when the project is scaffolded (in `composer.lock`, `package-lock.json`
and the Docker image tags) and must not float. **Nothing here needs a paid service or an account.**

## 1. Overview

```
 Browser ──► web (React + Vite) ──► api (Laravel) ──► db (PostgreSQL)
             :5173                  :8000      │
 Browser ──► /admin (Inertia) ──────┘          ├──► mailpit (SMTP :1025, UI :8025)
                                               ├──► fakestoreapi.com (only during catalogue:import;
                                               │    falls back to the committed JSON snapshot)
                                               └──► api.frankfurter.dev (exchange rates; only during
                                                    currencies:sync-rates / admin "Sync live rates")
```

All four services run in Docker with `docker compose up --build`. The only thing the host needs is Docker.

## 2. Services (docker-compose)

| Service | Image | Port(s) | Role |
|---|---|---|---|
| `web` | `node:22-alpine` (`web/Dockerfile`; the only image tag not pinned to a patch version) | 5173 | React client served by the Vite dev server |
| `api` | `php:8.4.28-cli-bookworm` + `pdo_pgsql` + `composer:2.11.2` + Node from `node:22.22.0-bookworm-slim` | 8000 | Laravel JSON API (`/api/v1`) and the `/admin` panel. Entrypoint: `composer install` → stable `APP_KEY` → `migrate --force` → `catalogue:import --if-empty` → `currencies:sync-rates --if-never-updated` (not fatal) → (if `ADMIN_ENABLED=true`) `admin:create --from-env`, `npm ci`, admin build when stale → `php -S` with Laravel's router script (not `artisan serve`, which drops container env vars) |
| `db` | `postgres:16.16-alpine` | 5432 | Holds categories, products, enquiries, users and currencies. Data lives in the named volume `pgdata`; `pg_isready` healthcheck; `init.sql` creates `catalogue_test` |
| `mailpit` | `axllent/mailpit:v1.34.0` | 1025 (SMTP), 8025 (UI) | Local stand-in for the sales inbox: catches every email the app sends |

### External services
| Service | Used by | When | Notes |
|---|---|---|---|
| Fake Store API (`https://fakestoreapi.com`) | `FakeStoreProductSource` | Only when `catalogue:import` runs (first launch or a manual re-sync) | Free and public. Never called from the browser or while the API handles a request. URL set by `CATALOGUE_SOURCE_URL` |
| Frankfurter v2 (`https://api.frankfurter.dev/v2`, `GET /rates?base=USD`) | `FrankfurterRateSource` (behind `CurrencyRateSource`), used only by `RateSyncer` | `currencies:sync-rates` (once on first start with `--if-never-updated`, or by hand) and the admin "Sync live rates" button | Open source (MIT), free, **no API key or account**; European Central Bank reference rates, published on working days. Never called from the browser or during a shop request. URL set by `CURRENCY_RATES_URL`; timeout 10s, 2 retries (`config/currency.php`). Any failure → `RatesUnavailable`, no rate changes |

**Fallback:** `api/database/data/fakestore-products.json`, a committed snapshot of the same data, read by
`JsonFileProductSource`. Used when Fake Store is unreachable (`CATALOGUE_FALLBACK_TO_FILE=true`) or on
purpose with `CATALOGUE_SOURCE=file`, so setup also works offline. Image URLs still point to Fake Store.

## 3. Backend (`api/`)

| Item | Choice | Why |
|---|---|---|
| Language | PHP 8.4 | Typed properties, readonly classes, enums; `declare(strict_types=1)` everywhere |
| Framework | Laravel 13 (`laravel/framework` 13.43.0), a JSON API plus the `/admin` panel | Its service container, Mail drivers, HTTP client, FormRequests and API Resources each provide a swap point for one of the things expected to change |
| Shop auth | `laravel/sanctum` 4.5.0, SPA cookie auth (no API tokens) | First-party; session cookie + CSRF for the shop origin, nothing stored in the browser's JS storage |
| Admin login | `laravel/fortify` 1.43.0 (login/logout controllers only; routes registered by hand) | First-party headless auth used by the starter kit; admins only |
| Admin server-side rendering bridge | `inertiajs/inertia-laravel` 3.7.0 | Lets the admin's React pages be served by Laravel routes/controllers without a separate API |
| Typed routes for the admin front-end | `laravel/wayfinder` 0.1.23 | Generates TypeScript functions for Laravel routes/actions (`resources/js/{actions,routes}`) |
| Dependency manager | Composer 2 | Standard for PHP |
| ORM / DB access | Eloquent + migrations, `pdo_pgsql` driver | The database engine is chosen by `DB_CONNECTION`; no engine-specific SQL outside migrations |
| Calls to Fake Store | Laravel HTTP client (wraps Guzzle) | Timeout and retry; `Http::fake()` in tests |
| Validation | FormRequests (`ProductQueryRequest` (`?currency=`) and `SearchProductsRequest`, `StoreEnquiryRequest`, `Auth\LoginRequest`, `Auth\RegisterRequest`, `Admin\ListEnquiriesRequest` (lenient: bad filters are ignored), `Admin\{Store,Update}CurrencyRequest`) | Validation stays out of controllers |
| Response shape | API Resources (`ProductResource`, `CurrencyResource`, …) | Our own response shape, separate from upstream data and table columns. `ProductResource` converts `price` into the request's currency through `CurrencyConverter` |
| Currency conversion | `App\Catalogue\Currency\CurrencyConverter` (plain PHP `round()`, no bcmath) | The one place prices are converted: stored USD × stored rate, rounded half-up to 2 decimals. Base currency `USD` is fixed in `config/currency.php` |
| Email | Laravel Mail + `EnquiryReceived` Mailable | Provider chosen by `MAIL_MAILER` (SMTP → Mailpit locally, `ses` later) with no code change |
| Rate limiting | Laravel `throttle` middleware | Protects `POST /enquiries`, shop register/login, admin login, re-send, currency writes (30/min) and "Sync live rates" (6/min) |
| Admin hardening | `AdminSecurityHeaders` middleware, Inertia `EncryptHistory` middleware | Every `/admin` response: `X-Frame-Options: DENY`, `CSP frame-ancestors 'none'`, `Cache-Control: no-store, private`, `nosniff`, `Referrer-Policy: same-origin`. Inertia history encrypted and cleared on logout (docs/qa/ADMIN_QA.md) |
| CORS | Laravel's built-in CORS config | Only the web origin is allowed, with credentials (paths `api/*`, `sanctum/csrf-cookie`) |
| Console | Artisan commands `catalogue:import`, `admin:create`, `currencies:sync-rates [--if-never-updated]` | Seeds and re-syncs the catalogue; bootstraps/resets admins; refreshes exchange rates |
| Cache / session | `CACHE_STORE=file`, `SESSION_DRIVER=file` | No DB tables for either. The API is stateless for non-shop callers (curl, other origins: no cookies, no CSRF); the shop origin (`SANCTUM_STATEFUL_DOMAINS`) and `/admin` get a cookie session |
| Queue | None for the PoC (mail is sent synchronously) | Next step: `ShouldQueue` with the `database` queue driver, then SQS |

## 4. Frontend (`web/`)

| Item | Choice | Why |
|---|---|---|
| Language | TypeScript (strict mode, no `any`) | The brief prefers TypeScript; type safety at the API boundary |
| UI library | React | Widely known; quick for a reviewer to read |
| Build / dev server | Vite | Fast start, simple config; reads `VITE_API_URL` |
| Routing | React Router 8 | Pages: Home, Category, Search, Product detail (+ enquiry form), Login, Register |
| HTTP | Native `fetch`, wrapped in `src/api/client.ts` | No extra dependency; `client.ts` is the only module that knows the API URL. Sends `credentials: 'include'` and the `X-XSRF-TOKEN` header for Sanctum |
| Auth state | `AuthProvider` + `useAuth()` (React context) | Asks `/auth/me` on start; no tokens or user data in localStorage |
| Currency state | `CurrencyProvider` + `useCurrency()` (React context), `useProductApi` | Loads `/currencies` once; the selected code is a display preference kept in localStorage (`catalogue.currency`). Product requests send `?currency=`; the client formats with `Intl.NumberFormat`, never converts |
| Styling | Plain CSS + CSS variables (theme tokens) | Rebranding means changing the tokens, not the components; no CSS framework needed |
| Runtime (container) | Node.js 22 LTS | Long-term support; matches the `web` image |

Resolved versions (web `package-lock.json`): React 19.3.1, React Router 8.5.2, TypeScript 6.0.3,
Vite 8.3.8, Vitest 5.1.1, MSW 3.0.7, ESLint 10.15.0, typescript-eslint 8.73.0.

## 4a. Admin frontend (`api/resources/js`, served at `/admin`)

Kept separate from `web/` on purpose: its own `package.json`/lockfile in `api/`, its own toolchain.

| Item | Choice (resolved version) | Why |
|---|---|---|
| Base | Laravel React starter kit (pinned commit, see DECISIONS.md), trimmed to login + sidebar layout | Ready-made admin shell; no time spent on layout |
| UI library | React 19.2 range (`^19.2.0`, resolved 19.3.1) + Inertia (`@inertiajs/react` 3.11.0) | Pages are rendered from Laravel controllers; no separate admin API |
| Language | TypeScript `^5.7.2` (resolved 5.9.3) | The kit's version; web/ uses TS 6.0 with ESLint |
| Styling | Tailwind CSS 4 (4.3.5) + shadcn/ui components (`components.json`, Radix primitives, lucide-react icons) | Kit default; colours are shadcn CSS tokens in `resources/css/app.css` |
| Build / check | vite-plus 1.4.0 (`vp build`, `vp check`; `vite` aliased to `@voidzero-dev/vite-plus-core`) + `laravel-vite-plugin` 3.2.1 | Kit default; one tool for build, format and lint |
| Typed routes | Wayfinder (`@laravel/vite-plugin-wayfinder` 0.1.11 + `laravel/wayfinder`) | Runs `php artisan wayfinder:generate` during the build |
| Runtime | Node 22 (`node:22.22.0-bookworm-slim` binaries copied into the api image) | Wayfinder needs PHP and Node in the same container |
| Install safety | `.npmrc` `ignore-scripts=true` | No dependency install scripts run |

Checks: `docker compose exec api npm run types:check`, `npm run check`, `npm run build`.

## 5. Database

| Item | Choice | Why |
|---|---|---|
| Engine | PostgreSQL 16 | Solid, free, and the same engine as a likely AWS RDS setup |
| Tables | `categories`, `products`, `enquiries`, `users` (`is_admin` flag; `enquiries.user_id` nullable FK), `currencies` (code PK, rate per 1 USD; USD/EUR/GBP/INR inserted by its migration) | Schema in PLAN.md §4 |
| Seeding | `php artisan catalogue:import` (also called by `DatabaseSeeder`) | Imports from Fake Store, or the JSON snapshot if it's down; safe to run again (upserts by `(source, external_id)`) |
| Test database | `catalogue_test` (same Postgres container) | Tests use the same engine and never touch dev data |

## 6. Testing

| Layer | Tool | What it covers |
|---|---|---|
| api feature tests | PHPUnit 12.5 (12.5.45) + `RefreshDatabase` | Every endpoint and the import command, shop auth + CSRF, the stateless API and the admin panel (`assertInertia`), against real Postgres |
| api unit tests | PHPUnit | Fake Store JSON → `Product` mapping; JSON file source; fallback source; top-rated split; currency conversion rounding |
| Test doubles (api) | `Http::fake()` + `Http::preventStrayRequests()` (Fake Store and Frankfurter), `Mail::fake()`, model factories | No real network, no real email |
| web tests | Vitest + React Testing Library (+ jsdom) | Page behaviour: search, enquiry submit, currency switching, loading, empty and error states |
| API mocking (web) | MSW (Mock Service Worker) | Fakes API responses at the network level, so tests run the real `client.ts` |

Run inside containers:
```bash
docker compose exec api php artisan test
docker compose exec web npm test
```

## 7. Tooling and quality

| Tool | Purpose |
|---|---|
| Docker + Docker Compose | Runs every service locally with one command |
| Laravel Pint | PHP code style (included with Laravel) |
| ESLint 10 (flat config in `web/eslint.config.js`: typescript-eslint, react-hooks, react-refresh) | TypeScript/React linting for `web/`. Replaces the oxlint the current Vite template ships, because typescript-eslint needs TypeScript < 6.1 (TS pinned to ~6.0) |
| `tsc --noEmit` | Type checking for the web app (and `npm run types:check` for the admin front-end) |
| vite-plus `vp check` | Format + lint for the admin front-end in `api/` (`npm run check`) |
| Git + GitHub | `main` holds only an empty initial commit; all work (code, docs, config) is on `feature/catalogue-poc`; PR into `main` left open |
| Claude Code | AI pair programmer; rules in CLAUDE.md, workflow skills in `.claude/skills/` (PLAN.md §11), log in AI_LOG.md → AI_USAGE.md |
| Laravel Boost (`laravel/boost` 2.10.4, `require-dev` only) | Dev-only AI tooling. An MCP server that gives AI agents Laravel docs search, app info (routes, DB schema, config) and logs, run inside the api container via the root `.mcp.json` (`api/.mcp.json` is Boost's in-container variant); plus generated guidelines and skills in `api/CLAUDE.md`, `api/.claude/skills/`, `api/.ai/guidelines/` and `api/boost.json`. Never installed in production (dev dependency); it registers only in a local environment |

## 8. Configuration (env vars, all in `.env.example`)

| Variable | Used by | Local default |
|---|---|---|
| `APP_NAME`, `APP_URL`, `LOG_CHANNEL` | api | `Product Catalogue`, `http://localhost:8000`, `stderr` |
| `APP_ENV`, `APP_DEBUG`, `APP_KEY` | api | `local`, `false`, empty → generated once into `api/storage/app/.app-key` |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | api | `pgsql`, `db`, `5432`, `catalogue`, local-only credentials |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | api | `smtp`, `mailpit`, `1025`, a placeholder address, `Product Catalogue` |
| `SALES_INBOX_ADDRESS` | api | a placeholder address (shows up in Mailpit) |
| `CATALOGUE_SOURCE` | api | `fakestore` (or `file` for the offline snapshot) |
| `CATALOGUE_SOURCE_URL` | api | `https://fakestoreapi.com` |
| `CATALOGUE_FALLBACK_TO_FILE` | api | `true` |
| `CATALOGUE_SNAPSHOT_PATH` | api | `database/data/fakestore-products.json` |
| `FEATURED_PRODUCT_IDS` | api | comma-separated Fake Store ids (5) |
| `CURRENCY_RATES_URL` | api | `https://api.frankfurter.dev/v2` (Frankfurter v2; no key) |
| `CORS_ALLOWED_ORIGIN` | api | `http://localhost:5173` |
| `SANCTUM_STATEFUL_DOMAINS` | api | `localhost:5173` (shop origin; gets cookie session + CSRF on `/api`) |
| `CACHE_STORE`, `QUEUE_CONNECTION` | api | `file`, `sync` |
| `SESSION_DRIVER`, `SESSION_LIFETIME` | api | `file`, `120` (minutes) |
| `SESSION_PATH`, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE` | api | `/`, empty (host-only cookie), `false` (`true` behind HTTPS) |
| `ADMIN_ENABLED` | api | `true` in `.env.example`/compose (code default `false`) |
| `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` | api | `Admin`, `admin@example.com`, `local-admin-password` (local-only; first admin created on start) |
| `VITE_API_URL` | web | `http://localhost:8000/api/v1` |

Fixed values (not env vars): `config/catalogue.php` (Fake Store timeout/retries, top-rated count, enquiry
limit) and `config/currency.php`: `base` = `USD` (changing it would need re-priced products),
`rates_timeout` = 10 seconds, `rates_retries` = 2.

## 9. Production path (not built in the PoC)

| Local | Production on AWS |
|---|---|
| `php -S` (built-in server) | php-fpm + nginx, or Laravel Octane, on ECS Fargate |
| Vite dev server | Static build on S3 + CloudFront |
| Postgres container | Amazon RDS for PostgreSQL |
| Mailpit | Amazon SES (`MAIL_MAILER=ses`) |
| Synchronous mail | Queued mail (`ShouldQueue`) on SQS |
| Import at container start | Scheduled task (Laravel scheduler / EventBridge + ECS) |
| Rate sync at first start / by hand | `currencies:sync-rates` on a daily schedule |
| `.env` file | Secrets Manager / SSM Parameter Store |
