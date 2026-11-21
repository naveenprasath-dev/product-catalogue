# Decisions & Assumptions

## Stack choice
- **Client: React + TypeScript (Vite).** _TODO: final reasoning_
- **Backend: Laravel (PHP 8.4), used as an API only.**
  - **Fast to build.** Routing, validation, ORM, migrations, mail, HTTP client, rate limiting, CORS,
    console commands and testing helpers all come with the framework. In a 2–3 hour time-box, the
    time goes into the catalogue logic instead of wiring libraries together.
  - **Many first-party packages, maintained by the Laravel team,** cover the next steps without
    picking third-party libraries:
    | Need | Built in / first-party | Used in the PoC? |
    |---|---|---|
    | Database + seeding | Eloquent, migrations, factories, seeders | Yes |
    | Call Fake Store | HTTP client (`Http::retry/timeout`, `Http::fake`) | Yes |
    | Validation | FormRequest | Yes |
    | Our own JSON shape | API Resources | Yes |
    | Email, provider swappable | Mail + Mailables (SMTP, SES, Postmark, Resend drivers) | Yes (SMTP → Mailpit) |
    | Rate limiting | `throttle` middleware / RateLimiter | Yes |
    | Import command | Artisan console | Yes |
    | Tests | PHPUnit + `RefreshDatabase`, `Http::fake`, `Mail::fake` | Yes |
    | Code style | Pint | Yes |
    | Background mail | Queues (database → SQS driver) | Next step |
    | Scheduled re-sync | Task scheduler | Next step |
    | API auth / admin | Sanctum | Later |
    | Better search | Scout (database driver → Meilisearch/Typesense) | Later |
    | Production speed | Octane | Later |
    | Debugging / monitoring | Telescope, Pulse | Later |
  - **Swap points the brief asks for come with the framework.** The service container binds
    `ProductSource`/`ProductRepository`, Mail drivers switch the email provider through `MAIL_MAILER`,
    and the DB layer switches the engine through `DB_CONNECTION`. The "will change" items are
    config and bindings, not rewrites.
  - **Testing support built in.** HTTP fakes and mail fakes make it easy to test every endpoint
    without touching the network.
  - **Trade-off:** the frontend is TypeScript and the backend PHP, so the two can't share types.
    That's acceptable because the API contract (`ProductResource`) is small and is checked by tests on
    both sides. A Node backend was considered first (see AI_LOG.md); Laravel was chosen for the
    points above.

## Assumptions (where the acceptance criteria were unclear)
| Area | Assumption | Why |
|---|---|---|
| Home "top rated" | The owner curates 5 product IDs in config (`FEATURED_PRODUCT_IDS`); they are not computed | "I have chosen" implies a manual choice |
| Category "top rated" | Top 3 by `rating.rate` within the category; the rest are "additional" | _TODO_ |
| Search | Case-insensitive substring over title, description and category, done server side | _TODO_ |
| Enquiry form | Name + email required; phone + message optional | _TODO_ |
| Sales inbox | `SALES_INBOX_ADDRESS` env var; Mailpit locally | No paid accounts allowed |
| Email failure | Show an error and allow a retry; no "Thank you" | Don't tell the customer it worked if it didn't |
| Database | PostgreSQL 16 in Docker, storing the catalogue and enquiries (`status`: pending/sent/failed) | No enquiry is lost if email fails; same engine as a likely RDS production setup |
| Product data | `php artisan catalogue:import` copies Fake Store into Postgres; runs automatically on first launch (`--if-empty`); idempotent upsert by `(source, external_id)` | The API stays fast and works when Fake Store is down; a new data source is a new `ProductSource`, not a rewrite |
| Fake Store down on first launch | Fall back to a committed JSON snapshot (`database/data/fakestore-products.json`); `CATALOGUE_SOURCE=file` forces it | The reviewer's `docker compose up` works even if Fake Store is down or there's no network; shows the swappable `ProductSource` in real use |
| Every source fails | api container exits with a clear error; compose restarts it | Better than starting with an empty shop and no explanation |
| Runtime | Everything containerised (web, api, db, mailpit) via `docker compose up --build` | Reviewer needs only Docker; no local PHP/Node/Postgres versions to match |

