# Admin + Auth Plan: admin panel (React starter kit) and shop login

Status: **Implemented (MVP + currencies + QA fixes); see DECISIONS.md 'Admin panel decisions' and
docs/qa/ADMIN_QA.md. This file is the original plan.** PLAN.md §6 listed "admin UI" as a cut. The user has now
asked for it. This file adds an admin panel without breaking any CLAUDE.md rule. Scope now also covers
**customer login/logout in `web/`** (§19), because it shares the users table, sessions, APP_KEY and
auth config with the admin panel. Decisions marked **[decide]** need the user's call before
building (§18).

## 1. Goal

A small, authenticated back office at `http://localhost:8000/admin` for the shop owner. It is built
with Laravel's official **React starter kit**, served by the existing `api` container, and still
starts with `docker compose up --build`. MVP scope: log in, see counts, browse and filter enquiries,
and re-send a failed enquiry. It reads and writes only through `CatalogueService` and the
repositories, and never calls Fake Store directly.

**Starter kit stack (checked against laravel.com/docs/starter-kits and `laravel/react-starter-kit`
main, Laravel 13.x):** Inertia 3 (`inertiajs/inertia-laravel ^3.0`, `@inertiajs/react ^3`), React
19.2, TypeScript ~5.7, Tailwind 4 (`@tailwindcss/vite`), shadcn/ui components copied into
`resources/js/components/ui` (Radix + `class-variance-authority`/`clsx`/`tailwind-merge`/`lucide-react`),
Laravel Fortify for auth, Laravel Wayfinder for typed routes, `laravel-vite-plugin ^3` on **vite-plus**
(`vite` is aliased to `@voidzero-dev/vite-plus-core`, and the scripts use `vp build` / `vp dev` / `vp check`),
and PHPUnit 12. By default it ships registration, password reset, email verification, 2FA and passkeys.
We keep **login/logout only**.

## 2. Recommendation summary

- **Port the kit into `api/` under `/admin` (option a).** Generate the kit in a throwaway directory
  outside the repo, copy in only the pieces listed in §5, and pin the kit commit in DECISIONS.md.
  This gives one app, one set of models, and one `CatalogueService`.
- **Two route stacks in one app.** `/api/v1` stays stateless: `api` middleware, no session,
  JSON errors, CORS. `/admin` uses the `web` middleware group with sessions, CSRF, Fortify auth and
  a `can:admin` gate. Errors render as JSON for `api/*` and as normal web responses for `/admin`.
- **Admins only.** There is no registration, password reset, 2FA or passkeys. The first admin is
  created from `ADMIN_EMAIL`/`ADMIN_PASSWORD` by an idempotent `admin:create` command that the
  entrypoint runs. `users.is_admin` plus a `Gate::define('admin')` protects every route. The panel is
  off unless `ADMIN_ENABLED=true`.
- **MVP (~60–70 min):** kit port + auth + Docker build (~35), dashboard counts (~8), enquiries
  list/filter/detail (~12), re-send failed enquiry (~10), docs (~5). **Next:** trigger import,
  DB-backed featured products, product edit rules, Vite HMR on :5174, password change.
- **Shop login (§19):** Sanctum SPA cookie auth (same-site `localhost`, CSRF cookie). The API
  gets `/api/v1/auth/{register,login,logout,me}`. Customers and admins share one `users` table and
  one `web` session guard, and `is_admin` separates them. A logged-in customer gets a pre-filled
  enquiry form, and the enquiry is linked to `user_id`. Guests can still enquire.
- **Docker:** Node goes into the api image (copied from a pinned `node:22` image). The entrypoint
  runs `npm ci && npm run build` when the admin build is missing or stale, and `php -S` serves
  `public/build` as static files. No new service and no new port. `web` keeps :5173.

## 3. Options considered (Q1)

| Option | Verdict |
|---|---|
| **(a) Port kit pieces into `api/`, routes under `/admin`** | **Recommended.** It reuses `CatalogueService`, the repositories, `CatalogueImporter`, `EnquiryReceived`, factories and the test DB. About 25 files to copy and trim. |
| (b) Separate `admin/` Laravel app from `laravel new`, sharing Postgres | Rejected. It duplicates models and migrations, has two owners of one schema, and would copy or bypass `CatalogueService`, which breaks the CLAUDE.md layering. |
| (c) Admin pages inside `web/` + new `/api/v1/admin/*` JSON endpoints with Sanctum | Rejected for now. It is not the starter kit, needs cookie auth across :5173 and :8000 (CORS credentials, Sanctum), and mixes back office with shop styling. |
| (d) Filament / Nova | Out of scope. The user asked for the React starter kit, and Nova is paid. |

## 4. Architecture

```
 Browser ──► web (React/Vite :5173) ──► /api/v1/*  (api middleware: stateless, CORS, JSON errors)
                                              │
 Browser ──► http://localhost:8000/admin/* ───┤  (web middleware: session cookie, CSRF,
             Inertia + React (built assets     │   Fortify login, auth + can:admin, ADMIN_ENABLED)
             in api/public/build)              ▼
                         ┌─────────── api container (php -S + router script) ───────────┐
                         │ Api\*Controller        Admin\*Controller (thin, Inertia::render)│
                         │        │                     │   FormRequests                   │
                         │        └──────► CatalogueService ◄───── (admin use cases too)    │
                         │                 │       │          │                            │
                         │   ProductRepository  EnquiryRepository  EnquiryReceived (Mail)  │
                         │                 │       │          │                            │
                         │   CatalogueImporter ◄─ ProductSource (import only; MVP+ trigger)│
                         └─────────────┬──────────────────────┬────────────────────────────┘
                                       ▼                      ▼
                                 db (Postgres)          mailpit (SMTP)
```

## 5. Files added and changed (all in `api/`)

From the kit means "copy from the generated kit, then trim". New means written by us.

