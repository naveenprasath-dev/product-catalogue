# Product Catalogue PoC

A small product catalogue: a home page with 5 curated "top rated" products, category pages
(top rated + the rest), server-side search, product details and an enquiry form that emails a
sales inbox. Customers can optionally create an account (the enquiry form is then pre-filled), and a
small `/admin` panel lets staff see and re-send enquiries. React + TypeScript client (`web/`), Laravel
JSON API + admin panel (`api/`), PostgreSQL and Mailpit, all in Docker.

**Features**

- Shop: home page with 5 curated products, category pages (top 3 by rating + the rest), server-side
  search, product details with an enquiry form that emails the sales inbox (Mailpit locally).
- **Currencies**: a dropdown in the shop header shows prices in USD, EUR, GBP or INR (or any currency
  an admin adds). The API converts the prices; rates come from a free, open-source rate service. See [Currencies](#currencies).
- Optional shop accounts (Sanctum cookie auth): the enquiry form is pre-filled when signed in.
- `/admin` panel: dashboard, enquiries (filter, detail, re-send), currencies (add, edit, set the default,
  delete, sync live rates). Hardened after a QA pass ([docs/qa/ADMIN_QA.md](docs/qa/ADMIN_QA.md)).

It is built as a **foundation, not a throwaway**. The brief says the product data source, the look
and feel and the email provider will all change, so each of those sits behind one swap point:
products are imported from [Fake Store](https://fakestoreapi.com) into Postgres through a
`ProductSource` contract (with a committed JSON snapshot as fallback), the API only ever reads
Postgres and returns its own response shape, email goes through Laravel Mail (provider picked by
`MAIL_MAILER`), and the UI is styled only through CSS theme tokens.

Contents: [Quick start](#quick-start) · [Shop accounts](#shop-accounts) · [Currencies](#currencies) ·
[Admin panel](#admin-panel) · [Tests and checks](#tests-and-checks) ·
[Catalogue data](#catalogue-data) · [Architecture](#architecture) · [API](#api) ·
[Configuration](#configuration) · [Project layout](#project-layout) · [Stack](#stack) ·
[Decisions and cuts](#decisions-and-cuts) · [AI-assisted workflow](#ai-assisted-workflow) ·
[Production path](#production-path) · [Troubleshooting](#troubleshooting) · [Screenshots](#screenshots)

## Quick start

**Prerequisites:** Docker with Docker Compose, and curl. Nothing else (no local PHP, Node or
Postgres), and no accounts or paid services.

**Recommended: one command** (from a fresh clone):

```bash
./scripts/setup.sh
```

It copies `.env.example` to `.env` (only if `.env` is missing), runs `docker compose up -d --build`,
waits for Postgres, the API (`/up` and `/api/v1/categories`), the web app and Mailpit, seeds the demo
data (idempotent), then **checks the logins for real** with curl: the admin logs in and sees the
dashboard and the demo enquiries, a wrong password is refused, and the demo customer can log in to the
shop but gets a 403 on `/admin`. It prints a ✔/✘ summary, the URLs and the local demo logins, and
exits non-zero if any check fails.

| Command | What it does |
|---|---|
| `./scripts/setup.sh` | Build, start, seed demo data, run the checks |
| `./scripts/setup.sh --check` | Only the checks, against the running stack (no build, no seeding); a quick smoke test |
| `./scripts/setup.sh --reset` | `docker compose down -v` first (**deletes the database**: asks y/N), then a fresh setup |
| `./scripts/setup.sh --reset --yes` | Same, without the prompt |

**Alternative: plain Docker Compose**

```bash
cp .env.example .env
docker compose up --build
```

`docker-compose.yml` has the same defaults built in, so the first line is optional; copy it when you
want to change a setting. This **also seeds the demo data** on start (`SEED_DEMO_DATA=true`), so the
admin panel is populated either way; only the login checks are skipped.

| What | URL |
|---|---|
| Web app | http://localhost:5173 |
| API | http://localhost:8000/api/v1 (e.g. http://localhost:8000/api/v1/products/featured) |
| Mailpit (the "sales inbox") | http://localhost:8025 |
| Admin panel | http://localhost:8000/admin (local login `admin@example.com` / `local-admin-password`) |

**Local demo logins** (local-only defaults from `.env`; change them for anything that isn't your machine):

| Who | Where | Email / password |
|---|---|---|
| Admin | http://localhost:8000/admin | `admin@example.com` / `local-admin-password` (`ADMIN_EMAIL` / `ADMIN_PASSWORD`) |
| Demo customer | http://localhost:5173/login | `customer@example.com` / `local-customer-password` (`DEMO_CUSTOMER_EMAIL` / `DEMO_CUSTOMER_PASSWORD`) |

**What happens on start** (`api/docker/entrypoint.sh`):

1. `composer install` (fast no-op when `vendor/` is up to date).
2. `APP_KEY`: a set `APP_KEY` env var wins; otherwise a key is generated **once** and stored in
   `api/storage/app/.app-key` (gitignored), so login sessions survive restarts.
3. `php artisan migrate --force` creates the `categories`, `products`, `enquiries`, `users` and
   `currencies` tables (the currencies migration also inserts USD, EUR, GBP and INR with starting rates).
4. `php artisan catalogue:import --if-empty` imports the catalogue from Fake Store
   (20 products, 4 categories). If Fake Store is unreachable it logs a warning and imports the
   committed snapshot `api/database/data/fakestore-products.json` instead. On later starts the
   products table is not empty, so this step is skipped and startup doesn't depend on Fake Store.
5. `php artisan currencies:sync-rates --if-never-updated` replaces the starting rates with live ones,
   once. It is skipped after any sync or admin edit, and it is not fatal: offline, the stored rates are used.
6. If `ADMIN_ENABLED=true`: `php artisan admin:create --from-env` creates the first admin from
   `ADMIN_*` (idempotent, never resets a password), `npm ci` syncs the admin panel's `node_modules`,
   and `npm run build` builds the admin front-end when `public/build` is missing or older than its sources.
7. If `SEED_DEMO_DATA=true` (the local default): `php artisan db:seed --class=DemoSeeder` (see
   [Demo data](#demo-data)). Not fatal: a failure logs one line and the server still starts.
8. PHP's built-in server (`php -S` with Laravel's router script) on port 8000. Not `artisan serve`,
   which strips the container's env vars from its workers.

The **first** start takes about 30–60 seconds longer than later ones (`npm ci` + the admin build).
Wait for `API listening on http://0.0.0.0:8000` in `docker compose logs api`.

Then submit an enquiry from any product page and open Mailpit to see the email.

## Shop accounts

Accounts are optional; guests can browse and enquire exactly as before.

- **Register / Log in / Log out** live in the header (on small screens: a user icon, or the avatar +
  Log out). `/login` and `/register` return you to the page you came from (`?next=`, same-app paths only).
- When signed in, the enquiry form's name and email are **pre-filled** (still editable), and the
  stored enquiry is linked to the account. The API takes the user from the session, never from the body.
- Auth is Laravel Sanctum's SPA **cookie** auth: an HttpOnly session cookie plus a CSRF token
  (`/sanctum/csrf-cookie`, sent back as `X-XSRF-TOKEN`). No tokens in localStorage.
- There is no password-reset or email-verification flow in the PoC.

## Currencies

- **In the shop**: the currency dropdown in the header (next to Log in) switches every price on the
  page. The choice is remembered in the browser (localStorage; it's a display preference, not account
  data). If `/currencies` fails, the dropdown is hidden and prices show in the default currency.
- **Server-side conversion**: product prices are stored in **USD** (the base currency, rate fixed at 1).
  The client sends `?currency=EUR` and the API returns the converted `price` plus its `currency` code.
  `CurrencyConverter` is the only place that converts (stored rate, rounded half-up to 2 decimals); the
  client only formats.
- **Seeded**: USD (the default), EUR, GBP and INR, with starting rates, inserted by the migration.
- **Admin** (`/admin/currencies`): add a currency (ISO 4217 code, name, symbol, rate), edit its name,
  symbol, rate or enabled flag, make it the default, delete it, and **Sync live rates**. The base
  currency can't be disabled or deleted and its rate stays 1. The default must stay enabled. Disabled
  currencies disappear from the dropdown.
- **Live rates** come from [Frankfurter](https://frankfurter.dev) (open source, no API key, European
  Central Bank reference rates, published on working days). Only the sync calls it, never a shop
  request. A sync updates the enabled non-base currencies in one transaction. If Frankfurter fails
  (network, HTTP error, bad data), **no rate changes**: the command exits 1 and the admin sees an error toast.
- **First start** pulls live rates once (`currencies:sync-rates --if-never-updated` in the entrypoint).
  Later starts never overwrite synced or hand-edited rates.

```bash
# Refresh the rates by hand (same as the admin "Sync live rates" button)
docker compose exec api php artisan currencies:sync-rates
# If it fails with MissingAppKeyException (see Troubleshooting), pass the persisted key:
docker compose exec api sh -c 'APP_KEY="$(cat storage/app/.app-key)" php artisan currencies:sync-rates'
```

Enquiries and the sales email are unchanged: they show the stored USD price. Details and reasons:
DECISIONS.md "Currency decisions".

## Admin panel

http://localhost:8000/admin, served by the api container (Laravel + Inertia + React, from Laravel's
React starter kit). Log in locally with **`admin@example.com` / `local-admin-password`**. These are
local-only defaults from `.env.example` (`ADMIN_EMAIL` / `ADMIN_PASSWORD`), created on start by
`php artisan admin:create --from-env`; change them for anything that isn't your machine.

- **Dashboard**: product and category counts, enquiries by status, last import time.

- **Enquiries**: newest first, 20 per page, filter by status (`pending` / `sent` / `failed`), detail page.
- **Re-send**: a `failed` enquiry (or one stuck in `pending` for over 5 minutes) can be re-sent to
  the sales inbox; the result shows as a toast (toasts stay for 6 seconds).
- **Currencies**: list, add, edit, make default, delete and "Sync live rates" (see [Currencies](#currencies)).
- Only users with `is_admin` can log in; shop customers get the same "credentials do not match" error.
  There is no admin registration or password reset page. On small screens the enquiry list shows as cards.

**Security** (from the [admin QA pass](docs/qa/ADMIN_QA.md): 71 cases, 7 bugs found and fixed):

- Every `/admin` response (including the login page, redirects and 403s) is sent with
  `X-Frame-Options: DENY` and `Content-Security-Policy: frame-ancestors 'none'` (no clickjacking of
  one-click actions), `Cache-Control: no-store`, `nosniff` and `Referrer-Policy: same-origin`.
- Inertia page data in the browser history is encrypted and cleared on logout, so Back after logout
  shows nothing.
- Every failed login takes the same time (one password-hash check, also for unknown emails and
  customers), so timing doesn't reveal which emails are admin accounts.
- After 5 failed logins a minute (per email + IP) the form says "Too many attempts. Try again in N
  seconds." A signed-in admin opening `/admin/login` goes to the dashboard.

The panel is **off unless `ADMIN_ENABLED=true`** (then `/admin/*` is a plain 404 and the entrypoint
skips the admin steps). `.env.example` and `docker-compose.yml` set it to `true` for local use.

```bash
# Create another admin (prompts for a hidden password, min 12 characters)
docker compose exec api php artisan admin:create --email=you@example.com
# Reset an existing admin's password
docker compose exec api php artisan admin:create --email=admin@example.com --reset
# Rebuild the admin front-end by hand (e.g. it shows an old build)
docker compose exec api npm run build
```

## Tests and checks

All checks run inside the containers (start the stack first; `docker compose up -d --build` runs it
in the background). The same list is in the `run-checks` skill. For an end-to-end smoke test of the
running stack (services up, admin and customer logins), run `./scripts/setup.sh --check`.

```bash
docker compose exec api php artisan test             # PHPUnit feature + unit tests
docker compose exec api ./vendor/bin/pint --test     # PHP code style
docker compose exec web npm test                     # Vitest (runs once) + React Testing Library + MSW
docker compose exec web npm run lint                 # ESLint, --max-warnings 0
docker compose exec web npx tsc --noEmit             # type check
# admin panel front-end (inside api/)
docker compose exec api npm run types:check          # tsc --noEmit
docker compose exec api npm run check                # vp check (format + lint)
docker compose exec api npm run build                # production build of /admin
```

- API tests use a separate `catalogue_test` database (created by `docker/postgres/init.sql`,
  forced in `api/phpunit.xml`), so they never touch the dev data.
- No test hits the network or sends real mail: `Http::fake()` + `Http::preventStrayRequests()` for
  Fake Store (the snapshot file is the fixture) and Frankfurter, `Mail::fake()` for email, factories for data.
- Web tests mock the API with MSW at the network level, so the real `src/api/client.ts` runs.
- Coverage: every endpoint and the import and rate-sync commands have feature tests
  (`api/tests/Feature/`, including `CurrenciesTest`, `ProductCurrencyTest`, `SyncCurrencyRatesCommandTest`),
  shop auth and CSRF in `api/tests/Feature/Auth/`, the stateless API in `StatelessApiTest`, the admin
  panel in `api/tests/Feature/Admin/` (currencies and security headers too); the mapper, JSON file
  source, fallback source, top-rated split and currency conversion have unit tests (`api/tests/Unit/`);
  each web page has a test next to it (`web/src/pages/*.test.tsx`), plus `client.test.ts` (CSRF,
  credentials), `UserMenu.test.tsx`, `currency/currency.test.tsx` and `lib/format.test.ts`.
  Case list: PLAN.md §7.
- Manual QA of the admin panel and logins: [docs/qa/ADMIN_QA.md](docs/qa/ADMIN_QA.md) (71 cases,
  7 bugs found, all fixed and covered by tests or a live re-check).

## Catalogue data

The API never calls Fake Store. Products get into Postgres only through the import command:

```bash
# Re-sync from Fake Store (snapshot as fallback); safe to repeat, it upserts
docker compose exec api php artisan catalogue:import

# Import from the committed snapshot only (no network)
docker compose exec api php artisan catalogue:import --source=file

# Only import when the products table is empty (what the container entrypoint does)
docker compose exec api php artisan catalogue:import --if-empty
```

- The command prints where the data came from, e.g.
  `20 products, 4 categories imported from fakestore.` or
  `... imported from snapshot (fakestore unavailable).`
- Re-running updates rows in place: categories are upserted by `slug`, products by
  `(source, external_id)`, all in one transaction. Live and snapshot imports map to the same rows.
- If every source fails (Fake Store down **and** snapshot missing/invalid, or fallback disabled), it
  prints the reason, changes nothing and exits 1.
- Products that disappear upstream are kept (enquiries reference them); see DECISIONS.md.
- `php artisan migrate --seed` also works: `DatabaseSeeder` calls `catalogue:import`, then
  `DemoSeeder` when `SEED_DEMO_DATA=true` (never in production).

### Demo data

`Database\Seeders\DemoSeeder` fills the local stack so a reviewer sees a populated admin panel:

- the admin from `ADMIN_*` (through `admin:create --from-env`, so the same password rules apply),
- a demo shop customer, "Demo Customer" (`DEMO_CUSTOMER_EMAIL` / `DEMO_CUSTOMER_PASSWORD`),
- 12 sample enquiries on imported products: `sent`, `failed` and `pending` (two pending ones are
  older than 5 minutes, so they can be re-sent from the admin), some linked to the demo customer,
  some from guests. Each message starts with **`[demo]`**.

It is idempotent (existing accounts are never changed, and no enquiries are added once any `[demo]`
enquiry exists), inserts rows directly so **no email is sent**, skips the enquiries with a message
when there are no products yet, and **refuses to run in production**. It runs on container start
when `SEED_DEMO_DATA=true` (set `false` for an empty admin) and from `./scripts/setup.sh`.

```bash
docker compose exec api php artisan db:seed --class=DemoSeeder   # safe to repeat
```

To start over with fresh demo data: `./scripts/setup.sh --reset` (deletes the database).

**Refreshing the snapshot** (the file is also the test fixture, so run the tests afterwards):

```bash
curl -fsS https://fakestoreapi.com/products -o api/database/data/fakestore-products.json
docker compose exec api php artisan test
```

**Resetting everything** (fresh database, re-import on start):

```bash
docker compose down -v && docker compose up --build
```

> **Warning:** `down -v` deletes the `pgdata` volume, which holds **all enquiries** as well as the
> catalogue. `php artisan migrate:fresh --seed` does the same to the tables.

Inspect the data:

```bash
docker compose exec db psql -U catalogue -d catalogue -c 'select count(*) from products;'
```

## Architecture

```
 Browser ──► web (React + Vite) ──► api (Laravel, php -S) ───► db (PostgreSQL 16)
             :5173                  :8000                │
                                    /api/v1  JSON API    ├──► mailpit (SMTP :1025, UI :8025)
                                    /api/v1/auth/*       │
                                    /sanctum/csrf-cookie │
 Browser ──────────────────────────►/admin   Inertia     │
                                                         │
             catalogue:import only ──────────────────────┼──► fakestoreapi.com
             (first start / manual re-sync)              │     └─ fallback: committed JSON snapshot
             currencies:sync-rates / "Sync live rates" ──┴──► api.frankfurter.dev (exchange rates)
```

Requests from the shop origin (`SANCTUM_STATEFUL_DOMAINS`, `localhost:5173`) get a cookie session
and CSRF protection; any other caller (curl, other origins) gets the same stateless JSON API, with
no cookies. `/admin` uses the same session (one host-only `localhost` cookie).

Inside the API:

```
Request ─► Controller (thin) ─► FormRequest (validation)
                 │
                 ▼
          CatalogueService ─► ProductRepository ─► EloquentProductRepository ─► Postgres
                 │                                     (returns Product DTOs)
                 ├─► EnquiryRepository (store enquiry: pending → sent | failed; admin list/re-send)
                 └─► Laravel Mail ─► EnquiryReceived Mailable ─► MAIL_MAILER driver
          ProductResource ◄─ (our own JSON shape; price converted by CurrencyConverter
                              into SelectedCurrency, set from ?currency= by ProductQueryRequest)

/api/v1/currencies ─► Api\CurrencyController ─► CurrencyService ─► CurrencyRepository ─► Postgres
/api/v1/auth/*     ─► Api\AuthController ─► App\Auth\AuthService (web guard, session)
/admin/*           ─► Admin controllers (thin) ─► CatalogueService / CurrencyService ─► Admin resources ─► Inertia pages

catalogue:import ─► CatalogueImporter ─► ProductSource
                                          ├─ FakeStoreProductSource (HTTP, timeout + retry)
                                          ├─ JsonFileProductSource  (snapshot, no network)
                                          └─ FallbackProductSource  (live, then snapshot)
                                          all via FakeStoreProductMapper → Product DTO

currencies:sync-rates ─► CurrencyService ─► RateSyncer ─► CurrencyRateSource ─► FrankfurterRateSource (HTTP)
(+ admin "Sync live rates")                        └─► CurrencyRepository (one transaction; nothing written on failure)
```

**What changes, and where it is swapped**

| Will change | Swap point |
|---|---|
| Product data source | `ProductSource` contract, chosen by `CATALOGUE_SOURCE` and bound in `App\Providers\CatalogueServiceProvider`. A new supplier, PIM or CSV is a new `ProductSource` implementation; only the importer uses it. |
| Product storage / read model | `ProductRepository` contract → `EloquentProductRepository`. Controllers and `CatalogueService` only see the contract and the `Product` DTO. |
| Exchange-rate source | `CurrencyRateSource` contract → `FrankfurterRateSource` (URL from `CURRENCY_RATES_URL`), bound in `CatalogueServiceProvider`. Another rate provider is a new implementation; only `RateSyncer` uses it. |
| Email provider | Laravel Mail drivers: SMTP → Mailpit locally, `MAIL_MAILER=ses` (or another driver) in production, no code change. Content lives in `App\Mail\EnquiryReceived`. |
| Look and feel | Only in `web/`: colours, fonts, spacing and radii are CSS variables in `web/src/styles/tokens.css`; components use `var(--…)` only. |
| API contract | `ProductResource` (and the other Resources). The client never sees the raw Fake Store payload or our table columns. |
| Database engine | Eloquent / Laravel DB layer, engine chosen by `DB_CONNECTION`; no engine-specific SQL outside migrations. |

**Rules** (enforced in CLAUDE.md and covered by tests):

- The client never calls Fake Store; all product data comes through `api/`.
  `web/src/api/client.ts` is the only module that knows the API base URL.
- API requests read Postgres only. Fake Store is reached only by `FakeStoreProductSource`, and only
  during `catalogue:import`, so the shop keeps working when Fake Store is down.
- Exchange rates are read from Postgres too. Frankfurter is reached only by `FrankfurterRateSource`,
  during `currencies:sync-rates` or the admin sync. Prices are converted only in `CurrencyConverter`.
- Controllers stay thin: validation in FormRequests, logic in `CatalogueService` (currency rules in
  `CurrencyService`).
- API errors are JSON with no stack traces (`APP_DEBUG=false`); `POST /enquiries` is rate-limited and
  has a honeypot field; user input is escaped in the email; CORS allows only the web origin (with
  credentials). Shop-origin POSTs need the CSRF token (otherwise 419).

## API

Base URL `http://localhost:8000/api/v1`. Every endpoint reads from Postgres.

| Method | Route | Returns |
|---|---|---|
| GET | `/products/featured` | `{ "data": Product[] }`: the 5 curated products (`FEATURED_PRODUCT_IDS`), in that order |
| GET | `/categories` | `{ "data": Category[] }` |
| GET | `/categories/{slug}/products` | `{ "data": { "category", "top_rated": Product[], "others": Product[] } }`: top 3 by rating, then the rest; unknown slug → 404 |
| GET | `/products?search=term` | `{ "data": Product[] }`: case-insensitive match on title, description and category; empty or > 100 chars → 422 |
| GET | `/products/{id}` | `{ "data": Product }`; unknown id → 404 |
| GET | `/currencies` | `{ "data": Currency[], "meta": { "base": "USD", "rates_updated_at": "…" \| null } }`: enabled currencies, default first |
| POST | `/enquiries` | Body `{ product_id, name, email, phone?, message?, website? }` (`website` is a honeypot) → 201 `{ "data": { "id", "status": "sent" } }`; email failure → 502 (enquiry kept as `failed`); 5 requests/min per IP → 429 |

Every product route (the first five rows except `/categories`) takes an optional `?currency=EUR`
(3 letters, any case): `price` is then converted on the server and `currency` names it. Absent → the
default currency (USD as seeded). Unknown, disabled or malformed → 422 on `currency`.

```jsonc
// Product
{ "id": 1, "title": "…", "description": "…", "price": 109.95, "currency": "USD", "image_url": "https://…",
  "category": { "slug": "mens-clothing", "name": "men's clothing" },
  "rating": { "rate": 3.9, "count": 120 } }
// Currency: rate = units per 1 USD
{ "code": "EUR", "name": "Euro", "symbol": "€", "rate": 0.91003, "is_default": false }
```

**Shop auth** (Sanctum SPA cookie auth; only works from the shop origin, which has a session):

| Method | Route | Returns |
|---|---|---|
| GET | `/sanctum/csrf-cookie` (outside `/api/v1`) | 204 + `XSRF-TOKEN` cookie |
| POST | `/auth/register` | Body `{ name, email, password, password_confirmation }` → 201 `{ "data": User }`, logged in; 3/min per IP |
| POST | `/auth/login` | Body `{ email, password, remember? }` → 200 `{ "data": User }`; 422 on bad credentials; 5/min per email+IP |
| POST | `/auth/logout` | 204; guest → 401 |
| GET | `/auth/me` | 200 `{ "data": User }`; guest → 401 |

`User` is `{ id, name, email }`. `POST /enquiries` from a signed-in customer stores their user id
from the session. Called without a shop `Origin` (curl), register/login return 403.

Errors: 404/429/502 → `{ "message": "…" }`; 422 → `{ "message": "…", "errors": { "field": ["…"] } }`;
401 → `{ "message": "Unauthenticated." }`; 419 = missing/stale CSRF token.
The full contract (shared by `api/` and `web/`, change both together) is in
[PLAN.md → API contract](PLAN.md#api-contract-shared-by-api-and-web--change-both-sides-together).

## Configuration

All settings are env vars, listed with local defaults in [`.env.example`](.env.example) and passed to
the containers by `docker-compose.yml`. The ones you are most likely to change:

| Variable | Default | What it does |
|---|---|---|
| `CATALOGUE_SOURCE` | `fakestore` | Import source: `fakestore` (live) or `file` (snapshot, no network) |
| `CATALOGUE_SOURCE_URL` | `https://fakestoreapi.com` | Fake Store base URL |
| `CATALOGUE_FALLBACK_TO_FILE` | `true` | If the live import fails, use the snapshot |
| `CATALOGUE_SNAPSHOT_PATH` | `database/data/fakestore-products.json` | Snapshot path (relative to `api/`) |
| `FEATURED_PRODUCT_IDS` | `12,11,3,18,5` | The 5 home-page products, as Fake Store ids, in display order |
| `CURRENCY_RATES_URL` | `https://api.frankfurter.dev/v2` | Frankfurter v2 base URL for live exchange rates (no API key) |
| `SALES_INBOX_ADDRESS` | `sales@example.com` | Where enquiries are emailed |
| `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT` | `smtp`, `mailpit`, `1025` | Mail driver; `ses` etc. in production |
| `MAIL_FROM_ADDRESS`, `MAIL_FROM_NAME` | `shop@example.com`, `Product Catalogue` | Sender |
| `CORS_ALLOWED_ORIGIN` | `http://localhost:5173` | Browser origin allowed to call the API |
| `DB_*` | `pgsql`, host `db`, db/user/password `catalogue` | Database connection (local-only credentials) |
| `APP_DEBUG` | `false` | Keep `false`: no stack traces in responses |
| `APP_KEY` | empty | Empty locally → generated once into `api/storage/app/.app-key` (gitignored); set a real one anywhere else |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:5173` | `host:port` values that get cookie/session auth on `/api`; everyone else is stateless |
| `SESSION_DRIVER`, `SESSION_LIFETIME` | `file`, `120` | Sessions for shop login and `/admin` (no DB table) |
| `SESSION_PATH`, `SESSION_DOMAIN`, `SESSION_SECURE_COOKIE` | `/`, empty, `false` | One host-only `localhost` cookie shared by `:5173` and `:8000`; `true` behind HTTPS |
| `ADMIN_ENABLED` | `true` (code default `false`) | Turns the `/admin` panel on |
| `ADMIN_NAME`, `ADMIN_EMAIL`, `ADMIN_PASSWORD` | `Admin`, `admin@example.com`, `local-admin-password` | First admin, created on start if missing (local-only defaults) |
| `SEED_DEMO_DATA` | `true` (code default `false`) | Seed the demo customer and `[demo]` enquiries on start ([Demo data](#demo-data)); never in production |
| `DEMO_CUSTOMER_EMAIL`, `DEMO_CUSTOMER_PASSWORD` | `customer@example.com`, `local-customer-password` | Demo shop customer created by `DemoSeeder` (local-only defaults) |
| `VITE_API_URL` | `http://localhost:8000/api/v1` | API base URL for the web client (read only by `client.ts`) |

Also in `.env.example`: `APP_NAME`, `APP_ENV`, `APP_URL`, `LOG_CHANNEL`, `CACHE_STORE`,
`QUEUE_CONNECTION`. Fixed tuning values (Fake Store timeout/retries, top-rated count,
enquiry rate limit) live in `api/config/catalogue.php`; the base currency (`USD`, not an env var)
and the rates call's timeout (10s) and retries (2) in `api/config/currency.php`. Full table: [TECH_STACK.md §8](TECH_STACK.md#8-configuration-env-vars-all-in-envexample).

## Project layout

```
product-catalogue/
├── api/                          Laravel 13: JSON API + /admin panel
│   ├── app/
│   │   ├── Auth/                 AuthService (shop register/login/logout)
│   │   ├── Catalogue/            Contracts (ProductSource, ProductRepository, EnquiryRepository,
│   │   │                         CurrencyRepository, CurrencyRateSource), Data (readonly DTOs), Sources,
│   │   │                         Repositories, Import, Exceptions, CatalogueService,
│   │   │                         Currency/ (CurrencyService, CurrencyConverter, SelectedCurrency,
│   │   │                         RateSyncer, FrankfurterRateSource)
│   │   ├── Console/Commands/     ImportCatalogue (catalogue:import), CreateAdmin (admin:create),
│   │   │                         SyncCurrencyRates (currencies:sync-rates)
│   │   ├── Http/                 Controllers/{Api,Admin} (thin), Requests, Resources (+ Admin/),
│   │   │                         Middleware (HandleInertiaRequests, AdminSecurityHeaders,
│   │   │                         RedirectAdminsFromLogin), Responses/AdminLogoutResponse
│   │   ├── Mail/                 EnquiryReceived
│   │   ├── Models/               Category, Product, Enquiry, User, Currency
│   │   └── Providers/            CatalogueServiceProvider (catalogue + currency bindings), FortifyServiceProvider
│   ├── config/                   catalogue.php, currency.php, admin.php, sanctum.php, cors.php, fortify.php, …
│   ├── database/
│   │   ├── data/fakestore-products.json   committed snapshot (fallback + test fixture)
│   │   ├── migrations/ factories/ seeders/
│   ├── resources/js/             admin front-end (Inertia + React + shadcn/ui),
│   │                             pages/{dashboard,enquiries,currencies,auth}
│   ├── routes/api.php            /api/v1 routes (catalogue, currencies, enquiries, auth/*)
│   ├── routes/web.php            /admin routes (only when ADMIN_ENABLED=true)
│   ├── tests/{Feature,Unit}/     Feature/Auth, Feature/Admin for the new parts
│   ├── docker/entrypoint.sh      composer install → APP_KEY → migrate → import --if-empty →
│   │                             sync-rates --if-never-updated → admin:create + admin build →
│   │                             DemoSeeder (SEED_DEMO_DATA) → php -S
│   └── Dockerfile                PHP 8.4 + Composer + Node 22 (admin build)
├── web/                          React + TypeScript (Vite)
│   ├── src/
│   │   ├── api/                  client.ts (only module that knows the API URL; CSRF + cookies), types.ts
│   │   ├── auth/                 AuthProvider, useAuth, safe ?next= redirects
│   │   ├── currency/             CurrencyProvider, useCurrency, saved choice (localStorage)
│   │   ├── components/           Layout, UserMenu, CurrencySelect, CategoryNav, SearchBar, ProductGrid,
│   │   │                         ProductTile, EnquiryForm, Footer, Status, icons/, …
│   │   ├── hooks/                useApi.ts (typed data fetching), useProductApi.ts (+ currency), useCategories.ts
│   │   ├── pages/                Home, Category, Search, Product (+ enquiry), Login, Register,
│   │   │                         NotFound, with tests
│   │   ├── styles/               tokens.css (theme), global.css
│   │   ├── lib/format.ts         price formatting (formatPrice(amount, currencyCode); never converts)
│   │   └── test/                 MSW server, handlers, fixtures, setup
│   └── Dockerfile
├── docker/postgres/init.sql      creates the catalogue_test database
├── docker-compose.yml            web, api, db, mailpit
├── scripts/setup.sh              one-command setup + login smoke checks (--check, --reset)
├── .env.example
├── .claude/skills/               Claude Code project skills
├── docs/screenshots/             README screenshots
├── docs/qa/ADMIN_QA.md           admin panel + login QA report (cases, bugs, fixes)
├── CLAUDE.md                     rules for AI agents
├── PLAN.md  ADMIN_PLAN.md  TECH_STACK.md  DECISIONS.md  AI_LOG.md
└── README.md
```

Web routes: `/` (home), `/categories/:slug`, `/search?q=…`, `/products/:id` (details + enquiry form),
`/login`, `/register`.

## Stack

| Layer | Choice | Why (short) |
|---|---|---|
| Client | React 19 + TypeScript (strict) + Vite, React Router, plain CSS variables | Quickest for a reviewer to run; TypeScript preferred by the brief; rebrand = edit tokens |
| API | Laravel 13 on PHP 8.4 | Service container, Mail drivers, HTTP client, FormRequests and API Resources each give one of the needed swap points out of the box |
| Auth | Laravel Sanctum (shop, SPA cookie auth), Fortify (admin login) | First-party; cookie sessions, no tokens in the browser |
| Admin UI | Laravel React starter kit: Inertia + React 19 + TypeScript + Tailwind 4 + shadcn/ui, Wayfinder | Ready-made, themeable admin shell inside the api app; no separate service |
| Database | PostgreSQL 16 | Free, solid, maps directly onto AWS RDS |
| Local email | Mailpit | Free, no account; shows the sales inbox in the browser |
| Tests | PHPUnit (api); Vitest + React Testing Library + MSW (web) | Real routing/validation/Postgres on the API side; UI tested like a user against the real client |
| Quality | Pint, ESLint (typescript-eslint), `tsc --noEmit` | Style, lint and types, all run in containers |
| Run | Docker Compose | One command; only Docker needed on the host |

Every tool and the reasoning behind it: [TECH_STACK.md](TECH_STACK.md).

## Decisions and cuts

Main assumptions (full list with reasons in [DECISIONS.md](DECISIONS.md)):

- **Home "top rated"** = 5 products curated in config (`FEATURED_PRODUCT_IDS`), not computed
  ("I have chosen" implies a manual pick).
- **Category "top rated"** = top 3 by rating in that category; the rest are "more products".
- **Search** = case-insensitive substring over title, description and category name, server side.
- **Enquiry** = name + email required, phone + message optional, product attached automatically.
  The enquiry is always stored; if email fails it is kept as `failed` and the UI shows a retry
  message instead of "Thank you".
- **Product data** lives in Postgres, imported from Fake Store on first start; a committed snapshot
  covers Fake Store being down. If every source fails, the api container exits with a clear error
  rather than serving an empty shop.
- **Prices** are stored in USD (Fake Store has no currency, its prices read as USD) and converted on
  the server into the shopper's chosen currency with stored rates. Live rates are synced on demand,
  never per request; enquiries keep the USD price.

**Cut from the PoC:** customer confirmation email, password reset and email verification (admin
passwords are reset from the CLI), product editing and an import trigger in the admin panel,
featured products managed in the database, cart, pagination of the shop, scheduled catalogue re-sync
and scheduled rate sync (both are manual or first-start only), removing products that vanish upstream,
queued mail, downloading images, prices in other currencies in the enquiry email.

## AI-assisted workflow

Built with Claude Code. The AI setup is part of the submission:

- **[CLAUDE.md](CLAUDE.md)**: non-negotiable architecture rules (client never calls Fake Store, API
  reads Postgres only, one `ProductSource`/Mail/`client.ts` boundary each), code conventions, error
  and security rules, and "don't mark work done until tests pass".
- **[AI_LOG.md](AI_LOG.md)**: raw log of key prompts, decisions and AI mistakes, written up as
  [AI_USAGE.md](AI_USAGE.md). Development problems and their fixes are logged in [ISSUES.md](ISSUES.md).
- **`.claude/skills/`**: project skills that turn the plan into repeatable steps:

| Skill | Purpose |
|---|---|
| `api-endpoint` | Add or change an `/api/v1` endpoint: route, thin controller, FormRequest, service method, Resource, feature test |
| `product-source` | Product sources (Fake Store, snapshot, fallback), mapper, importer, `catalogue:import`; adding a new source |
| `catalogue-data` | Run or force the import, refresh the snapshot, reset or inspect the database |
| `web-page` | Add or change a page/component in `web/`: routing, `client.ts`, tokens, loading/empty/error states, tests |
| `run-checks` | Run api tests, Pint, web tests, ESLint and `tsc` in containers; required before "done" |
| `pr-ready` | Final review: rule audit, secrets check, docs in sync, fresh-clone Docker run, submission checklist |
| `ai-log` | Record a key prompt, decision, AI mistake or phase time in AI_LOG.md |
| `laravel-backend` | House style for PHP in `api/`: layering, bindings, DTOs, config, errors, mail, rate limiting, CORS, Pint |
| `laravel-database` | Migrations, models, casts, factories, repository queries, upserts, transactions |
| `laravel-testing` | PHPUnit patterns: `RefreshDatabase`, factories, `Http::fake`, `Mail::fake`, throttle, JSON shape |
| `react-frontend` | House style for `web/`: structure, typed client and `useApi`, routing, forms, tokens, accessibility |
| `react-testing` | Vitest + RTL + user-event + MSW setup and patterns |
| `admin-page` | Add or change an `/admin` page or action: route, thin Admin controller, service, Admin resource, React page, `assertInertia` tests |
| `git-workflow` | Branch rules (`main` holds no code), safe staging, commit format, push and PR with `gh` |
| `dev-issues` | Log a development problem in ISSUES.md (symptom, cause, fix, prevention) and keep its summary table in sync |

- **Laravel Boost** (`laravel/boost`, dev dependency only): Laravel's first-party AI tooling, kept
  because it fits the AI-native brief and makes the agent more accurate on the installed Laravel
  APIs. It adds an MCP server (Laravel docs search, app info such as routes/schema/config, logs),
  registered in the root `.mcp.json` to run inside the api container
  (`docker compose exec -T api php artisan boost:mcp`; `api/.mcp.json` is Boost's own in-container
  variant), plus generated guidelines and skills in `api/CLAUDE.md`, `api/.claude/skills/`,
  `api/.ai/guidelines/` and `api/boost.json`. The root CLAUDE.md rules win over Boost's generic
  guidelines. It is never installed in production (`require-dev`), and its routes (e.g.
  `/_boost/browser-logs`) only register in a local environment.

## Production path

Not built in the PoC; the swap points above are what makes each step a config or binding change.

| Local | Production on AWS |
|---|---|
| `php -S` (built-in server) | php-fpm + nginx, or Laravel Octane, on ECS Fargate |
| Vite dev server | Static build of `web/` on S3 + CloudFront |
| Postgres container | Amazon RDS for PostgreSQL |
| Mailpit | Amazon SES (`MAIL_MAILER=ses`) |
| Synchronous mail | Queued mail (`ShouldQueue`) on SQS |
| Import at container start | Scheduled task (Laravel scheduler / EventBridge + ECS) |
| Rate sync at first start / by hand | `currencies:sync-rates` on a daily schedule (ECB rates change once per working day) |
| `.env` file | Secrets Manager / SSM Parameter Store |

## Troubleshooting

| Symptom | Cause / fix |
|---|---|
| `port is already allocated` / `address already in use` | Something on the host already uses 5173, 8000, 5432, 1025 or 8025 (often a local Postgres on 5432). Stop it, or change the left side of the `ports:` mapping in `docker-compose.yml`. If you move the API or web port, also update `VITE_API_URL` / `CORS_ALLOWED_ORIGIN` in `.env`. |
| Fake Store is down or there is no network on first start | Nothing to do: the import logs a warning and loads the snapshot (`docker compose logs api` shows `imported from snapshot (fakestore unavailable)`). Re-sync later with `docker compose exec api php artisan catalogue:import`. To skip the network entirely, set `CATALOGUE_SOURCE=file`. |
| `api` container keeps restarting | Every product source failed (Fake Store unreachable **and** snapshot missing/invalid, or `CATALOGUE_FALLBACK_TO_FILE=false`), or the database is unreachable. Check `docker compose logs api`; restore `api/database/data/fakestore-products.json` (`git checkout` it) or enable the fallback. The container exits on purpose rather than serve an empty shop. |
| `Live currency rates unavailable; using the stored rates` in the api log | Frankfurter couldn't be reached on first start. Not an error: the seeded rates are used. Sync later from `/admin/currencies` or with `docker compose exec api php artisan currencies:sync-rates`. A failed sync changes no rates. |
| No currency dropdown in the shop header | `GET /api/v1/currencies` failed (or returned no enabled currencies); prices show in the default currency. Check the API is up. |
| Products load but images are missing | Image URLs point to fakestoreapi.com, also in the snapshot, so they need the network. Downloading images during import is a listed next step. |
| Web app shows an error / CORS error in the console | Check the API is up (http://localhost:8000/api/v1/categories) and that `CORS_ALLOWED_ORIGIN` matches the origin the web app is served from. If you move the web port, also update `SANCTUM_STATEFUL_DOMAINS`. |
| `401` on `/api/v1/auth/me` in the browser console | Expected for guests: the shop asks "who am I?" on load and treats 401 as "not signed in". |
| `419` on a POST | Missing or stale CSRF token. The shop fetches `/sanctum/csrf-cookie` and retries once by itself; a repeated 419 usually means cookies are blocked or the web app isn't served from `localhost:5173`. Calls from curl (no shop `Origin`) don't need a token. |
| Logged out after restarting the stack | Only if `api/storage/app/.app-key` was deleted (a new key invalidates sessions). It's created once and kept. |
| `MissingAppKeyException` from `docker compose exec api php artisan …` | The entrypoint exports `APP_KEY` only to the server process; `exec` shells don't see it. Most commands (migrate, import, `admin:create`, tests) don't need it; for ones that encrypt/decrypt (e.g. tinker) run `docker compose exec api sh -c 'APP_KEY="$(cat storage/app/.app-key)" php artisan …'`, or set `APP_KEY` in `.env`. |
| Admin panel shows an old build / missing page | `docker compose exec api npm run build` (the entrypoint only rebuilds at start). |
| `/admin` is a 404 | `ADMIN_ENABLED` isn't `true`. Set it in `.env` and restart the api container. |
| Web code changes don't show up | The Vite dev server polls the bind-mounted `web/` every 300ms (`vite.config.ts`), because file events from a macOS host don't reach the container. Reload the page; if still stale, `docker compose restart web`. |
| No email in Mailpit | Check http://localhost:8025 and `SALES_INBOX_ADDRESS`; a send failure returns 502 and the enquiry is stored as `failed`. |
| Want a clean slate | `./scripts/setup.sh --reset` (asks first), or `docker compose down -v && docker compose up --build`. Both delete enquiries too; the demo data is seeded again. |
| `scripts/setup.sh` reports a ✘ | Each line names the failing check. "timed out" on the API usually means it is still building the admin panel on a slow first start (raise `SETUP_TIMEOUT`, seconds, default 600) or is restart-looping (`docker compose logs api`). A failing demo-customer login after you changed its password is expected: the seeder never resets an existing account. |
| Admin has no enquiries | `SEED_DEMO_DATA` is `false`, or there were no products when it ran. Run `docker compose exec api php artisan db:seed --class=DemoSeeder`. |

## Screenshots

Taken with Playwright from a real `docker compose up` run (live Fake Store data).

| Home (featured) | Category (top rated + more) |
|---|---|
| ![Home](docs/screenshots/01-home.png) | ![Category](docs/screenshots/02-category.png) |
| **Search** | **Product + enquiry form** |
| ![Search](docs/screenshots/03-search.png) | ![Product](docs/screenshots/04-product-enquiry.png) |
| **Client-side validation** | **Thank you (email delivered to Mailpit)** |
| ![Validation](docs/screenshots/05-enquiry-validation.png) | ![Thank you](docs/screenshots/06-thank-you.png) |
| **Log in** | **Signed-in header** |
| ![Log in](docs/screenshots/07-login.png) | ![Signed-in header](docs/screenshots/08-signed-in-header.png) |
| **Prices in INR (currency dropdown)** | |
| ![Product in INR](docs/screenshots/11-currency-inr.png) | |

**Small screens**

| Home (mobile) | Product (mobile) | Currency dropdown in the header (mobile, INR) |
|---|---|---|
| <img src="docs/screenshots/09-home-mobile.png" alt="Home on a phone" width="280"> | <img src="docs/screenshots/10-product-mobile.png" alt="Product on a phone" width="280"> | <img src="docs/screenshots/12-currency-mobile.png" alt="Currency dropdown set to INR on a phone" width="280"> |

**Admin**

| Login | Dashboard |
|---|---|
| ![Admin login](docs/screenshots/admin-01-login.png) | ![Admin dashboard](docs/screenshots/admin-02-dashboard.png) |
| **Enquiries** | **Enquiry detail** |
| ![Enquiries](docs/screenshots/admin-03-enquiries.png) | ![Enquiry detail](docs/screenshots/admin-04-enquiry-detail.png) |
| **Re-send toast** | **Login error** |
| ![Re-send toast](docs/screenshots/admin-05-resend-toast.png) | ![Login error](docs/screenshots/admin-06-login-error.png) |
| **Currencies** | **Currencies after "Sync live rates"** |
| ![Currencies](docs/screenshots/admin-08-currencies.png) | ![Currency sync](docs/screenshots/admin-09-currency-sync.png) |
| **Mobile (375px)** | |
| <img src="docs/screenshots/admin-07-mobile.png" alt="Admin on a phone" width="280"> | |

The admin screenshots were taken during QA, before the starter kit's Laravel logo was replaced with the shop's.

