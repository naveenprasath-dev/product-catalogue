# Plan — Product Catalogue PoC (React + Laravel)

Source brief: "Code Challenge: AI-Native Engineer". Time-box: 2–3 hours.
Goal: a small, well-reasoned PoC that is a **foundation, not a throwaway**. The data
source, look and feel, and email provider are all expected to change.

Phase 2 (shop customer login + the `/admin` panel) is planned in detail in [ADMIN_PLAN.md](ADMIN_PLAN.md);
this file is updated to match what was built.

## 1. Stack

| Layer | Choice | Why |
|---|---|---|
| Client | React + TypeScript (Vite) | Quickest for a reviewer to run (no simulator needed); TS is preferred in the brief |
| Backend | Laravel 13, PHP 8.4, a JSON API (+ the `/admin` panel, phase 2) |
 Its service container, Mail drivers, Http client, FormRequests and API Resources each provide a swap point for one of the things expected to change |
| Database | PostgreSQL 16, runs in Docker | Holds the catalogue (imported from Fake Store on first launch, with a committed JSON snapshot as fallback) and every enquiry. Laravel's DB layer keeps the engine swappable (`DB_CONNECTION`); maps directly onto AWS RDS later |
| Local email | Mailpit (SMTP), runs in Docker | Free, no account needed; the reviewer sees the "sales inbox" at http://localhost:8025 |
| Tests | PHPUnit (api), Vitest + React Testing Library (web) | Covers the critical path; both run inside their containers |
| Run | `docker compose up --build` — **everything is containerised** (web, api, db, mailpit) | Brief asks for 1–2 commands, < 10 minutes; the reviewer needs only Docker, no local PHP/Node/Postgres |

## 2. Repo layout

```
product-catalogue/
├── api/                 Laravel: JSON API + /admin panel (+ Dockerfile, entrypoint: migrate → import → admin:create/build → serve)
├── web/                 React + TS (Vite) (+ Dockerfile)
├── docker/postgres/     init.sql (creates the test database)
├── docker-compose.yml   web + api + db (postgres) + mailpit
├── .env.example
├── .claude/skills/      Claude Code project skills for the dev workflow (see §11)
├── CLAUDE.md            AI agent rules
├── PLAN.md              this file
├── TECH_STACK.md        every language, framework, service and tool, and why
├── README.md
├── DECISIONS.md
├── ADMIN_PLAN.md        phase 2 plan: admin panel + shop login
└── AI_USAGE.md          (built from AI_LOG.md)
```

## 3. Boundaries (what changes, and where it is swapped)

Products have two sides: **where they come from** (the upstream source, used only by the import)
and **where the API reads them** (our database). Each side has its own contract.

| Will change | Mechanism |
|---|---|
| Product data source | `ProductSource` contract, chosen by `CATALOGUE_SOURCE`: `FakeStoreProductSource` (Http with timeout + retry, default) or `JsonFileProductSource` (committed snapshot). `FallbackProductSource` wraps them so a Fake Store failure falls back to the file. Only the importer uses a source. A new supplier/PIM/CSV = a new `ProductSource` binding; nothing else changes. |
| Product storage / read model | `ProductRepository` contract → `EloquentProductRepository`. Controllers and `CatalogueService` only see this contract and the `Product` DTO. |
| Email provider | Laravel Mail drivers: SMTP → Mailpit locally; `MAIL_MAILER=ses` for AWS, with no code change. Content lives in the `EnquiryReceived` Mailable. |
| Look and feel | Only in the React client, styled via theme tokens (CSS variables). |
| API contract | `Product` DTO + `ProductResource`. The client never sees the raw Fake Store shape or our table columns. |
| Database engine | Eloquent / Laravel DB layer; engine chosen by `DB_CONNECTION` (pgsql locally, RDS in production). |

Rules: the client calls only our API, never Fake Store. API requests never call Fake Store either;
they read Postgres only, so the catalogue keeps working when Fake Store is down.

## 4. Backend (`api/`)