```
app/
├── Http/
│   ├── Controllers/Admin/
│   │   ├── DashboardController.php          new: Inertia 'dashboard' with counts from CatalogueService
│   │   ├── EnquiryController.php            new: index (filter), show, resend
│   │   └── (MVP+) CatalogueImportController.php
│   ├── Middleware/HandleInertiaRequests.php from the kit: rootView 'admin', shares auth.user {name,email}, flash
│   └── Requests/Admin/
│       ├── ListEnquiriesRequest.php         new: status in pending|sent|failed (optional), page int
│       └── (MVP+) RunImportRequest.php      source in fakestore|file
├── Actions/Fortify/                          from the kit: none needed (no registration or reset)
├── Console/Commands/CreateAdmin.php          new: admin:create {--email=} {--from-env} (idempotent)
├── Models/User.php                           from the kit, + is_admin bool cast, $hidden password
├── Providers/FortifyServiceProvider.php      from the kit, trimmed: loginView → Inertia 'auth/login', 'login' rate limiter
├── Catalogue/
│   ├── CatalogueService.php                  + dashboardStats(), enquiries(), enquiry(), resendEnquiry()
│   ├── Contracts/EnquiryRepository.php       + paginate(?EnquiryStatus), find(id), countsByStatus()
│   ├── Repositories/EloquentEnquiryRepository.php  implements the above
│   └── Data/{StoredEnquiry,DashboardStats}.php     new readonly DTOs
config/
├── fortify.php                               from the kit: prefix 'admin', home '/admin', features []
├── inertia.php                               from the inertia-laravel publish (testing.ensure_pages_exist)
└── admin.php                                 new: enabled (ADMIN_ENABLED), bootstrap email/password
database/
├── migrations/2026_11_22_000001_create_users_table.php   users (+ is_admin), password_reset_tokens left out, sessions left out (file driver)
└── factories/UserFactory.php                 from the kit, + admin() state
routes/web.php                                new: /admin group (see §6), loaded only when admin.enabled
resources/
├── views/admin.blade.php                     the kit's app.blade.php, renamed (@viteReactRefresh, @vite, @inertia)
├── css/app.css                               from the kit: Tailwind 4 + shadcn oklch tokens (admin only)
└── js/                                       from the kit, trimmed
    ├── app.tsx                               createInertiaApp, title "Admin · Product Catalogue"
    ├── pages/auth/login.tsx                  from the kit (remove register / forgot-password links)
    ├── pages/dashboard.tsx                   from the kit, replace placeholders with stat cards
    ├── pages/enquiries/{index,show}.tsx      new: table + status filter + pagination; detail + "Re-send"
    ├── layouts/{app-layout,auth-layout}.tsx (+ app/app-sidebar-layout, auth/auth-simple-layout)
    ├── components/{app-sidebar,app-shell,nav-main,nav-user,...}.tsx  from the kit (nav: Dashboard, Enquiries)
    ├── components/ui/{button,card,input,label,badge,table,select,sidebar,dropdown-menu,...}.tsx
    ├── lib/utils.ts, hooks/*, types/index.d.ts   from the kit (+ Enquiry, Paginated<T> types)
package.json, package-lock.json, vite.config.ts, tsconfig.json, components.json   from the kit, trimmed
bootstrap/app.php                             + web routes, Inertia middleware on web, split exception rendering
docker/entrypoint.sh, Dockerfile, .dockerignore   §8
tests/Feature/Admin/*Test.php                 §9
```

**Left out of the kit:** registration, password reset, email verification, 2FA, passkeys
(`@laravel/passkeys`, `input-otp`), `laravel/chisel`, settings pages (profile, password,
appearance), `HandleAppearance` (we follow the system light/dark setting), the welcome page, SSR,
Sail and Larastan.

## 6. Routes

All admin routes sit under `prefix('admin')` with the `web` middleware group. They are registered
only when `config('admin.enabled')` is true. Otherwise `/admin` returns 404.

| Method | Path | Middleware | Handler | MVP |
|---|---|---|---|---|
| GET | `/admin/login` | guest | Fortify → Inertia `auth/login` | ✅ |
| POST | `/admin/login` | guest, `throttle:login` (5/min per email+IP) | Fortify | ✅ |
| POST | `/admin/logout` | auth | Fortify | ✅ |
| GET | `/admin` | auth, can:admin | `DashboardController` → `dashboard` | ✅ |
| GET | `/admin/enquiries?status=&page=` | auth, can:admin | `Admin\EnquiryController@index` | ✅ |
| GET | `/admin/enquiries/{enquiry}` | auth, can:admin | `@show` (id `[0-9]{1,18}`) | ✅ |
| POST | `/admin/enquiries/{enquiry}/resend` | auth, can:admin, `throttle:6,1` | `@resend` → redirect back with a flash message | ✅ |
| POST | `/admin/catalogue/import` | auth, can:admin, `throttle:3,1` | `CatalogueImportController` → flash `ImportResult` | next |
| GET/PUT | `/admin/featured` | auth, can:admin | featured products editor | next |
| GET | `/admin/products`, `/admin/products/{id}` | auth, can:admin | read-only browse | next |

A logged-in user who is not an admin gets 403 everywhere. A guest is redirected to `/admin/login`.
Fortify's `home` is `/admin`.

## 7. Coexistence with the API (Q2)