## Dropped or simplified
- _TODO_

## How the design grows beyond the PoC
- _TODO_

## Next, with another day
- Run `catalogue:import` on a schedule (Laravel scheduler / ECS scheduled task) instead of only at start.
- Mark products that vanish upstream as inactive (`is_active`) instead of keeping them visible.
- Download product images during import (local/S3 storage) so the offline snapshot shows images too.
- _TODO_

## Why I added Claude Code project skills (`.claude/skills/`)
CLAUDE.md says **what** must always be true (the rules). Skills say **how** to do a recurring task in
this repo, step by step, with a "done when" check. I split them this way so CLAUDE.md stays short and
is always loaded, while the longer how-to text is loaded only when the task needs it.

| Reason | What it gives me |
|---|---|
| **Same result every time** | Adding an endpoint, a product source or a page follows the same steps (test first → route → FormRequest → service → thin controller → Resource), so the code stays consistent as the project grows. |
| **Parallel agents stay aligned** | I build the backend and frontend with separate AI agents running at the same time. Shared skills, plus the API contract in PLAN.md, keep both sides to the same conventions without them talking to each other. |
| **Quality gates built in** | Every skill ends with "done when"; `run-checks` and `pr-ready` make tests, Pint, ESLint and `tsc` part of the workflow, not an afterthought (CLAUDE.md: don't mark work done until tests pass). |
| **The architecture rules are enforced where the work happens** | e.g. `product-source` repeats that only `FakeStoreProductSource` calls Fake Store; `web-page` repeats that only `client.ts` knows the API URL. |
| **Safe data operations** | `catalogue-data` asks before destructive commands (`down -v`, `migrate:fresh` delete enquiries). |
| **The AI workflow is part of the submission** | The brief is for an AI-native engineer; the skills are committed so a reviewer can see and reuse how I work with the AI, and `ai-log` feeds AI_USAGE.md. |

Two kinds of skill:
- **Task skills** (step-by-step): `api-endpoint`, `product-source`, `web-page`, `catalogue-data`,
  `run-checks`, `pr-ready`, `ai-log`, `git-workflow` (added when the repo went public: `main` holds no
  code, never stage blindly, secrets scan before every commit, ask before pushing).
- **Convention skills** (house style for any code on one side), added once building had started so
  both agents wrote code the same way: `laravel-backend`, `laravel-database`, `laravel-testing`,
  `react-frontend`, `react-testing`.

Trade-off: skills are documentation that can go stale. That already happened once: `react-testing`
used MSW v2's `onUnhandledRequest`, but the project resolved MSW 3, where it is `onUnhandledFrame` and
the old key is silently ignored. The frontend agent caught it and the skill was fixed. So a skill is
updated in the same change as the code it describes, and `pr-ready` checks docs are in sync.

## Laravel Boost (`laravel/boost`, dev dependency only)
Laravel's first-party AI tooling. Added so the agent writes Laravel 13 code that matches the
**installed** package versions, instead of relying on what the model remembers.

| What it adds | Where | Why it helps |
|---|---|---|
| Version-aware guidelines | `api/CLAUDE.md` (generated, loaded when working in `api/`) | Laravel/PHPUnit/Pint conventions for the versions in `composer.lock` |
| Skills | `api/.claude/skills/` (`laravel-best-practices`, `testing-best-practices`, `infer-conventions`) | Sit alongside our own skills; ours cover this project's architecture, Boost's cover general Laravel practice |
| MCP server (`boost:mcp`) | root `.mcp.json`, run **inside the api container** (`docker compose exec -T api php artisan boost:mcp`) | Lets the agent read the real DB schema, routes, config, logs and search version-matched Laravel docs |

Choices made during install:
- **Laravel Cloud turned off** (`boost.json`: `"cloud": false`, `deploying-to-cloud` skill removed).
  Boost's built-in "deployments" guideline promotes Laravel Cloud, a paid service. It's overridden by
  `api/.ai/guidelines/deployments/core.blade.php` with this project's rule: local Docker only, AWS as
  the documented production path.
- **MCP via Docker, not host PHP**, because the app's env (Postgres host `db`, `APP_ENV=local`) only
  exists in the container. Boost registers only in a local environment.
- Generated files (`api/CLAUDE.md`, `api/.claude/skills/`) are regenerated by
  `php artisan boost:install`; project-specific changes go in `api/.ai/guidelines/`, never in the
  generated files.
- Not used in production: it is in `require-dev`, and Boost only registers in a local environment.

## Frontend (`web/`) decisions
| Area | Decision | Why |
|---|---|---|
| Price currency | Prices are shown as USD via one `formatPrice` helper (`src/lib/format.ts`) | Fake Store has no currency field; one constant to change |
| Enquiry form placement | The form sits on the product detail page (`/products/:id`) and is replaced by the "Thank you" message on 201; no separate route | Product is attached automatically; fewer routes; a refresh can't re-submit |
| Search | Term lives in `?q=` (linkable, back button works); empty/whitespace term blocked client-side; `maxLength=100` mirrors the API limit | Server still validates (422) |
| Client validation | Name + email required, email format, length limits mirroring `StoreEnquiryRequest`; UX only, server 422 field errors shown under the matching field | CLAUDE.md: server is the source of truth |
| Failure messages | 502 / network → "could not send, try again" with values kept; 429 → "too many requests, wait"; 404 → in-page not-found | PLAN.md §6 email-failure assumption |
| Linting | ESLint 10 + typescript-eslint (strict) + react-hooks + react-refresh, `--max-warnings 0`. The current Vite template ships oxlint and TypeScript 7, but typescript-eslint supports TS < 6.1, so TypeScript is pinned to 6.0.x to keep ESLint as in TECH_STACK.md | Matches TECH_STACK/skills; revisit when typescript-eslint supports TS 7 |
| Single tsconfig | One `tsconfig.json` (app, tests, vite config) instead of the template's project references | So `npx tsc --noEmit` really type-checks everything (on a references-only root it checks nothing) |
| API base URL | `client.ts` falls back to `http://localhost:8000/api/v1` when `VITE_API_URL` is unset; `.env.test` sets it for Vitest | `npm run dev` works without extra setup; still only one module knows the URL |
| Shop auth (client) | `client.ts` sends `credentials: 'include'` on every request; before any POST/PUT/PATCH/DELETE it fetches `${API origin}/sanctum/csrf-cookie` only if no `XSRF-TOKEN` cookie exists (concurrent callers share one fetch), then sends the cookie value as `X-XSRF-TOKEN`. A 419 re-fetches the cookie and retries once | Sanctum SPA cookie auth (ADMIN_PLAN §19a). Reading the cookie each time picks up the token Laravel rotates on login/logout. The CSRF URL is derived from `VITE_API_URL`, so `client.ts` is still the only module with the URL |
| Session state | `AuthProvider` calls `me()` on start; 401 → guest (not an error); a network/5xx on `me` also falls back to guest. No tokens or user data in localStorage | The session is an HttpOnly cookie; the shop works for guests anyway |
| Login/register redirect | `?next=` is honoured only for same-app paths (`/…`, not `//…`, not `/login`/`/register`); header links carry the current page. Signed-in users visiting `/login`/`/register` are sent to `next` | Return to the product after logging in, without an open redirect |
| Enquiry pre-fill | Name/email are filled from the signed-in user once per user (also when the session resolves after the form mounted), never overwriting typed values, and stay editable. No user id is sent; the API takes it from the session | ADMIN_PLAN §19d |
| Logout failure | A failed logout (non-401) keeps the user signed in and shows an inline alert in the header; a 401 counts as already logged out | Don't show a guest header while the server session still exists |
| Auth tests | MSW defaults: `me` → 401, `csrf-cookie` → 204 that sets `document.cookie` (MSW doesn't write cookies under Node), `signedIn()` helper; `XSRF-TOKEN` cleared after each test. `credentials: 'include'` is checked with a pass-through `vi.spyOn(fetch)` because MSW's intercepted `Request` drops `credentials` | Existing page tests stay unchanged and guest-only |

Web dependencies (all pinned by `package-lock.json`): `react-router` (routing; the only runtime addition);
dev: `vitest` (test runner, shares Vite config), `jsdom` (DOM for tests), `@testing-library/react`,
`@testing-library/dom`, `@testing-library/user-event`, `@testing-library/jest-dom` (test the UI like a user
and readable DOM assertions), `msw` (mock the API at the network level so the real `client.ts` runs),
`eslint`, `@eslint/js`, `typescript-eslint`, `eslint-plugin-react-hooks`, `eslint-plugin-react-refresh`,
`globals` (linting).

## Backend implementation decisions (api/)
| Area | Decision | Why |
|---|---|---|
| Laravel version | Laravel 13 (latest stable at scaffold time), PHP `^8.4`; images pinned (`php:8.4.28-cli-bookworm`, `postgres:16.16-alpine`, `axllent/mailpit:v1.34.0`, `composer:2.11.2`) | Versions must not float |
| API-only skeleton | Removed the default users/cache/jobs migrations, `User` model, web routes, Vite/npm files and the storage-serving route; routes are registered with `api:` only (no `install:api`, so no Sanctum) | Nothing in the PoC needs auth, DB cache/sessions or queues; fewer moving parts and no extra dependency |
| Env files | Single root `.env.example`; `api/.env.example` removed. Compose passes every var to the api container explicitly, with defaults, so `docker compose up` also works without a `.env` | One place for config; CLAUDE.md "every env var into .env.example" |
| `APP_KEY` | Empty in `.env.example`; the api entrypoint generates an ephemeral key per start when unset | The API is stateless (no sessions/cookies/encryption), so nothing depends on key stability, and no key is committed |
| Default featured ids | `FEATURED_PRODUCT_IDS=12,11,3,18,5` = the five highest-rated items in the Fake Store data (4.8, 4.8, 4.7, 4.7, 4.6) | A sensible owner choice until they curate their own |
| Non-env tunables | Fake Store timeout (10s) and retries (2), `top_rated_per_category` (3) and the enquiry rate limit (5/min per IP) are plain values in `config/catalogue.php`, not env vars | Keeps the env surface to what TECH_STACK.md §8 lists; promote to env vars if ops need to change them |
| `ProductSource::origin()` | Sources report a label (`fakestore`, `snapshot`, `snapshot (fakestore unavailable)`) used in the import summary | The command can say where the data came from without knowing which classes are wired |
| Empty upstream list | Treated as `CatalogueUnavailable` (so the fallback is used) | An empty response must not look like a successful import |
| Import DB errors | A database error mid-import rolls back the transaction; the command prints a one-line message, logs the detail, exits 1 | No stack traces from the command; nothing half-written |
| Enquiry storage | Added a small `EnquiryRepository` contract (+ Eloquent impl) next to `ProductRepository` | Keeps Eloquent models out of `CatalogueService` (laravel-backend skill); not in PLAN.md §4's file list |
| Honeypot response | A filled `website` returns the normal 201 shape `{ "data": { "id": 0, "status": "sent" } }` but stores and sends nothing | Bots learn nothing; `id: 0` is never a real row id |
| Optional fields | Empty strings for `phone`, `message`, `website` count as "not given" (Laravel's ConvertEmptyStringsToNull + `nullable`) and are stored as NULL | The web client always sends `website=""` |
| Mail failure | Any exception while sending marks the enquiry `failed`, logs the enquiry id only (no personal data), returns 502 `{ "message": … }` | PLAN.md: record kept, UI shows retry |
| Product id route | `/products/{id}` only matches 1–18 digits; anything else is a 404 | Avoids bigint overflow errors reaching Postgres; non-numeric ids 404 without a trace |
| Search | `whereLike` with `%`/`_` escaped so they match literally; results ordered by title | Users searching "50%" should not match everything |
| Errors | Every response from the app renders as JSON (not just `api/*`): 404 → `{ "message": "Not found." }` | API-only app; no HTML error pages anywhere |
| Phase 2 packages | `laravel/sanctum ^4.0` (4.5.0), `laravel/fortify ^1.37.2` (1.43.0, pulls in `laravel/passkeys` 0.3.0, unused), `inertiajs/inertia-laravel ^3.0` (3.7.0), `laravel/wayfinder ^0.1.14` (0.1.23), with constraints matching the starter kit's composer.json, locked by composer.lock | Admin panel (kit port) + shop cookie auth; installed in one step so both agents built against the same lock |
| Users | One `users` table (`is_admin` default false, **not fillable**), no `password_reset_tokens`/`sessions` tables. `enquiries.user_id` nullable FK, `nullOnDelete`, indexed | Customers and admins differ only by a flag; reset is CLI-only; file sessions |
| APP_KEY (supersedes "ephemeral key") | Entrypoint: an env `APP_KEY` wins; otherwise read/generate `storage/app/.app-key` once (gitignored, in `.dockerignore`) | Sessions and remember cookies are encrypted, so the key must survive restarts. `docker compose exec api php artisan …` doesn't see the exported key (tests have their own) |
| Sessions | `SESSION_DRIVER=file`, path `/`, no domain (host-only `localhost` cookie), `lax`; phpunit keeps `array` | Shared by `/admin` and the shop (user decision 9: one session) |
| Stateful API | `$middleware->statefulApi()` + `SANCTUM_STATEFUL_DOMAINS=localhost:5173`. CORS `supports_credentials: true`, paths `api/*` + `sanctum/csrf-cookie`, origin still only `CORS_ALLOWED_ORIGIN` | Only the shop origin gets a session + CSRF; curl/other origins stay stateless and cookie-free (`StatelessApiTest`) |
| Cookie auth only | `Sanctum::getAccessTokenFromRequestUsing(fn () => null)` | No `personal_access_tokens` table; a stray `Authorization: Bearer` header must give 401, not a SQL error |
| Errors (supersedes "every response is JSON") | JSON only when `api/*` or `expectsJson()`; the JSON 404 renderer follows the same rule; `/admin` gets Laravel's normal (debug-off) pages. `EnquiryNotFound` maps to a 404 | Admin is a web app; API behaviour is unchanged (`ErrorResponsesTest`) |
| Shop auth endpoints | `/api/v1/auth/{register,login,logout,me}`; thin `Api\AuthController` + `App\Auth\AuthService` on the `web` guard; limits `shop-register` 3/min/IP, `shop-login` 5/min per email+IP; emails lower-cased | ADMIN_PLAN §19(b). Kept out of `CatalogueService` (auth isn't catalogue logic) |
| No `guest` middleware on register/login | Dropped | Laravel's `RedirectIfAuthenticated` answers a JSON client with a 302 to `/`; logging in again just re-authenticates |
| Register/login without a session → 403 | `authorize()` = `hasSession()` in the FormRequests | Cookie auth can't work without a session (non-shop origin); a clear 403 beats a 500 "session store not set" |
| Enquiry `user_id` | `StoreEnquiryRequest::toData()` takes it from `$request->user()`; body `user_id` is ignored | Never trust the body for identity |
| Admin use cases | In `CatalogueService` (`dashboardStats`, `enquiries` paginator of `StoredEnquiry` DTOs, `enquiry`, `resendEnquiry`); one private `deliver()` used by submit and re-send | One mail path through `EnquiryReceived`; repositories still hide Eloquent |
| Re-send rules | Allowed for `failed`, or `pending` older than 5 min (stuck); `sent` → `EnquiryNotResendable`; mail failure → stays `failed`, `EnquiryNotSent`; unknown id → `EnquiryNotFound` (404) | Never email the sales inbox twice for a sent enquiry |
| Tests run as `testing` | phpunit.xml now forces `APP_ENV=testing` (the container's `APP_ENV=local` used to win) | Laravel's test-only behaviour (e.g. CSRF bypass) depends on it; `StatefulCsrfTest` turns CSRF back on to test 419 |
| Product editing (later, user decision 6) | Upstream-owned fields stay read-only in admin; local edits go in a `product_overrides` table merged by `EloquentProductRepository` at read time, so `catalogue:import` never overwrites them and "revert to upstream" is a row delete | A "locally edited, skip on import" flag would silently stop price updates |

## Admin panel decisions (api/, /admin)
| Area | Decision | Why |
|---|---|---|
| Kit source | `laravel/react-starter-kit` @ `012f4248f70e255e32ad93f540aca5fb7e8c96fe` (2026-11-10), cloned outside the repo; only the login, sidebar layout and the shadcn components we use were copied, then trimmed | ADMIN_PLAN §5; pinning the commit makes the port reproducible |
| Removed from the kit | Registration, password reset, email verification, 2FA, passkeys, settings/appearance pages, welcome page, SSR, `HandleAppearance`, `@laravel/passkeys`, `input-otp`, `concurrently`, `@laravel/multiplex`, Radix collapsible/navigation-menu/toggle/select, the Bunny font | Admins only, password reset is CLI-only (user decision 7); no third-party font CDN |
| Auth routes | `Fortify::ignoreRoutes()`; `routes/web.php` registers only `/admin/login` (GET/POST, `throttle:login` 5/min per email+IP) and `/admin/logout` with Fortify's controller. A test asserts no register/reset/verify/2FA/passkey/`user/*` routes exist | Fortify otherwise also adds password-confirmation routes; explicit is easier to audit |
| Admin-only login | `Fortify::authenticateUsing` accepts only `is_admin` users; a customer gets the same "credentials do not match" error as a wrong password. `Gate::define('admin')` + `can:admin` on every admin route (a test walks the route table) | Shared users table (§19c); no hint whether an email is a customer |
| Logout target | `fortify.redirects.logout = /admin/login` | Fortify's default `/` is a 404 in this app |
| Shared props | `auth.user` is `{id, name, email}` only; enquiry props go through `Admin\EnquiryResource`, and the detail prop is `resolve()`d (no `data` wrapper) | No `is_admin`/hashes/raw columns reach the browser |
| Re-send result | Controller catches `EnquiryNotResendable` (warning toast) and `EnquiryNotSent` (error toast "Still failing, try again later") and redirects back; flash via `Inertia::flash('toast', …)` → kit's `useFlashToast` + sonner. Route throttled 6/min; logs enquiry id + admin id only | The global 502 JSON renderer is for the API; the admin sees a toast, not an error page |
| Theming | Kit shadcn tokens in `api/resources/css/app.css` plus `--success`/`--warning` for status badges; copied components had raw palette classes (`text-red-600`, `bg-neutral-200`, …) replaced by tokens. Follows the system light/dark setting | CLAUDE.md token rule; admin and shop styles stay separate |
| Admin toolchain | TS ~5.7 + `vp check` (kit) in `api/`, separate from web's TS 6.0 + ESLint. `vp fmt` ignores Markdown/JSON/PHP-owned paths so it only formats the admin front-end | Kept apart on purpose (ADMIN_PLAN §17) |
| Node in the api image | `node:22.22.0-bookworm-slim` binaries copied into the api image; `npm ci` in the image and the entrypoint; the entrypoint rebuilds `public/build` only when it is missing or older than `resources/js`, `resources/css`, `routes/web.php`, `package-lock.json` or `vite.config.ts` | Wayfinder's Vite plugin runs `php artisan wayfinder:generate`, so PHP and Node must share a container |
| `.npmrc` `ignore-scripts=true` | Kept from the kit | No install scripts run from dependencies |
| `admin:create` | `--from-env` (entrypoint, idempotent, never resets), `--email=x` (hidden prompt), `--email=x --reset`; min 12 characters; password never printed or logged | User decisions 3 and 7 |
| CSRF 419 test | Not covered by a feature test: Laravel skips CSRF verification while running unit tests | Covered by the framework's `web` group; noted rather than faked |