```
app/
├── Catalogue/
│   ├── Contracts/
│   │   ├── ProductSource.php                fetchProducts(): iterable<Product>   (upstream, import only)
│   │   └── ProductRepository.php            find(id), featured(ids), byCategory(slug), categories(), search(term)
│   ├── Data/Product.php                     readonly DTO
│   ├── Sources/
│   │   ├── FakeStoreProductSource.php       the ONLY class that calls the Fake Store URL
│   │   ├── JsonFileProductSource.php        reads the committed snapshot file, no network
│   │   ├── FallbackProductSource.php        tries primary, on CatalogueUnavailable uses fallback (logs a warning)
│   │   └── FakeStoreProductMapper.php       Fake Store item → Product DTO, shared by both sources
│   ├── Repositories/EloquentProductRepository.php
│   ├── Import/CatalogueImporter.php         source → upsert categories + products (one transaction)
│   ├── Exceptions/CatalogueUnavailable.php  source unreachable/invalid during import
│   └── CatalogueService.php                 featured(), categoryListing(), search(), submitEnquiry()
│   (phase 2 additions: Contracts/EnquiryRepository, Data/{StoredEnquiry,DashboardStats,EnquiryData,…},
│    Exceptions/{EnquiryNotFound,EnquiryNotResendable,EnquiryNotSent}; CatalogueService also has
│    dashboardStats(), enquiries(), enquiry(), resendEnquiry() for the admin panel)
├── Auth/AuthService.php                     shop register/login/logout on the web guard
├── Console/Commands/ImportCatalogue.php     php artisan catalogue:import
├── Console/Commands/CreateAdmin.php         php artisan admin:create [--from-env | --email=x [--reset]]
├── Http/
│   ├── Controllers/Api/{Product,Category,Enquiry,Auth}Controller.php   (thin)
│   ├── Controllers/Admin/{Dashboard,Enquiry}Controller.php            (thin, Inertia)
│   ├── Middleware/HandleInertiaRequests.php
│   ├── Requests/{StoreEnquiry,SearchProducts}Request.php, Auth/{Login,Register}Request.php,
│   │   Admin/ListEnquiriesRequest.php
│   └── Resources/{Product,Category,CategoryListing,Enquiry,User}Resource.php,
│       Admin/{Enquiry,DashboardStats}Resource.php
├── Mail/EnquiryReceived.php
├── Models/{Category,Product,Enquiry,User}.php
└── Providers/CatalogueServiceProvider.php   binds ProductSource (per CATALOGUE_SOURCE / fallback flag) + ProductRepository
    Providers/FortifyServiceProvider.php     admin-only login (is_admin), no Fortify routes
config/catalogue.php                         source, source_url, fallback_to_file, snapshot_path, featured_ids,
                                             top_rated_per_category, sales_inbox
config/{admin,sanctum,cors,fortify,session}.php   admin toggle + bootstrap, stateful shop origin, CORS with credentials
routes/api.php, routes/web.php               /api/v1 (+ /auth/*); /admin (only when ADMIN_ENABLED=true)
resources/js/                                admin front-end (Inertia + React, pages dashboard / enquiries / auth/login)
database/
├── data/fakestore-products.json             committed snapshot of GET /products (offline/fallback source)
├── migrations/                              categories, products, enquiries, users, enquiries.user_id
├── factories/{Category,Product,Enquiry,User}Factory.php  for tests (tests never use the real import)
└── seeders/DatabaseSeeder.php               calls catalogue:import, so `migrate --seed` also works
```

### Seeding the catalogue from Fake Store (with JSON fallback)

Command: `php artisan catalogue:import [--if-empty] [--source=fakestore|file]`