| Concern | Today | Change |
|---|---|---|
| Routing | `withRouting(api: …)` only | Add `web: routes/web.php`. Add `HandleInertiaRequests` (+ `AddLinkHeadersForPreloadedAssets`) to the **web** group only via `$middleware->web(append: …)`. The `api` group is untouched, so it has no session and no CSRF. |
| Sessions | `SESSION_DRIVER=array` | **`SESSION_DRIVER=file`**: sessions start in the `web` group, and for `/api/*` only on requests from the stateful shop origin (Sanctum, §19). Requests from other origins stay stateless. No `sessions` table is needed. `SESSION_PATH=/` (shared by admin and shop), `SESSION_DOMAIN` empty (host-only `localhost`), `SESSION_SAME_SITE=lax`, `SESSION_SECURE_COOKIE` false locally. |
| Cache | `CACHE_STORE=file` | Unchanged. The login rate limiter uses it. |
| `APP_KEY` | ephemeral per start | **Must be stable** (encrypted session and remember cookies). Entrypoint: if `APP_KEY` is empty, read `storage/app/.app-key`, or generate it once and write it there. The file is gitignored by `storage/app/.gitignore` and survives restarts through the bind mount. A set `APP_KEY` env var still wins. |
| CSRF | none | `web` group's `ValidateCsrfToken`. Inertia/axios send `X-XSRF-TOKEN` automatically. `/api/*` is not in the web group, so nothing to exclude. |
| CORS | `paths: ['api/*']` | Admin is same-origin (:8000), so no change for it. The shop needs `paths: ['api/*','sanctum/csrf-cookie']` and `supports_credentials: true` (§19). Origins stay restricted to `CORS_ALLOWED_ORIGIN`. |
| Exceptions | `shouldRenderJsonWhen(fn () => true)` + JSON 404 for all | `shouldRenderJsonWhen(fn (Request $r) => $r->is('api/*') \|\| $r->expectsJson())`. The JSON 404 renderer only applies under that same condition. `/admin` gets Laravel's default (debug-off) error pages, and Inertia shows 403/404/419 as normal responses. `ErrorResponsesTest` still passes (it hits `/api/v1/*`). Update the DECISIONS.md "Errors" row. |
| Auth redirect | none | `$middleware->redirectGuestsTo('/admin/login')`. API routes never use `auth`, so this doesn't affect them. |
| Static assets | `php -S` + router script | The router serves files that exist in `public/`, so `public/build/*` works with no change. |

## 8. Auth (Q3)

- **Users table** (new migration): `id, name, email unique, password, is_admin boolean default false,
  remember_token, timestamps`. Laravel's default `password_reset_tokens` and `sessions` tables are
  not needed (no reset, file sessions).
- **Fortify** (admin only) uses `Fortify::authenticateUsing()` to accept **only `is_admin` users**, so
  a customer can't log in at `/admin/login` (generic "invalid credentials" error). `features => []`. Login and logout are always on, and registration, reset,
  verification, 2FA and passkeys are off. `prefix => 'admin'`, `home => '/admin'`, `guard => 'web'`.
- **Gate**: `Gate::define('admin', fn (User $u): bool => $u->is_admin)`, used as `can:admin` on the
  group. Without registration every user is an admin in practice, but the gate is what makes
  "403 for non-admin" testable and survives adding non-admin roles later.
- **First admin**: `php artisan admin:create --from-env` reads `ADMIN_EMAIL`/`ADMIN_NAME`/`ADMIN_PASSWORD`.
  It creates the user (hashed, `is_admin=true`) if missing and does nothing if the user exists, so
  it never resets a changed password. The entrypoint runs it when `ADMIN_ENABLED=true` and both vars
  are set. It refuses passwords shorter than 12 characters and never logs the password. Interactive
  use: `admin:create --email=x` prompts for the password (hidden).
- **Password reset: not in MVP.** Reset is `admin:create --email=x --reset`. A Fortify reset mail via
  Mailpit is a next step and needs the CLAUDE.md mail rule widened (§11).

## 9. Feature scope and estimates (Q4)

