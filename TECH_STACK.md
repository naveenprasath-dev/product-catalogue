# Tech Stack — Product Catalogue PoC

Every language, framework, library, service and tool this project uses, and why.
Scope and architecture: PLAN.md · Assumptions: DECISIONS.md.

Exact versions are pinned when the project is scaffolded (in `composer.lock`, `package-lock.json`
and the Docker image tags) and must not float. **Nothing here needs a paid service or an account.**

## 1. Overview

```
 Browser ──► web (React + Vite) ──► api (Laravel) ──► db (PostgreSQL)
             :5173                  :8000      │
                                               ├──► mailpit (SMTP :1025, UI :8025)
                                               └──► fakestoreapi.com (only during catalogue:import;
                                                    falls back to the committed JSON snapshot)
```

All four services run in Docker with `docker compose up --build`. The only thing the host needs is Docker.

## 2. Services (docker-compose)

| Service | Image | Port(s) | Role |
|---|---|---|---|
| `web` | `node:22-alpine` | 5173 | React client served by the Vite dev server |
| `api` | `php:8.4-cli` + `pdo_pgsql` + Composer | 8000 | Laravel JSON API (`/api/v1`). Entrypoint: `migrate --force` → `catalogue:import --if-empty` → `php -S` with Laravel's router script (not `artisan serve`, which drops container env vars) |
| `db` | `postgres:16-alpine` | 5432 | Holds categories, products and enquiries. Data lives in the named volume `pgdata`; `pg_isready` healthcheck; `init.sql` creates `catalogue_test` |
| `mailpit` | `axllent/mailpit` | 1025 (SMTP), 8025 (UI) | Local stand-in for the sales inbox: catches every email the app sends |

### External service
| Service | Used by | When | Notes |
|---|---|---|---|
| Fake Store API (`https://fakestoreapi.com`) | `FakeStoreProductSource` | Only when `catalogue:import` runs (first launch or a manual re-sync) | Free and public. Never called from the browser or while the API handles a request. URL set by `CATALOGUE_SOURCE_URL` |

**Fallback:** `api/database/data/fakestore-products.json`, a committed snapshot of the same data, read by
`JsonFileProductSource`. Used when Fake Store is unreachable (`CATALOGUE_FALLBACK_TO_FILE=true`) or on
purpose with `CATALOGUE_SOURCE=file`, so setup also works offline. Image URLs still point to Fake Store.

## 3. Backend (`api/`)

| Item | Choice | Why |
|---|---|---|
| Language | PHP 8.4 | Typed properties, readonly classes, enums; `declare(strict_types=1)` everywhere |
| Framework | Laravel (latest stable), used as an API only | Its service container, Mail drivers, HTTP client, FormRequests and API Resources each provide a swap point for one of the things expected to change |
| Dependency manager | Composer 2 | Standard for PHP |
| ORM / DB access | Eloquent + migrations, `pdo_pgsql` driver | The database engine is chosen by `DB_CONNECTION`; no engine-specific SQL outside migrations |
| Calls to Fake Store | Laravel HTTP client (wraps Guzzle) | Timeout and retry; `Http::fake()` in tests |
| Validation | FormRequest (`StoreEnquiryRequest`) | Validation stays out of controllers |
| Response shape | API Resources (`ProductResource`) | Our own response shape, separate from upstream data and table columns |
| Email | Laravel Mail + `EnquiryReceived` Mailable | Provider chosen by `MAIL_MAILER` (SMTP → Mailpit locally, `ses` later) with no code change |
| Rate limiting | Laravel `throttle` middleware | Protects `POST /enquiries` |
| CORS | Laravel's built-in CORS config | Only the web origin is allowed |
| Console | Artisan command `catalogue:import` | Seeds and re-syncs the catalogue |
| Cache / session | `CACHE_STORE=file`, `SESSION_DRIVER=array` | The API is stateless, so cache and sessions don't need the database |
| Queue | None for the PoC (mail is sent synchronously) | Next step: `ShouldQueue` with the `database` queue driver, then SQS |

## 4. Frontend (`web/`)