1. The source is picked by `CATALOGUE_SOURCE` (default `fakestore`); `--source` overrides it for one run.
   - `fakestore`: `FakeStoreProductSource` calls `GET {CATALOGUE_SOURCE_URL}/products` (default
     `https://fakestoreapi.com`) with timeout and retry. Bad payload or network failure → `CatalogueUnavailable`.
     If `CATALOGUE_FALLBACK_TO_FILE=true` (default), `FallbackProductSource` catches that, logs a
     warning and reads the snapshot instead.
   - `file`: `JsonFileProductSource` reads `CATALOGUE_SNAPSHOT_PATH` (default
     `database/data/fakestore-products.json`). No network at all — useful offline and in CI.
   - Both sources use `FakeStoreProductMapper`, so every item becomes the same `Product` DTO with
     `source = 'fakestore'` and the same `external_id`. A later live re-sync therefore updates the
     snapshot rows instead of duplicating them.
2. `CatalogueImporter` runs in one DB transaction:
   - upsert `categories` by `slug` (slug made from the Fake Store category name);
   - upsert `products` by `(source, external_id)`, so running it again updates prices, ratings and
     images instead of creating duplicates.
3. The command prints a summary including where the data came from
   (`20 products, 4 categories imported from fakestore` / `... from snapshot (Fake Store unavailable)`)
   and exits 0. If every source fails (Fake Store down **and** snapshot missing/invalid, or fallback disabled) it
   prints a clear message and exits 1; the transaction means nothing half-written is left behind.

`--if-empty` skips the import when `products` already has rows. The api container entrypoint uses it,
so the **first** `docker compose up` seeds the database and later starts are instant and don't depend
on Fake Store. To refresh the data later, run `docker compose exec api php artisan catalogue:import`.

**The snapshot file** is the raw `GET /products` response, saved once at scaffold time
(`curl -s https://fakestoreapi.com/products > api/database/data/fakestore-products.json`) and committed.
Fake Store is open source (MIT), so committing a copy of its sample data is fine. It doubles as the
test fixture for `Http::fake()`. Limits: it can go stale (re-run the curl line to refresh it), and image
URLs in it still point to fakestoreapi.com, so offline the data loads but images don't
(downloading images is a next step).

Products that disappear upstream are not deleted in the PoC (enquiries reference them). Marking them
inactive is listed as a next step in DECISIONS.md.

### Database (PostgreSQL)

```
categories
  id             bigserial PK
  slug           varchar(100)   unique (used in /categories/{slug}/products)
  name           varchar(100)
  created_at / updated_at

products
  id             bigserial PK   our id, used in our URLs
  source         varchar(30)    'fakestore' (live or snapshot; allows a different source later)
  external_id    varchar(64)    the id in the source system
  category_id    bigint FK → categories.id
  title          varchar(255)
  description    text
  price          numeric(10,2)
  image_url      varchar(2048)
  rating_rate    numeric(3,2)
  rating_count   integer
  imported_at    timestamptz
  created_at / updated_at
  unique (source, external_id)
  index  (category_id, rating_rate)

enquiries
  id             bigserial PK
  product_id     bigint FK → products.id
  product_title  varchar(255)   copy of the title at the time of the enquiry
  name           varchar(100)
  email          varchar(255)
  phone          varchar(30)    nullable
  message        text           nullable (max 2000 enforced in StoreEnquiryRequest)
  status         varchar(20)    pending | sent | failed (default pending)
  mailed_at      timestamptz    nullable
  user_id        bigint FK → users.id   nullable, nullOnDelete, indexed (set from the session, never the body)
  created_at / updated_at
  index (status, created_at)

users                            shop customers and admins in one table
  id             bigserial PK
  name, email (unique), password (hashed), remember_token
  is_admin       boolean        default false, not mass-assignable
  created_at / updated_at
  (no password_reset_tokens / sessions tables: reset is CLI-only, sessions are files)
```

Enquiry flow in `CatalogueService`: save as `pending` → send `EnquiryReceived` → mark `sent`
(201), or mark `failed` and return 502 so the UI shows retry. The record is kept either way.

### Endpoints (`/api/v1`) — all read from Postgres