| Feature | MVP? | Est. | Notes |
|---|---|---|---|
| Kit port + Fortify login + users/gate + `admin:create` | ✅ | 20m | §5 file list |
| Docker: Node in image, build step, APP_KEY persistence, env | ✅ | 10m | §10 |
| Exception/route split in `bootstrap/app.php` | ✅ | 5m | §7 |
| Dashboard: products, categories, enquiries by status (pending/sent/failed), last import time (`max(imported_at)`) | ✅ | 8m | `CatalogueService::dashboardStats()` → `DashboardStats` DTO. Product/category counts via `ProductRepository::counts()` |
| Enquiries list (filter by status, 20/page, newest first) + detail | ✅ | 12m | `EnquiryRepository::paginate()`/`find()` → `StoredEnquiry` DTO → `Admin\EnquiryResource` (our shape) |
| Re-send failed enquiry | ✅ | 10m | see below |
| Tests + docs | ✅ | 10m (tests written alongside, docs at the end) | §11, §13 |
| **MVP total** | | **~65–75m** | To hit 45–60m, move "re-send" to next (saves ~10m) |
| Trigger `catalogue:import` (live / snapshot) + show `ImportResult` | next | 15m | Controller → `CatalogueService::import(?source)` → `ProductSourceFactory` + `CatalogueImporter`. The same path as the command, so no direct Fake Store call. Runs synchronously. Fake Store timeout + 2 retries can block a `php -S` worker for 30s+ (4 workers), so a queued job is the follow-up |
| Featured products in DB | next | 30m | New `featured_products (product_id FK unique, position)`; `ProductRepository::featured()` drops its `$externalIds` parameter; `CatalogueService` loses the `featuredIds` constructor argument; `config('catalogue.featured_ids')` / `FEATURED_PRODUCT_IDS` become only the **initial seed** (migration/import seeds the table when empty); `FeaturedProductsTest` switches to seeding the table; phpunit.xml env stays for the seed test. API contract unchanged |
| Product browse (read-only) | next | 15m | Reuses `ProductRepository` |
| Product edit | later | 45m+ | **Conflict:** `CatalogueImporter` upserts and overwrites `title, description, price, image_url, rating_*, category_id`. **Proposed rule:** upstream-owned fields stay read-only in admin. Local edits go in a `product_overrides` table (product_id, field values, edited_by) that `EloquentProductRepository` merges at read time, so the import never touches them and an admin can "revert to upstream". Rejected alternative: a `locally_edited` flag that makes the importer skip the row, because the row then silently stops getting price updates |
| Password change page (kit's settings/password) | later | 10m | |
| Vite dev server with HMR for admin | later | 10m | §10 |

**Re-send design (MVP):** `CatalogueService::resendEnquiry(int $id): EnquiryResult`. It loads
`StoredEnquiry` via `EnquiryRepository::find()` (null → 404). Only `failed` (and `pending` older
than 5 minutes, which means it got stuck) may be re-sent. Otherwise it throws `EnquiryNotResendable`,
which becomes a flash error. It then rebuilds `EnquiryData` from the stored row, loads the `Product`
via `ProductRepository::find()` and runs the same send → `markSent`/`markFailed` block as
`submitEnquiry`, extracted into one private `deliver(int $id, EnquiryData, Product)` method so there
is one mail path through `EnquiryReceived`. A failure logs the enquiry id only, and the admin sees
"Still failing, try later" (a flash message, not a 502 page). Note: `submitEnquiry` is refactored,
so the existing `StoreEnquiryTest` must still pass unchanged.

## 10. Docker and build (Q5)

- **Dockerfile:** add Node without a second service:
  `COPY --from=node:22.x-bookworm-slim /usr/local/bin/node /usr/local/bin/node` +
  `/usr/local/lib/node_modules/npm`, and symlink `npm`/`npx`. Pin the exact tag. Why: the kit's
  Wayfinder Vite plugin runs `php artisan wayfinder:generate` during the build, so the build needs
  PHP and Node in the **same** container. A `node`-only container can't build it.
- **Image layer:** `COPY package.json package-lock.json ./ && RUN npm ci`, the same layer caching
  as composer.
- **compose:** add an anonymous volume `/var/www/html/node_modules` (like `vendor`) and new env
  vars (§13). No new ports: built assets are served on :8000. `web` keeps :5173. The future admin HMR
  port is **5174**.
- **entrypoint.sh** (order): `composer install` → `npm ci` (fast when unchanged) → APP_KEY
  resolve/persist → `migrate --force` → `catalogue:import --if-empty` → if `ADMIN_ENABLED=true`:
  `admin:create --from-env` and `npm run build` when `public/build/manifest.json` is missing or
  older than `resources/js`, `resources/css` or `package-lock.json` → `php -S`. The build adds about
  10–20s to the first start only.
- **Changing admin code during dev:** `docker compose exec api npm run build`, or restart the
  container. **Next step:** `ADMIN_VITE_DEV=true` runs `npm run dev -- --host 0.0.0.0 --port 5174`
  in the background in the entrypoint and maps `5174:5174`. The `public/hot` file then switches
  `@vite` to the dev server.
- `.dockerignore`: add `node_modules`, `public/build`, `public/hot`. (`.gitignore` already has them.)
- `docker compose up --build` stays the one command.

## 11. Theming (Q6)

The kit's `resources/css/app.css` already follows the token rule. Components use Tailwind utility
classes like `bg-background` and `text-primary`, which `@theme` maps to CSS variables (`--background`,
`--primary`, …, oklch) defined in `:root` and `.dark`. So the "CSS variables / theme tokens" rule is
met as long as **components never use raw palette classes** (`bg-blue-600`) or hex values. Rebranding
the admin means editing the variables in `app.css`. The admin and the shop stay fully separate: the
admin tokens live in `api/resources/css/app.css` and the shop tokens in `web/`, there are no shared
CSS files, and Tailwind is never added to `web/`. Proposed CLAUDE.md wording is in §13.

## 12. Tests (Q7)

`tests/TestCase.php` adds `$this->withoutVite()` for admin tests (no manifest in CI).
`config/inertia.php` keeps `testing.ensure_pages_exist = true` (pages exist in the container), so a
typo in a page name fails the test. All tests use `RefreshDatabase` on `catalogue_test`,
`UserFactory` with an `admin()` state, and `Mail::fake()`.

| File | Cases |
|---|---|
| `Admin/AdminAccessTest` | guest → redirect `/admin/login` on every admin GET; non-admin → 403 on every route; admin → 200; `ADMIN_ENABLED=false` → `/admin` 404; API routes still send no `Set-Cookie` header |
| `Admin/LoginTest` | login page renders `auth/login`; valid admin login → redirect `/admin`; wrong password → error, no session; 6th attempt in a minute → 429; logout; `/admin/register` and `/admin/forgot-password` → 404 |
| `Admin/DashboardTest` | `assertInertia(fn ($p) => $p->component('dashboard')->where('stats.enquiries.failed', 2)…)` with factory data |
| `Admin/EnquiriesTest` | list newest first, paginated; `?status=failed` filters; invalid status → validation error; detail shows fields; unknown id → 404; props contain no raw model columns beyond `Admin\EnquiryResource` |
| `Admin/ResendEnquiryTest` | failed → mail sent to the sales inbox (`Mail::assertSent(EnquiryReceived::class)`), status `sent`, `mailed_at` set; mail throws → stays `failed`, flash error, log has the id only; `sent` enquiry → not resendable, no mail; no CSRF token → 419 |
| `CreateAdminCommandTest` | creates an admin from env; second run does nothing (password unchanged); short password → exit 1; missing env → exit 1 with message |
| `Unit/CatalogueServiceTest` | + `resendEnquiry` status rules |
| (next) `Admin/CatalogueImportTest` | `Http::fake()` + `preventStrayRequests`; live OK → flash with counts and origin; upstream down → snapshot used; all fail → flash error, DB unchanged |

**Frontend tests:** none in MVP. The pages are thin and covered through `assertInertia`, plus
`tsc --noEmit` and `vp check` (lint/format from the kit). Vitest + RTL for admin pages is a next step.

**run-checks** gains:
```bash
docker compose exec api npm run types:check   # tsc --noEmit on resources/js
docker compose exec api npm run check         # vp check (kit's lint/format)
docker compose exec api npm run build         # admin assets build
```

## 13. Security (Q8)

- Every admin route has `auth` + `can:admin`, and only `/admin/login` is `guest`. `AdminAccessTest`
  loops over `Route::getRoutes()` with prefix `admin`, so a new unprotected route fails the test.
- Login rate limit: 5/min per email+IP (the kit's Fortify limiter). Re-send and import routes are
  throttled.
- CSRF on all admin POSTs (`web` group). Session cookie is `HttpOnly`, `SameSite=lax`, path `/`,
  and `Secure` via `SESSION_SECURE_COOKIE=true` outside local.
- No personal data in logs. Admin actions log `enquiry_id` and the admin `user_id` only, never the
  name, email, phone or message. Inertia props go through `Admin\EnquiryResource`, so only the listed
  fields reach the browser.
- **Off by default outside local:** `config('admin.enabled')` defaults to `false`, and `.env.example`
  sets `ADMIN_ENABLED=true` for local. Production would also put `/admin` behind a VPN or IP allowlist
  (documented only).
- `APP_DEBUG=false` stays, so admin error pages show no traces.
- `admin:create` hashes with the default hasher, and the password is never in argv for the
  interactive path.

## 14. Docs, config and skills changes (Q9)

| File | Change |
|---|---|
| **PLAN.md** | §1 stack: add "Admin: Inertia + React (starter kit) in `api/`". §2 layout note. §3 add the row "Admin UI → `/admin` in api, via `CatalogueService`". §4 add `Admin\*` controllers, `users` table and `routes/web.php`. §6: remove "admin UI" and "auth" from Cuts, add "admin only, no registration". §7 add the admin tests. §8 add `http://localhost:8000/admin` and the admin login. §9 add the admin step. §11 add the `admin-page` skill. Link to this file. |
| **TECH_STACK.md** | §1 diagram (`/admin` on :8000). §3: Inertia, Fortify, Wayfinder. Cache/session row → `SESSION_DRIVER=file` for admin. New "Admin frontend" table: React 19.2, TS ~5.7 (separate from web's 6.0), Tailwind 4, shadcn/ui, vite-plus. Node 22 inside the api image. §6 admin tests. §8 new env vars. |
| **DECISIONS.md** | Rows: kit ported into `api/` (option a) + pinned kit commit; admins only with no registration/reset/2FA (why); `is_admin` gate; `admin:create` bootstrap; `SESSION_DRIVER=file` + persisted APP_KEY (replaces the "ephemeral key" row); errors JSON only for `api/*` (replaces the "Errors" row); two TypeScript versions (kit 5.7 / web 6.0) and two linters (kit `vp check` / web ESLint), kept apart on purpose; re-send rules; product-edit override rule (proposed). Update "Stack choice: API auth / admin → Fortify + Inertia (built)". |
| **README.md** | Quick start: the admin URL + default local login. "Tests and checks": the 3 new commands. Remove "admin UI" from Cuts. Troubleshooting: "admin shows an old build → `docker compose exec api npm run build`". |
| **.env.example** | `ADMIN_ENABLED=true`, `ADMIN_NAME="Admin"`, `ADMIN_EMAIL=admin@example.com`, `ADMIN_PASSWORD=` ([decide]: empty, or a local-only default), `SESSION_DRIVER=file` (was `array`), `SESSION_PATH=/`, `SESSION_DOMAIN=`, `SANCTUM_STATEFUL_DOMAINS=localhost:5173`, `SESSION_SECURE_COOKIE=false`, `SESSION_LIFETIME=120`. Change the APP_KEY comment: "empty → generated once and stored in `api/storage/app/.app-key`". |
| **docker-compose.yml** | Pass the new vars. Anonymous `node_modules` volume. |
| **CLAUDE.md** (proposed wording) | Architecture: "The admin panel (`/admin`) lives in `api/` (Inertia + React starter kit). Admin controllers are thin and call `CatalogueService` like API controllers. They never call `ProductSource`/Fake Store directly (import goes through `CatalogueImporter`) and pass data to pages through Resources, never raw models." · "Every `/admin` route requires `auth` + `can:admin`; there is no public registration." · Styling: "Admin styles use only the shadcn CSS variables in `api/resources/css/app.css` via token classes (`bg-primary`), never raw palette classes or hex. Admin and shop styles are never shared." · Mail (only if password reset is added): "Business email goes only through Mailables in `app/Mail` (`EnquiryReceived`); framework auth notifications are allowed." · Testing: "Admin pages: `assertInertia` feature tests; run `npm run types:check` in the api container." |
| **`.claude/skills/run-checks`** | Add the 3 api npm commands. |
| **`.claude/skills/pr-ready`** | Add "admin off when `ADMIN_ENABLED=false`" and "no `ADMIN_PASSWORD` committed". |
| **`.claude/skills/admin-page`** (new, warranted: recurring task with non-obvious rules) | Outline: **When**: add or change an `/admin` page or action. **Steps**: 1) feature test first (`assertInertia`, guest redirect, non-admin 403, `Mail::fake`/`Http::fake`); 2) route in the `/admin` group in `routes/web.php` (it inherits `auth`+`can:admin`; throttle any POST); 3) FormRequest in `Requests/Admin`; 4) use case in `CatalogueService` → repository (never Eloquent in controllers, never `ProductSource`); 5) thin controller → `Inertia::render('x', ['y' => Resource])` or `redirect()->back()->with('flash')`; 6) page in `resources/js/pages/…` with kit layout + `components/ui`, typed props in `types/`, no `any`, token classes only; 7) add the nav item in `app-sidebar.tsx`; 8) `npm run build` + types:check + tests. **Done when**: run-checks green, access test covers the route, docs updated. |
| **AI_LOG.md** | Log the "admin un-cut" decision (`ai-log` skill). |