| Item | Choice | Why |
|---|---|---|
| Language | TypeScript (strict mode, no `any`) | The brief prefers TypeScript; type safety at the API boundary |
| UI library | React | Widely known; quick for a reviewer to read |
| Build / dev server | Vite | Fast start, simple config; reads `VITE_API_URL` |
| Routing | React Router | Pages: Home, Category, Search, Product detail, Enquiry |
| HTTP | Native `fetch`, wrapped in `src/api/client.ts` | No extra dependency; `client.ts` is the only module that knows the API URL |
| Styling | Plain CSS + CSS variables (theme tokens) | Rebranding means changing the tokens, not the components; no CSS framework needed |
| Runtime (container) | Node.js 22 LTS | Long-term support; matches the `web` image |

## 5. Database

| Item | Choice | Why |
|---|---|---|
| Engine | PostgreSQL 16 | Solid, free, and the same engine as a likely AWS RDS setup |
| Tables | `categories`, `products`, `enquiries` | Schema in PLAN.md §4 |
| Seeding | `php artisan catalogue:import` (also called by `DatabaseSeeder`) | Imports from Fake Store, or the JSON snapshot if it's down; safe to run again (upserts by `(source, external_id)`) |
| Test database | `catalogue_test` (same Postgres container) | Tests use the same engine and never touch dev data |

## 6. Testing

| Layer | Tool | What it covers |
|---|---|---|
| api feature tests | PHPUnit (Laravel default) + `RefreshDatabase` | Every endpoint and the import command, against real Postgres |
| api unit tests | PHPUnit | Fake Store JSON → `Product` mapping; JSON file source; fallback source; top-rated split |
| Test doubles (api) | `Http::fake()` + `Http::preventStrayRequests()`, `Mail::fake()`, model factories | No real network, no real email |
| web tests | Vitest + React Testing Library (+ jsdom) | Page behaviour: search, enquiry submit, loading, empty and error states |
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
| ESLint (flat config in `web/eslint.config.js`: typescript-eslint, react-hooks, react-refresh) | TypeScript/React linting. Replaces the oxlint the current Vite template ships, because typescript-eslint needs TypeScript < 6.1 (TS pinned to ~6.0) |
| `tsc --noEmit` | Type checking for the web app |
| Git + GitHub | `main` holds only an empty initial commit; all work (code, docs, config) is on `feature/catalogue-poc`; PR into `main` left open |
| Claude Code | AI pair programmer; rules in CLAUDE.md, workflow skills in `.claude/skills/` (PLAN.md §11), log in AI_LOG.md → AI_USAGE.md |
| Laravel Boost (`laravel/boost`, dev only) | Version-aware Laravel guidelines (`api/CLAUDE.md`), Laravel skills (`api/.claude/skills/`) and an MCP server (DB schema, routes, logs, docs search) run inside the api container via root `.mcp.json` |

## 8. Configuration (env vars, all in `.env.example`)

| Variable | Used by | Local default |
|---|---|---|
| `APP_ENV`, `APP_DEBUG`, `APP_KEY` | api | `local`, `false`, generated |
| `DB_CONNECTION`, `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD` | api | `pgsql`, `db`, `5432`, `catalogue`, local-only credentials |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_FROM_ADDRESS` | api | `smtp`, `mailpit`, `1025`, a placeholder address |
| `SALES_INBOX_ADDRESS` | api | a placeholder address (shows up in Mailpit) |
| `CATALOGUE_SOURCE` | api | `fakestore` (or `file` for the offline snapshot) |
| `CATALOGUE_SOURCE_URL` | api | `https://fakestoreapi.com` |
| `CATALOGUE_FALLBACK_TO_FILE` | api | `true` |
| `CATALOGUE_SNAPSHOT_PATH` | api | `database/data/fakestore-products.json` |
| `FEATURED_PRODUCT_IDS` | api | comma-separated Fake Store ids (5) |
| `CORS_ALLOWED_ORIGIN` | api | `http://localhost:5173` |
| `CACHE_STORE`, `SESSION_DRIVER` | api | `file`, `array` |
| `VITE_API_URL` | web | `http://localhost:8000/api/v1` |

## 9. Production path (not built in the PoC)

| Local | Production on AWS |
|---|---|
| `php -S` (built-in server) | php-fpm + nginx, or Laravel Octane, on ECS Fargate |
| Vite dev server | Static build on S3 + CloudFront |
| Postgres container | Amazon RDS for PostgreSQL |
| Mailpit | Amazon SES (`MAIL_MAILER=ses`) |
| Synchronous mail | Queued mail (`ShouldQueue`) on SQS |
| Import at container start | Scheduled task (Laravel scheduler / EventBridge + ECS) |
| `.env` file | Secrets Manager / SSM Parameter Store |