| Method | Route | Purpose |
|---|---|---|
| GET | `/products/featured` | The 5 curated "top rated" products, in configured order |
| GET | `/categories` | Category list for links |
| GET | `/categories/{slug}/products` | `{ top_rated: [...], others: [...] }` (404 for unknown slug) |
| GET | `/products?search=term` | Server-side text search |
| GET | `/products/{id}` | Product details (404 if missing) |
| POST | `/enquiries` | Validate, store in Postgres, email sales inbox, return 201 |

### API contract (shared by `api/` and `web/` — change both sides together)

```jsonc
// Product (ProductResource)
{ "id": 1, "title": "…", "description": "…", "price": 109.95,
  "image_url": "https://…", "category": { "slug": "mens-clothing", "name": "men's clothing" },
  "rating": { "rate": 3.9, "count": 120 } }
// Category
{ "slug": "mens-clothing", "name": "men's clothing" }
```

| Route | 2xx body |
|---|---|
| `GET /products/featured` | `{ "data": Product[] }` |
| `GET /categories` | `{ "data": Category[] }` |
| `GET /categories/{slug}/products` | `{ "data": { "category": Category, "top_rated": Product[], "others": Product[] } }` |
| `GET /products?search=term` | `{ "data": Product[] }` |
| `GET /products/{id}` | `{ "data": Product }` |
| `POST /enquiries` | request `{ product_id, name, email, phone?, message?, website? }` (`website` = honeypot, must be empty) → 201 `{ "data": { "id": 12, "status": "sent" } }` |

Errors are always JSON: 404/429/502 → `{ "message": "…" }`; 422 → Laravel's
`{ "message": "…", "errors": { "field": ["…"] } }`. `price` and `rating.rate` are JSON numbers, not strings.

#### Shop auth (Sanctum SPA cookie auth, see ADMIN_PLAN.md §19)

```jsonc
// User (UserResource): is_admin, password and tokens are never exposed
{ "id": 7, "name": "Ada Lovelace", "email": "ada@example.com" }
```

| Method | Route | Guard / limit | Request | 2xx | Errors |
|---|---|---|---|---|---|
| GET | `/sanctum/csrf-cookie` (outside `/api/v1`) | – | – | 204 + `XSRF-TOKEN` cookie | – |
| POST | `/auth/register` | 3/min per IP | `{ name, email, password, password_confirmation }` | 201 `{ "data": User }`, logged in | 422 (email taken, password < 8 chars or unconfirmed), 429 |
| POST | `/auth/login` | 5/min per email+IP | `{ email, password, remember? }` | 200 `{ "data": User }` | 422 `errors.email: ["These credentials do not match our records."]` (same for unknown email), 429 |
| POST | `/auth/logout` | `auth:sanctum` | – | 204 | 401 |
| GET | `/auth/me` | `auth:sanctum` | – | 200 `{ "data": User }` | 401 `{ "message": "Unauthenticated." }` (a guest, not an error) |

- Requests from the shop origin (`SANCTUM_STATEFUL_DOMAINS`, `localhost:5173`) are stateful: the
  client sends `credentials: 'include'`, and every POST (including `/enquiries`) needs the
  `X-XSRF-TOKEN` header (copied from the `XSRF-TOKEN` cookie); a missing or stale token is **419**.
- Requests without a shop `Origin`/`Referer` (curl, other origins) stay stateless: no cookies, no CSRF.
  `register`/`login` need the session, so they return **403** from such callers.
- Bearer tokens are ignored (cookie auth only).
- Emails are trimmed and lower-cased. `is_admin` in a register body is ignored.
- `POST /enquiries` from a logged-in customer stores `user_id` from the session (never from the body).
  Guests enquire exactly as before.

### Security / quality basics
- FormRequest validation: name required (max 100), email required (`email:rfc`), phone optional,
  message optional (max 2000), `product_id` required and `exists:products,id`.
- `throttle` on enquiries, plus a honeypot field.
- Escaped mail template (no HTML injection).
- CORS restricted to the web origin.
- Consistent JSON errors, no stack traces; `APP_DEBUG=false` in `.env.example`.
- Search uses Laravel's `whereLike` (case-insensitive, parameter-bound) — no raw SQL, no
  engine-specific `ILIKE` in app code.