## 15. Dependencies (Q10)

**composer (require):**
- `inertiajs/inertia-laravel ^3.0`: server adapter that renders React pages from controllers without a separate JSON API.
- `laravel/fortify ^1.37`: first-party, headless login/logout + rate limiting that the kit's auth pages are built on.
- `laravel/sanctum`: shop SPA cookie auth with CSRF for `/api/v1/auth/*` (§19); no token table.
- `laravel/wayfinder ^0.1`: generates typed TS route/action helpers that the kit pages import. Keeping it avoids rewriting every kit page. Pre-1.0, so a **[decide]** item; the alternative is plain URL strings.

**npm (new `api/package.json`, separate from `web/`):**
- `@inertiajs/react ^3`, `@inertiajs/vite ^3`: client side of Inertia + page resolution in Vite.
- `react`, `react-dom ^19.2`, `@types/react*`: UI.
- `typescript ~5.7`: the kit's version (strict mode, no `any`).
- `vite` (aliased to `@voidzero-dev/vite-plus-core`), `vite-plus`, `laravel-vite-plugin ^3`, `@vitejs/plugin-react`: build/dev server as the kit ships it (vite-plus also gives `vp check` lint/format).
- `@laravel/vite-plugin-wayfinder`: runs Wayfinder generation in the build.
- `@rolldown/plugin-babel`, `babel-plugin-react-compiler`: the kit's React Compiler setup. Optional, drop if the build is slow.
- `tailwindcss ^4`, `@tailwindcss/vite`, `tw-animate-css`: admin styling (tokens via `@theme`).
- `@radix-ui/react-{avatar,dialog,dropdown-menu,label,select,separator,slot,tooltip,collapsible}`: accessible primitives behind the shadcn components we keep (only those the kept components import).
- `class-variance-authority`, `clsx`, `tailwind-merge`: shadcn variant/class helpers.
- `lucide-react`: icons used by the kit layout.
- `sonner`: flash toasts (re-send/import results).