- `CACHE_STORE=file`, `SESSION_DRIVER=file` (no DB-backed cache/sessions). Stateless for non-shop
  callers; the shop origin (`SANCTUM_STATEFUL_DOMAINS`) and `/admin` get a cookie session + CSRF.
- DB credentials only via env vars (`DB_*` in `.env.example` with local-only defaults).
- Mail sent synchronously for the PoC (`ShouldQueue` + the `database` queue driver, then SQS, is the next step).

## 5. Frontend (`web/`)
- Pages: Home (featured), Category (top rated + others), Search results, Product detail with the
  enquiry form on the same page (no separate enquiry route; "Thank you" replaces the form),
  Login (`/login`), Register (`/register`), NotFound.
- `src/api/client.ts` is the only module that knows the API URL (`VITE_API_URL`); it sends
  `credentials: 'include'` and the Sanctum CSRF header.
- Auth: `src/auth/` (`AuthProvider` + `useAuth()`, safe `?next=` redirects). The header's account
  menu (`UserMenu`) shows Log in / Register for guests, the name + Log out when signed in. The enquiry
  form is pre-filled for signed-in users; guests enquire as before.
- Components: `ProductTile`, `ProductGrid`, `SearchBar`, `EnquiryForm`, `CategoryNav`, `UserMenu`, `Footer`.
- Loading, empty and error states on every page.

## 6. Assumptions (expanded in DECISIONS.md)

| Gap | Assumption |
|---|---|
| "I have chosen 5 top rated" | Curated by the owner in config (`FEATURED_PRODUCT_IDS`, Fake Store ids = `external_id`), not computed |
| Category "top rated" | Top 3 by `rating_rate` in that category; the rest are "additional" |
| Search | Case-insensitive substring match over title, description and category name, done server side; empty term → 422 |
| Enquiry fields | Name + email required; phone + message optional; product attached automatically |
| Sales inbox | `SALES_INBOX_ADDRESS` env var; Mailpit locally |
| Email failure | Enquiry kept with `status=failed`; 502 → UI shows a retry message, not "Thank you" |
| Product data | Imported into Postgres from Fake Store on first launch; the API never calls Fake Store at request time |
| Fake Store down on first launch | Import falls back to the committed JSON snapshot and says so in the log, so `docker compose up` still gives a working shop |
| Every source fails | api container exits with a clear error and compose restarts it (`restart: on-failure`) — better than serving an empty shop silently |
| Cuts | Customer confirmation email, password reset UI and email verification (admin reset is CLI-only), product editing in admin, an import trigger in admin, featured products managed in the DB (still `FEATURED_PRODUCT_IDS`), cart, shop pagination, scheduled re-sync, removing products that vanish upstream |

## 7. Tests

Every endpoint and the import command get **feature tests** (full HTTP request / artisan call →
real routing, validation, service, Postgres). Work isn't done until they pass.

Shared setup: `RefreshDatabase` on the `catalogue_test` Postgres database (created by
`docker/postgres/init.sql`, so tests use the same engine as the app and never touch dev data);
data from `Category`/`Product` factories; `Mail::fake()` for mail; `Http::fake()` +
`Http::preventStrayRequests()` so no test ever hits the real network.

### api feature tests (`api/tests/Feature/`)

| File | Cases |
|---|---|
| `FeaturedProductsTest` | returns exactly the 5 configured products in configured order; response matches `ProductResource` shape (no raw columns like `external_id`); configured id missing from DB is skipped, not a 500 |
| `CategoriesTest` | lists all categories with slug + name, sorted by name; empty DB → `[]` |
| `CategoryProductsTest` | `top_rated` = top 3 by `rating_rate` desc, `others` = the rest; category with ≤ 3 products → `others` empty; products from other categories never appear; unknown slug → 404 JSON |
| `ProductSearchTest` | matches title, description and category name; case-insensitive; non-matching products excluded; no matches → `[]`; empty/missing `search` → 422; very long term (> 100 chars) → 422 |
| `ProductShowTest` | existing id → product details; unknown id → 404 JSON; non-numeric id → 404, no stack trace |
| `StoreEnquiryTest` | valid → 201, row stored as `sent` with `mailed_at`, `EnquiryReceived` sent to `SALES_INBOX_ADDRESS` with product title; mail transport throws → row `failed`, 502 JSON; missing name/email, bad email, message > 2000 → 422 with field errors, no row, no mail; unknown `product_id` → 422; honeypot filled → silently accepted, no row, no mail; throttle → 429 after the limit; HTML in name/message is escaped in the rendered mail |
| `ImportCatalogueCommandTest` | imports products + categories from faked Fake Store JSON (the snapshot file is the fixture); second run → no duplicates and changed price/rating updated; `--if-empty` skips when products exist; upstream 500 / timeout / invalid JSON with fallback on → imports from snapshot, output says so; `CATALOGUE_SOURCE=file` / `--source=file` → imports with no HTTP request made; snapshot import then live import → same rows updated, no duplicates; fallback off + upstream down → exit 1, DB unchanged; upstream down + snapshot missing/invalid → exit 1, DB unchanged (transaction rolled back) |
| `ErrorResponsesTest` | unknown route → JSON 404; `APP_DEBUG=false` → no trace in error bodies; CORS header only for the configured web origin |
| `StatelessApiTest` | no shop `Origin` → no cookies set; another origin stays stateless; the shop origin gets a session cookie |
| `Auth/RegisterTest` | creates a customer and logs in; `is_admin` in the body ignored; duplicate email / weak or unconfirmed password → 422; throttled per IP; no shop session → 403 |
| `Auth/LoginTest` | valid login returns the user without secrets; wrong password and unknown email → the same 422; 6th attempt/min → 429; admins can log in to the shop too; no shop session → 403 |
| `Auth/SessionTest` | `me` returns the user; guest `me` → 401 JSON; bearer tokens not accepted; logout ends the session; guest logout → 401 |
| `Auth/StatefulCsrfTest` | `/sanctum/csrf-cookie` sets `XSRF-TOKEN` with credentialed CORS; shop-origin POST without a token → 419; with the token → accepted; no shop origin → no token needed |
| `Admin/AdminAccessTest` | guests → login redirect; customers → 403 everywhere; admins open every page; every admin route except login/logout needs an admin; no register/reset/verify/2FA/passkey routes; panel is a 404 when disabled; API responses still set no cookies |
| `Admin/LoginTest` | login page renders; admin logs in → dashboard; wrong password and customer credentials → the same error; throttled; logout |
| `Admin/DashboardTest` | catalogue and enquiry counts; empty catalogue → zeros |
| `Admin/EnquiriesTest` | newest first, 20 per page; filter by status; invalid filter → validation error; detail shows only the listed fields; unknown/malformed id → 404 |
| `Admin/ResendEnquiryTest` | failed enquiry re-sent; mail failure keeps it failed with an error toast (not a 502); sent enquiry not re-sent; unknown id → 404; throttled |
| `Admin/CreateAdminCommandTest` | creates an admin from env; second run changes nothing; short password / missing email refused; interactive create and `--reset` prompt for a hidden password |
| `AdminCatalogueServiceTest` | dashboard stats; enquiry paging + status filter; detail mapping; re-send rules (failed, stuck pending > 5 min, not sent/recent pending); unknown id → not found |

`StoreEnquiryTest` also covers: a guest enquiry has no account, and `user_id` in the body is ignored.

### api unit tests (`api/tests/Unit/`)
- `FakeStoreProductMapperTest`: Fake Store JSON → `Product` DTO mapping (price, rating, category slug);
  malformed item → `CatalogueUnavailable`.
- `JsonFileProductSourceTest`: reads the snapshot; missing file / invalid JSON → `CatalogueUnavailable`.
- `FallbackProductSourceTest`: primary OK → fallback never called; primary throws → fallback used and a warning logged; both throw → `CatalogueUnavailable`.
- `CatalogueServiceTest`: top-rated split logic in isolation.