**Not taken from the kit:** `laravel/chisel`, `larastan`, `laravel/sail`, `@laravel/passkeys`,
`input-otp`, `concurrently`, `@laravel/multiplex`, and the Radix checkbox/toggle/navigation-menu
packages if no kept component uses them.

**Docker:** `node:22` binary copied into the api image (no new service).

## 16. Build order (MVP)

| # | Step | Time | Done when |
|---|---|---|---|
| 1 | Generate the kit outside the repo (`docker run --rm -v "$PWD":/app -w /app composer:2.11.2 create-project laravel/react-starter-kit kit`, then record the commit). Copy the §5 files, trim features, add deps. | 12m | `composer install` + `npm ci` OK in the container |
| 2 | users migration + `User` + factory, Fortify config/provider, gate, `admin:create`, `routes/web.php`, `bootstrap/app.php` split | 10m | `AdminAccessTest`, `LoginTest`, `CreateAdminCommandTest` green. Existing API tests still green |
| 3 | Dockerfile (Node), entrypoint (npm ci, APP_KEY persist, admin:create, build), compose + `.env.example` | 10m | `docker compose up --build` → login at :8000/admin works. Restarting keeps the session |
| 4 | Dashboard stats (service + repo + page) | 8m | `DashboardTest` green |
| 5 | Enquiries list/filter/detail | 12m | `EnquiriesTest` green |
| 6 | Re-send (refactor `deliver()`, service, route, button + toast) | 10m | `ResendEnquiryTest` + `StoreEnquiryTest` green, mail visible in Mailpit |
| 7 | run-checks (+ new npm checks), docs/skills per §14 | 8m | everything green |
| | **Total** | **~70m** (~60m without step 6) | |

## 17. Risks

- **Kit drift:** the kit's `main` moves fast (vite-plus, Wayfinder 0.x, Inertia 3). Mitigation: pin
  the commit, and run `npm ci`/`composer.lock` only from the lockfiles.
- **Breaking the API:** the exception and middleware split could turn API errors into HTML or add
  cookies. Mitigation: the existing `ErrorResponsesTest` + the new "no Set-Cookie on /api" test run
  before anything else.
- **Startup time:** `npm ci` + build on first start adds roughly 30–60s. The ten-minute budget is
  still fine, but call it out in the README.
- **`php -S` workers:** synchronous mail re-send (and the later import) hold one of 4 workers.
  This is acceptable for the PoC. Queues are the next step, as already planned.
- **Two TS/lint toolchains** (web: TS 6.0 + ESLint, admin: TS 5.7 + `vp check`). Kept apart on
  purpose, but reviewers may ask why.
- **Personal data in the admin UI:** enquiries show customer contact details. This is fine behind
  auth, but production needs HTTPS, secure cookies and access control at the network edge.
- **Time:** the MVP is slightly over 60m. Re-send is the cut line.

## 18. Open questions for the user [decide]

1. **MVP cut line:** include "re-send failed enquiry" (~70m), or ship login + dashboard + enquiries
   only (~60m)?
2. **Import trigger in MVP?** It adds about 15m and is synchronous in the request.
3. **Admin bootstrap credentials:** a local-only default `ADMIN_PASSWORD` in compose/`.env.example`
   (simplest for the reviewer, like `DB_PASSWORD=catalogue`), or empty with a README step
   `docker compose exec api php artisan admin:create`?
4. **Wayfinder:** keep it (less porting, pre-1.0 dependency), or strip it and use plain URLs
   (about 10m more porting, one fewer composer + npm dependency)?
5. **Featured products to DB** next (changes `ProductRepository::featured()` and the
   `FEATURED_PRODUCT_IDS` role), or keep config-driven for the submission?
6. **Product editing:** accept the "upstream fields read-only; overrides table later" rule?
7. **Password reset** via Fortify + Mailpit later, which widens the CLAUDE.md mail rule? Or keep
   CLI reset only?