### web tests (Vitest + React Testing Library, API mocked with MSW at the `client.ts` boundary)
- Home: renders featured products; loading, then error state when the API fails.
- Category page: shows "Top rated" and "More products" sections; unknown category → not-found message.
- Search: submitting shows results; empty term blocked client-side; no results → empty state.
- Product detail → enquiry form: required-field errors shown; successful submit → "Thank you";
  502 → retry message, form values kept; 429 → wait message; empty for a guest; pre-filled for a
  signed-in user and sent with CSRF but no user id.
- `LoginPage.test.tsx`: logs in, updates the header and returns to `?next=`; server 422 under the
  email field; 429 → wait; required fields; off-site `?next=` ignored.
- `RegisterPage.test.tsx`: creates the account and signs in; server 422 under fields; mismatched
  passwords caught before sending.
- `UserMenu.test.tsx`: guest links carry the current page; logout returns to the guest header;
  failed logout keeps the user signed in with a message.
- `api/client.test.ts`: envelope unwrapping, 422/network/non-JSON errors; CSRF cookie fetched before
  the first POST and `X-XSRF-TOKEN` + credentials sent; no CSRF on GET; 419 → refresh and retry once
  (then give up); `me()` → null for a guest; login/logout.

### Commands
```bash
docker compose exec api php artisan test
docker compose exec web npm test
```

## 8. Run
```bash
cp .env.example .env
docker compose up --build
# first start: migrates and imports the catalogue from fakestoreapi.com
# (falls back to the committed snapshot if Fake Store is unreachable)
# web http://localhost:5173 · api http://localhost:8000 · inbox http://localhost:8025
# admin http://localhost:8000/admin (local login from ADMIN_EMAIL / ADMIN_PASSWORD in .env.example)

# re-sync products from Fake Store at any time
docker compose exec api php artisan catalogue:import
# or force the offline snapshot
docker compose exec api php artisan catalogue:import --source=file

# tests (inside the containers)
docker compose exec api php artisan test
docker compose exec web npm test
```
Everything runs in containers; the only host requirement is Docker.

| Service | Image | Notes |
|---|---|---|
| `db` | `postgres:16.16-alpine` | Named volume
 `pgdata` (catalogue survives restarts); `pg_isready` healthcheck; `init.sql` creates `catalogue_test` |
| `api` | `php:8.4.28-cli-bookworm` + `pdo_pgsql` + `composer:2.11.2` + Node 22 | `depends_on: db (service_healthy)`; `restart: on-failure`; entrypoint: stable `APP_KEY` → `migrate --force` → `catalogue:import --if-empty` → (admin on) `admin:create --from-env` + admin build → `php -S 0.0.0.0:8000` with Laravel's router script |
| `web` | `node:22-alpine` | Vite dev server on `0.0.0.0:5173`; `VITE_API_URL=http://localhost:8000/api/v1` |
| `mailpit` | `axllent/mailpit:v1.34.0` | SMTP 1025, UI 8025 |

To start again with an empty database: `docker compose down -v && docker compose up --build`.

PHP's built-in server (`php -S`) / Vite dev server are fine for a PoC; production would be php-fpm/nginx or Octane
on ECS, a static build of `web/` on S3 + CloudFront, RDS Postgres, and the import run as a scheduled
task instead of at container start.

## 9. Schedule

| Step | Time | Output |
|---|---|---|
| 1. Plan | 20m | CLAUDE.md, PLAN.md, DECISIONS.md, AI_LOG.md |
| 2. Scaffold | 30m | Laravel api, Vite web, Dockerfiles, docker-compose (web, api, db, mailpit), committed on `feature/catalogue-poc` (`main` stays an empty initial commit) |
| 3. Backend | 80m | Branch `feature/catalogue-poc`: migrations, models, sources (Fake Store, JSON file, fallback) + importer + command, repository, service, controllers, mail, feature tests written alongside each endpoint |
| 4. Frontend | 40m | Pages, components, API client, tests |
| 5. Review | 10m | Read every line; fresh-clone run via Docker (`down -v` first, so the import is tested; once more with the network off to check the fallback) |
| 6. Docs + PR | 10m | README, DECISIONS, AI_USAGE, time spent; open PR into `main` (leave it open) |

## 10. Submission checklist
- [ ] GitHub repo; `main` = empty initial commit only (no code); all work on `feature/catalogue-poc`; PR into `main` open
- [ ] PR description: summary, architecture, run/test steps, decisions, cuts, screenshots
- [ ] README (stack + why, setup, Mailpit URL, `catalogue:import`), `.env.example`, no secrets
- [ ] DECISIONS.md: assumptions, cuts, how it grows, next day
- [ ] AI_USAGE.md + CLAUDE.md committed
- [ ] All api feature/unit tests and web tests pass in containers
- [ ] Time spent, split into planning, building, testing, reviewing

## 11. Claude Code skills (`.claude/skills/`)

Project skills that turn this plan into repeatable steps for the AI agent. CLAUDE.md holds the
rules; these skills hold the how-to for each recurring task. They are committed, so the AI workflow
is part of the submission too.

| Skill | Use it for | Plan steps |
|---|---|---|
| `api-endpoint` | Add/change an `/api/v1` endpoint: test first → route → FormRequest → service → thin controller → Resource | 3 |
| `product-source` | Fake Store / JSON snapshot / fallback sources, mapper, importer, `catalogue:import`; adding a new source | 3 |
| `web-page` | React pages/components via `client.ts`, theme tokens, loading/empty/error states, RTL + MSW tests | 4 |
| `catalogue-data` | Run/force the import, refresh the snapshot, reset or inspect the DB (asks before destructive commands) | 2–5 |
| `run-checks` | api tests + Pint, web tests + ESLint + `tsc`, admin `types:check` + `check` + `build`, all in containers; required before "done" | 3–6 |

| `pr-ready` | Rule audit, secrets check, docs in sync, fresh-clone + offline run, PR checklist | 5–6 |
| `ai-log` | Record key prompts, AI mistakes and phase times in AI_LOG.md → AI_USAGE.md | all |
| `laravel-backend` | House style for any PHP in `api/`: layering, container bindings, DTOs, config, errors, mail, rate limit, CORS | 3 |
| `laravel-database` | Migrations, models, casts, factories, repository queries, upserts, transactions | 3 |
| `laravel-testing` | PHPUnit feature/unit test patterns: `Http::fake`, `Mail::fake`, artisan, JSON shape, throttle | 3 |
| `react-frontend` | House style for `web/`: structure, typed client + `useApi`, routing, forms, tokens, a11y | 4 |
| `react-testing` | Vitest + RTL + MSW setup and patterns for loading/empty/error/form cases | 4 |
| `admin-page` | Add/change an `/admin` page or action: route in the protected group, thin controller, service, Admin resource, React page with token classes, `assertInertia` tests | admin |
| `git-workflow` | Branch rules (`main` holds no code), safe staging, commit grouping and message format, push + PR with `gh` | 2–6 |

Convention skills (house style, added while building): `laravel-backend`, `laravel-database`,
`laravel-testing`, `react-frontend`, `react-testing`.

**Laravel Boost** (`laravel/boost`, dev only) adds, for `api/`:
- version-aware Laravel guidelines in `api/CLAUDE.md` (generated; project overrides in `api/.ai/guidelines/`);
- skills in `api/.claude/skills/`: `laravel-best-practices`, `testing-best-practices`, `infer-conventions`;
- an MCP server (`laravel-boost` in root `.mcp.json`) that runs `php artisan boost:mcp` inside the api
  container, so it needs the stack running (`docker compose up`).

Re-generate after upgrading Laravel or Boost: `docker compose exec api php artisan boost:install`.

Invoke with `/<skill-name>` or let Claude pick by description.