8. **Customer self-registration** (recommended, §19b), or seeded demo customers only?
9. **Shared session:** an admin who logs into the shop is also logged into `/admin` (one `web`
   guard, one cookie). Accept this for the PoC, or use a separate `admin` guard and cookie
   (about 10m more)?

## 19. User authentication (shop customers in `web/`)

### (a) Approach: Sanctum SPA cookie auth (recommended)

| | Sanctum SPA cookie (recommended) | Sanctum API tokens (bearer) |
|---|---|---|
| How | `GET /sanctum/csrf-cookie` → `POST /api/v1/auth/login` → Laravel session cookie (HttpOnly). Requests from the shop origin are made stateful by `EnsureFrontendRequestsAreStateful` (`$middleware->statefulApi()`). | Login returns a token. The SPA stores it and sends `Authorization: Bearer`. |
| Security | The token is never readable by JS (XSS can't steal it). CSRF is covered by the XSRF cookie/header + SameSite=lax. | The token has to live in JS-readable storage, so XSS can steal it. No CSRF issue. |
| Fit | `localhost:5173` and `localhost:8000` are **same-site** (cookies ignore the port), so it works locally without HTTPS. It shares the session, `APP_KEY` and `web` guard with the admin panel. | It needs a `personal_access_tokens` table plus token expiry and revoke work. Better suited to mobile or third-party clients. |
| Cost | CORS `supports_credentials: true`; `SANCTUM_STATEFUL_DOMAINS=localhost:5173`; `client.ts` sends `credentials: 'include'` + `X-XSRF-TOKEN`. Production needs the shop and API on one registrable domain (`SESSION_DOMAIN=.example.com`). | Simpler CORS, but more client token handling. |

**Consequences of going stateful:**
- **Every mutating request from the shop origin**, including the existing `POST /enquiries`,
  must send `X-XSRF-TOKEN`. `client.ts` calls `/sanctum/csrf-cookie` once before the first mutation
  and copies the `XSRF-TOKEN` cookie into the header. Requests without a shop `Origin`/`Referer`
  (curl, the existing feature tests) stay stateless with no CSRF, so `StoreEnquiryTest` is
  unchanged.
- `/sanctum/csrf-cookie` lives outside `/api/v1`. `client.ts` derives the origin from
  `VITE_API_URL`, so it is still the only module that knows the URL.
- Don't run `install:api`. Run `composer require laravel/sanctum` + publish `config/sanctum.php`
  only. No tokens table is needed.

### (b) Endpoints (all JSON, `/api/v1/auth`)

```jsonc
// User (UserResource)
{ "id": 7, "name": "Ada Lovelace", "email": "ada@example.com" }   // is_admin never exposed
```

| Method | Route | Middleware | Request | 2xx | Errors |
|---|---|---|---|---|---|
| GET | `/sanctum/csrf-cookie` | – | – | 204 + `XSRF-TOKEN` cookie | – |
| POST | `/auth/register` | guest, `throttle:register` (3/min per IP) | `{ name, email, password, password_confirmation }` | 201 `{ "data": User }` (logged in) | 422 (email taken, password min 8, mixedCase or numbers via `Password::defaults()`) |
| POST | `/auth/login` | guest, `throttle:login-shop` (5/min per email+IP) | `{ email, password, remember? }` | 200 `{ "data": User }` | 422 `{ message, errors: { email: ["These credentials do not match our records."] } }`, 429 |
| POST | `/auth/logout` | `auth:sanctum` | – | 204 | 401 |
| GET | `/auth/me` | `auth:sanctum` | – | 200 `{ "data": User }` | 401 `{ "message": "Unauthenticated." }` |

- **Register: yes** (recommended). Without it customers have no way to get an account. It is
  minimal, with no email verification and no password reset (next step: Mailpit). Register always
  sets `is_admin=false`, because `is_admin` is not fillable from input.
- Login and register call `session()->regenerate()`. Logout invalidates the session and regenerates
  the token. Thin `Api\AuthController` + `LoginRequest`/`RegisterRequest`, with the logic in a small
  `App\Auth\AuthService` (or `CatalogueService` if the user prefers one service; **[decide]** is
  folded into Q9 above).
- Admin login stays on Fortify at `/admin/login` (Inertia). Shop login uses these JSON endpoints.
  Both use `Auth::guard('web')`.

### (c) Customers and admins together

- **One `users` table**, `is_admin boolean default false`. This is the same migration as §8, so it
  is built once. No roles table: two kinds of user don't justify one. A `role` enum is the upgrade
  path.
- **Guards:** `web` (session) for both. `auth:sanctum` on the API resolves the same session user.
  `can:admin` protects `/admin`. Fortify's `authenticateUsing` rejects non-admins at `/admin/login`.
  An admin logged into the shop is also logged in at `/admin` (Q9).
- The admin enquiry list shows "customer account" when `user_id` is set.

### (d) What a logged-in customer gets (PoC)

- Header shows "Hi, {name}" with a **Log out** button. Guests see **Log in** and **Register** links.
- The enquiry form **pre-fills name and email** (editable). The backend sets
  `enquiries.user_id = auth user` (new nullable FK, `nullOnDelete`, index). `EnquiryData` gets
  `?int $userId`, and the controller passes `$request->user()?->id`. It is never taken from the
  request body.
- Guests enquire exactly as today. Nothing in `StoreEnquiryRequest` changes.
- **Next:** a "My enquiries" page (`GET /api/v1/me/enquiries`), password reset, email verification.

### (e) `web/` changes

| File | Change |
|---|---|
| `src/api/client.ts` | `credentials: 'include'` on every fetch; `Accept: application/json`; `ensureCsrf()` (GET `${origin}/sanctum/csrf-cookie` once, re-fetched on a 419); reads the `XSRF-TOKEN` cookie → `X-XSRF-TOKEN` header on POST/PUT/DELETE; new `register()`, `login()`, `logout()`, `me()`; `ApiError.isUnauthenticated` (401) and `isCsrfExpired` (419, retry once) |
| `src/api/types.ts` | `User`, `LoginInput`, `RegisterInput` |
| `src/auth/AuthContext.tsx` + `useAuth.ts` | `{ user: User \| null, status: 'loading'\|'guest'\|'authenticated', login, register, logout }`. Calls `me()` on mount, and a 401 means guest (not an error) |
| `src/pages/LoginPage.tsx`, `RegisterPage.tsx` | Forms with server 422 errors under fields; redirect back (`?next=`) on success |
| `src/components/Layout.tsx` (+ `UserMenu.tsx`) | Login/Register links or the name + Log out button, styled with tokens only |
| `src/components/EnquiryForm.tsx` | Initial name/email from `useAuth().user` |
| `src/App.tsx` / `main.tsx` | `<AuthProvider>`; routes `/login`, `/register` |
| `src/test/handlers.ts` | Default `GET /auth/me` → 401 and `GET /sanctum/csrf-cookie` → 204, so existing page tests keep passing; auth handlers for 200/422/429 |

### (f) Tests and docs

**api** (`tests/Feature/Auth/`):

| File | Cases |
|---|---|
| `RegisterTest` | 201 + logged in (`assertAuthenticated`); `is_admin` in the body is ignored; duplicate email/weak password → 422; throttle 429 |
| `LoginTest` (shop) | valid → 200 User shape (no `is_admin`, no password); wrong password → 422 generic; 6th attempt → 429; admin credentials also work in the shop |
| `LogoutTest`, `MeTest` | `actingAs` → 204 / 200; guest → 401 JSON |
| `StatefulCsrfTest` | request with `Origin: http://localhost:5173` and no XSRF token → 419; with token → OK; no Origin → stateless (no `Set-Cookie`) |
| `StoreEnquiryTest` (+) | logged-in enquiry stores `user_id`; guest → `user_id` null; `user_id` in the body is ignored |
| `ErrorResponsesTest` (+) | CORS sends `Access-Control-Allow-Credentials: true` only for the allowed origin |
| `Admin/LoginTest` (+) | customer credentials rejected at `/admin/login` |

**web** (Vitest + RTL + MSW): header shows Log in when `me` returns 401 and the name + Log out after
login; login 422 shows the field error; logout returns to the guest header; enquiry form pre-filled
when logged in and empty for guests; `client.test.ts` checks that the CSRF cookie is requested
before the first POST and the header is sent.

**Docs** (in addition to §14):
- PLAN.md §4: the `auth` endpoints and the `User` contract; §5: login/register pages; §6: remove
  "auth" from Cuts; §7: the tests above.
- TECH_STACK.md: `laravel/sanctum`.
- DECISIONS.md: cookie over token auth (why); registration on with no verification or reset; one
  users table + `is_admin`; shared session (Q9); enquiries linked to `user_id`; mutations need CSRF
  from the shop origin.
- README: log in / register in the shop.
- `.env.example`: `SANCTUM_STATEFUL_DOMAINS=localhost:5173`, `SESSION_DOMAIN=`, `SESSION_PATH=/`.
- CLAUDE.md (proposed): "Shop auth is Sanctum SPA cookie auth. Never store auth tokens in
  localStorage/sessionStorage. `client.ts` owns `credentials`, CSRF and the API origin." and
  "Every endpoint that needs a user uses `auth:sanctum`, and the user id comes from
  `$request->user()`, never from the request body."
- Skills: `api-endpoint` (auth/CSRF test cases for authenticated routes), `react-frontend`/`web-page`
  (`useAuth`, default `me` 401 handler), `react-testing` (CSRF handler).

**New dependency:** `laravel/sanctum` (composer). It is first-party and turns requests from the
shop origin into session-authenticated, CSRF-protected requests, with no token table. No new npm
packages in `web/`.

### (g) Estimates and parallel build order

One agent builds the **shared foundation** first, because everything depends on it. After that, a
backend agent and a frontend agent work in parallel against the contract in (b).

| Phase | Backend agent (`api/`) | Frontend agent (`web/`) | Admin UI agent (`api/resources/js`) (or the backend agent after B3) |
|---|---|---|---|
| **0. Foundation** (~15m, backend agent, blocking) | users migration (+ `is_admin`), `enquiries.user_id`, `User` + factory, `SESSION_DRIVER=file`, APP_KEY persistence, Sanctum + `statefulApi()`, CORS credentials, exception split, env vars | Starts right away on (e) against MSW only, using the contract in (b) | – |
| **1** (~20m) | B1: `/auth/*` endpoints + tests; enquiry `user_id` | F1: `client.ts` CSRF/credentials, types, `AuthContext`, handlers | A1: kit port + Fortify admin login + `admin:create` + Docker Node/build (§16 steps 1–3) |
| **2** (~20m) | B2: `CatalogueService` admin methods: stats, enquiries, `resendEnquiry` + tests | F2: Login/Register pages, header menu, enquiry pre-fill + tests | A2: dashboard + enquiries pages + `assertInertia` tests |
| **3** (~10m) | B3: run-checks, docs/DECISIONS | F3: run-checks, manual check against the real API (CSRF/cookies) | A3: re-send button + toast |
| **Integration** (~10m) | Fresh `docker compose down -v && up --build`: shop register → login → enquiry pre-filled → Mailpit; admin login → sees the enquiry with the account → re-send | | |

**Totals:** shop auth is about **25m backend + 30m frontend**. The admin MVP is about **65–75m**
(§16). Run serially, everything takes about **2h10m**. With three agents in parallel after the
foundation, it takes about **75–85 minutes of wall-clock time**. Cut line if time is short: drop
re-send (A3/B2 part), then registration (seed a demo customer via `admin:create --customer`
instead).
