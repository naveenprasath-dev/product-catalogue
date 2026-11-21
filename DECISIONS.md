# Decisions & Assumptions

## Stack choice
- **Client: React + TypeScript (Vite).**
  - **Quickest for a reviewer to run:** a web app needs only a browser, no simulator or device
    (PLAN.md §1), and the Vite dev server runs in the same `docker compose up`.
  - **TypeScript is preferred by the brief,** and strict types at the API boundary (`api/types.ts`
    mirrors the PLAN.md contract) catch contract drift at compile time.
  - **Look and feel will change:** plain CSS variables (`tokens.css`) keep a rebrand to one file,
    with no CSS framework to fight.
  - **Small surface:** React Router is the only runtime dependency beyond React; `fetch` is wrapped
    in one `client.ts`.
- **Backend: Laravel (PHP 8.4), used as a JSON API (plus the `/admin` panel added in phase 2).**

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
    | API auth / admin | Sanctum, Fortify | Yes (phase 2: shop login + `/admin`) |
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
| Category "top rated" | Top 3 by `rating.rate` within the category; the rest are "additional" | The brief gives no rule, and the data has a rating per product; 3 gives a clear "top" section in categories of 4–6 items. The count is one config value (`top_rated_per_category`) |
| Search | Case-insensitive substring over title, description and category, done server side | Simple and predictable for 20 products; server side so the client stays thin and a real search engine (Scout) can replace it later without changing the client |
| Enquiry form | Name + email required; phone + message optional | Sales needs a name and a way to reply; anything more adds friction. The product is attached automatically |
| Sales inbox | `SALES_INBOX_ADDRESS` env var; Mailpit locally | No paid accounts allowed |
| Email failure | Show an error and allow a retry; no "Thank you" | Don't tell the customer it worked if it didn't |
| Database | PostgreSQL 16 in Docker, storing the catalogue and enquiries (`status`: pending/sent/failed) | No enquiry is lost if email fails; same engine as a likely RDS production setup |
| Product data | `php artisan catalogue:import` copies Fake Store into Postgres; runs automatically on first launch (`--if-empty`); idempotent upsert by `(source, external_id)` | The API stays fast and works when Fake Store is down; a new data source is a new `ProductSource`, not a rewrite |
| Fake Store down on first launch | Fall back to a committed JSON snapshot (`database/data/fakestore-products.json`); `CATALOGUE_SOURCE=file` forces it | The reviewer's `docker compose up` works even if Fake Store is down or there's no network; shows the swappable `ProductSource` in real use |
| Every source fails | api container exits with a clear error; compose restarts it | Better than starting with an empty shop and no explanation |
| Runtime | Everything containerised (web, api, db, mailpit) via `docker compose up --build` | Reviewer needs only Docker; no local PHP/Node/Postgres versions to match |

## Dropped or simplified
- **Customer confirmation email**: only the sales inbox is emailed.
- **Cart, checkout and shop pagination**: 20 products don't need them; the brief is enquiry-based.
- **Scheduled re-sync**: the import runs on first start or by hand (`catalogue:import`).
- **Products that vanish upstream** are kept, not removed or hidden (enquiries reference them).
- **Images** stay hot-linked to fakestoreapi.com, so the offline snapshot shows no images.
- **Mail is synchronous** (no queue); a failure is stored as `failed` and the client shows a retry.
- **Auth is minimal** (phase 2): no password reset UI or email verification; admin passwords are
  reset with `php artisan admin:create --email=… --reset`. No 2FA, passkeys or admin registration.
- **Admin panel scope**: dashboard, enquiries list/detail and re-send only. No product editing, no
  import trigger, featured products still come from `FEATURED_PRODUCT_IDS` (ADMIN_PLAN §9).
- **Runtime**: `php -S` and the Vite dev server, file sessions and cache; fine locally, not for production.

## How the design grows beyond the PoC
- **New product source** (supplier API, PIM, CSV): a new `ProductSource` implementation bound in
  `CatalogueServiceProvider`; the importer, API and client don't change.
- **New email provider**: `MAIL_MAILER` (e.g. `ses`); content stays in `EnquiryReceived`.
- **Rebrand**: edit `web/src/styles/tokens.css` (shop) or `api/resources/css/app.css` (admin).
- **Scale**: the read model is already Postgres behind `ProductRepository`, so the API never depends
  on upstream at request time; queue mail (`ShouldQueue`, database then SQS), schedule the import,
  swap search to Scout, run on ECS/RDS/S3 + CloudFront (README → Production path).
- **Admin features**: each is a `CatalogueService` use case behind a thin Admin controller
  (`admin-page` skill). Planned next: import trigger, featured products in the DB, read-only product
  browse; later, product editing through a `product_overrides` table so imports never overwrite edits.
- **Customer features**: "My enquiries" (`enquiries.user_id` already exists), password reset via
  Fortify + Mail, email verification. Roles: a `role` enum replaces `is_admin` if more than two
  kinds of user appear.

## Next, with another day
- Run `catalogue:import` on a schedule (Laravel scheduler / ECS scheduled task) instead of only at start.
- Mark products that vanish upstream as inactive (`is_active`) instead of keeping them visible.
- Download product images during import (local/S3 storage) so the offline snapshot shows images too.
- Queue the enquiry email (`ShouldQueue` + the `database` queue driver), so a slow SMTP server
  doesn't slow the request.
- Admin: trigger `catalogue:import` from the panel (through `CatalogueService`, queued), and move
  featured products into a `featured_products` table (ADMIN_PLAN §9).
- Shop: a "My enquiries" page and password reset (ADMIN_PLAN §19d).
- Vitest + RTL tests for the admin pages (today they are covered by `assertInertia` feature tests,
  `tsc` and `vp check`).

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
  `run-checks`, `pr-ready`, `ai-log`, `dev-issues`, `git-workflow` (added when the repo went public: `main` holds no
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
| MCP server (`boost:mcp`) | root `.mcp.json`, run **inside the api container** (`docker compose exec -T api php artisan boost:mcp`); `api/.mcp.json` is Boost's in-container variant | Lets the agent read the real DB schema, routes, config, logs and search version-matched Laravel docs |

**Kept (user decision), `laravel/boost` 2.10.4:**

| Decision | Why | Boundaries |
|---|---|---|
| Keep Laravel Boost as a dev dependency | Fits the AI-native brief, and the agent is more accurate on the installed Laravel APIs (version-matched docs and guidelines instead of model memory) | The root CLAUDE.md rules take precedence over Boost's generic guidelines (project overrides live in `api/.ai/guidelines/`). It is `require-dev`, so never installed in production, and it registers its MCP server and routes (e.g. `/_boost/browser-logs`) only in a local environment (or with `APP_DEBUG=true`, which stays `false` here). Generated files (`api/CLAUDE.md`, `api/.claude/skills/`, `api/boost.json`) are not hand-edited |


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
| Enquiry pre-fill | Name/email are filled from the signed-in user once per user (also when the session resolves after the form mounted), never overwriting typed values, and stay editable. Logging out (or switching user) clears only the values the pre-fill inserted; anything typed or edited is kept (QA B6). No user id is sent; the API takes it from the session | ADMIN_PLAN §19d |
| Logout failure | A failed logout (non-401) keeps the user signed in and shows an inline alert in the header; a 401 counts as already logged out | Don't show a guest header while the server session still exists |
| Auth tests | MSW defaults: `me` → 401, `csrf-cookie` → 204 that sets `document.cookie` (MSW doesn't write cookies under Node), `signedIn()` helper; `XSRF-TOKEN` cleared after each test. `credentials: 'include'` is checked with a pass-through `vi.spyOn(fetch)` because MSW's intercepted `Request` drops `credentials` | Existing page tests stay unchanged and guest-only |
| UI redesign | Modern e-commerce look on the existing CSS-variables approach: tokens.css holds a neutral palette, one indigo accent, success/danger/badge/star colours, type scale, radii, shadows and motion tokens; light + dark (`prefers-color-scheme`) with AA text contrast; system font stack. No CSS framework, font or icon dependency | Stays within "theme tokens only, no new deps"; a rebrand is still one file |
| Icons | Small inline SVG components in `components/icons/` (search, user, star, chevron, arrow, bag), `aria-hidden`, `currentColor` | Six icons don't justify an icon library |
| Product card | The title link is stretched over the card with `::after`, so the whole card is clickable while the link's accessible name stays the title; the card shows the focus ring via `:focus-within`. Images sit in a square box on `--color-media-bg` with `object-fit: contain` + `mix-blend-mode: multiply` so white-background photos blend in | Avoids a link wrapping the whole card (a long, noisy accessible name) |
| "Top rated" badge | Shown on featured products (home) and on a category's `top_rated` section, never on "More products" or search results | Only where the API says a product is top rated; no invented labels |
| Star rating | Five SVG stars with a clipped overlay for partial stars; the group is `role="img"` with the label "Rated 4.7 out of 5 from 500 reviews"; the visible number stays as well | Rating is not conveyed by colour alone |
| Categories request | `Layout` loads categories once and shares them with the header nav, home tiles and footer (`<Outlet context>` + `useCategories()`) | One request instead of three |
| Skeletons | Grids and the product page show skeleton cards while loading; the wrapper keeps `role="status"` with a visually hidden label, the shapes are `aria-hidden` | Modern feel without losing the accessible loading message |
| Home page h1 | "Discover our top rated picks" (hero), sections "Shop by category" and "Featured products" | The page is still the top-rated landing page from PLAN.md |
| Product page | Breadcrumb Home › Category › Product; the category link lives only in the breadcrumb. The "Enquire about this product" CTA jumps to the form card (`#enquiry`, heading "Ask our sales team"). The image column is sticky from 900px | Enquiry stays on the product page (earlier decision) |
| Mobile header | At ≤ 480px the brand shows only the logo icon, and the account area is one 44px user-icon link (guest) or the avatar + Log out (signed in). The wordmark, "Log in" and "Hi, name" become visually hidden, so they remain the accessible names. Register is hidden; "Create an account" stays in the footer and on /login. These rules sit at the end of global.css so they win the cascade. Checked in Playwright at 320/375/414px with bounding-box overlap checks (brand vs account vs search: no overlap, one row, no horizontal overflow) | The first version's `display: none` lost to `.button` later in the file, and the wordmark overlapped the account area |
| Reduced motion | `prefers-reduced-motion` sets the motion tokens to 0 and turns off animations/transitions (shimmer, lift, smooth scroll) | Accessibility |
| Footer | Real links only: Home, Search, the categories from the API, Log in/Create an account (or "Signed in as …") | No invented policies, shipping or trust claims |
| Vite file watching | `server.watch.usePolling` (300ms) in `vite.config.ts` | `web/` is bind-mounted into Docker; on macOS, host file events don't reach the container, so the dev server kept serving stale modules |
| Currency selection | `CurrencyProvider` (`src/currency/`) loads `GET /currencies` once; selected = saved preference if still enabled, else the `is_default` one. The code is saved in `localStorage` (`catalogue.currency`) | A display preference, not auth data, so the "no localStorage" rule (which is about tokens/user data) doesn't apply. Every storage access is in try/catch; blocked storage just means the default each visit |
| Currency in product requests | Product pages use `useProductApi`, which adds the currency to the request and to the deps (switching re-fetches). `?currency=` is sent only for a non-default selection; the default is "omitted" (same result per the contract), so learning the default never causes a second request | No double fetch on first load |
| No flash of the wrong currency | With no saved preference, products load in parallel with `/currencies` (server default is right). With a saved one, product requests wait for `/currencies` to confirm it (`useApi` `enabled` option). On a switch, grids show skeletons; the product page keeps its content (and any half-typed enquiry) and only shows a price placeholder | A price is never shown in a currency the user didn't pick |
| Currency failures | `/currencies` fails or is empty → dropdown hidden, no `?currency=`, prices in the server default (saved preference kept for next visit). A product request answered 422 with `errors.currency` (disabled meanwhile) → selection reset to the default, saved value dropped, list reloaded, products re-fetched; no error shown | The shop keeps working; the API is the source of truth for enabled currencies |
| Price formatting | `formatPrice(amount, product.currency)` with `Intl.NumberFormat('en-US', { style: 'currency' })`, formatters cached per code; a malformed code falls back to "12.34 XYZ". The client never converts; it formats the code the API returned with the price | Fixed locale keeps output predictable (and tests stable); the price and its code always come from the same response |
| Currency dropdown | Native `<select>` with a visually hidden "Currency" label, options "€ EUR" (full name as `title`), in a `.header__actions` wrapper with the account menu (header right area). At ≤ 480px it stays on the same row with a 44px touch height (rule in the small-screen block at the end of global.css) | Native select is accessible and keyboard friendly without a dependency |

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
| API-only skeleton | Removed the default users/cache/jobs migrations, `User` model, web routes, Vite/npm files and the storage-serving route; routes are registered with `api:` only (no `install:api`, so no Sanctum) | Nothing in the first PoC needed auth, DB cache/sessions or queues. Phase 2 added `users`, Sanctum, Fortify and `routes/web.php` back for shop login and `/admin` (rows below) |
| Env files | Single root `.env.example`; `api/.env.example` removed. Compose passes every var to the api container explicitly, with defaults, so `docker compose up` also works without a `.env` | One place for config; CLAUDE.md "every env var into .env.example" |
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
| Phase 2 packages | `laravel/sanctum ^4.0` (4.5.0), `laravel/fortify ^1.37.2` (1.43.0, pulls in `laravel/passkeys` 0.3.0, unused), `inertiajs/inertia-laravel ^3.0` (3.7.0), `laravel/wayfinder ^0.1.14` (0.1.23), with constraints matching the starter kit's composer.json, locked by composer.lock | Admin panel (kit port) + shop cookie auth; installed in one step so both agents built against the same lock |
| Users | One `users` table (`is_admin` default false, **not fillable**), no `password_reset_tokens`/`sessions` tables. `enquiries.user_id` nullable FK, `nullOnDelete`, indexed | Customers and admins differ only by a flag; reset is CLI-only; file sessions |
| APP_KEY | Entrypoint: an env `APP_KEY` wins; otherwise read/generate `storage/app/.app-key` once (gitignored, in `.dockerignore`) | Sessions and remember cookies are encrypted, so the key must survive restarts. `docker compose exec api php artisan …` doesn't see the exported key (tests have their own) |
| Sessions | `SESSION_DRIVER=file`, path `/`, no domain (host-only `localhost` cookie), `lax`; phpunit keeps `array` | Shared by `/admin` and the shop (user decision 9: one session) |
| Stateful API | `$middleware->statefulApi()` + `SANCTUM_STATEFUL_DOMAINS=localhost:5173`. CORS `supports_credentials: true`, paths `api/*` + `sanctum/csrf-cookie`, origin still only `CORS_ALLOWED_ORIGIN` | Only the shop origin gets a session + CSRF; curl/other origins stay stateless and cookie-free (`StatelessApiTest`) |
| Cookie auth only | `Sanctum::getAccessTokenFromRequestUsing(fn () => null)` | No `personal_access_tokens` table; a stray `Authorization: Bearer` header must give 401, not a SQL error |
| Errors | JSON only when `api/*` or `expectsJson()`; the JSON 404 renderer follows the same rule; `/admin` gets Laravel's normal (debug-off) pages. `EnquiryNotFound` maps to a 404 | Admin is a web app; API behaviour is unchanged (`ErrorResponsesTest`) |
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
| Admin toolchain | TS `^5.7.2` (resolved 5.9.3) + `vp check` (kit)
 in `api/`, separate from web's TS 6.0 + ESLint. `vp fmt` ignores Markdown/JSON/PHP-owned paths so it only formats the admin front-end | Kept apart on purpose (ADMIN_PLAN §17) |
| Node in the api image | `node:22.22.0-bookworm-slim` binaries copied into the api image; `npm ci` in the image and the entrypoint; the entrypoint rebuilds `public/build` only when it is missing or older than `resources/js`, `resources/css`, `routes/web.php`, `package-lock.json` or `vite.config.ts` | Wayfinder's Vite plugin runs `php artisan wayfinder:generate`, so PHP and Node must share a container |
| `.npmrc` `ignore-scripts=true` | Kept from the kit | No install scripts run from dependencies |
| `admin:create` | `--from-env` (entrypoint, idempotent, never resets), `--email=x` (hidden prompt), `--email=x --reset`; min 12 characters; password never printed or logged | User decisions 3 and 7 |
| CSRF 419 test | Laravel skips CSRF verification while running tests, so `StatefulCsrfTest` swaps Sanctum's `validate_csrf_token` middleware for a subclass that always enforces it, and asserts the 419 / accepted cases for shop-origin requests | Tests the real Sanctum stateful path instead of trusting it |

## Currency decisions
| Area | Decision | Why |
|---|---|---|
| Base currency | USD, fixed in `config/currency.php` (not an env var); rate always 1; can't be disabled or deleted | Fake Store prices are USD. Changing the base would mean re-pricing every product |
| Storage | `currencies` table: `code` (ISO 4217, primary key), `name`, `symbol`, `rate decimal(18,8)` (units per 1 USD), `enabled`, `is_default`, `rate_updated_at`, timestamps. The migration inserts USD (default), EUR, GBP and INR with `insertOrIgnore` | A fresh `docker compose up` (which runs `migrate`) always has currencies; no separate seeder to remember |
| Seeded rates | EUR 0.91003, GBP 0.76131, INR 92.93 (Frankfurter, 2026-11-21); `rate_updated_at` null | Sensible offline values. The entrypoint replaces them with live rates on first start (`currencies:sync-rates --if-never-updated`, not fatal) |
| Live rates | Frankfurter **v2** (`https://api.frankfurter.dev/v2/rates?base=USD`, `CURRENCY_RATES_URL`). v1 (`/v1/latest`) now sends a `Deprecation` header pointing to v2, and `api.frankfurter.app` redirects. All quotes are fetched, not `quotes=…`, because v2 rejects the whole request (422) if one code is unknown | Open source (MIT), no key, ECB data (user decision). An admin-added currency Frankfurter doesn't know must not break the sync for the others |
| One caller | `FrankfurterRateSource` (behind `CurrencyRateSource`) is the only class that calls Frankfurter. Only `RateSyncer` uses it, from `currencies:sync-rates` and the admin "Sync live rates" button. Shop requests read stored rates only | Mirrors the Fake Store rule: no request-time upstream calls, and the source is swappable |
| Sync failure | Network error, timeout, HTTP error, invalid JSON, a non-list body, a wrong `base`, or a non-numeric or ≤ 0 rate all raise `RatesUnavailable` before any write. The command exits 1 and the admin sees an error toast; no rate changes | Same "all or nothing" rule as the import |
| What a sync updates | Enabled non-base currencies present in the response, in one transaction. Disabled currencies keep their rate. Enabled codes with no live rate are reported (command output, warning toast) and kept | The admin decides what is shown; missing data never zeroes a rate |
| `rates_updated_at` | `max(rate_updated_at)`: set by a sync and by an admin edit that changes the rate (renaming doesn't). Null while all rates are the seeded starting values | One honest "prices last re-rated" time for the shop, with no extra settings table. `--if-never-updated` uses it, so a restart never overwrites admin edits |
| Conversion | `CurrencyConverter::fromBase()` is the only conversion: `round(round(usd × rate, 8), 2, HALF_UP)`. Pre-rounding to 8 decimals removes float noise (1.005 is stored as 1.00499…). The exact product of a 2-dp price and an 8-dp rate has ≤ 10 decimals. The api image has no bcmath, and adding an extension needs an image rebuild | Exact half-up results for every realistic price × rate (unit-tested edge cases); no new dependency |
| Selecting the currency | `ProductQueryRequest` (a FormRequest on all 4 product routes; `SearchProductsRequest` extends it) validates `?currency=` (3 letters, case-insensitive; unknown, disabled or malformed → 422 on `currency`). It puts the currency into `SelectedCurrency`, a **scoped** (per-request) binding that `ProductResource` reads. Absent → the default currency | Controllers stay one-liners, and `CatalogueService`/`Product` DTOs stay in USD. `CategoryListingResource` reuses `ProductResource` and gets conversion for free |
| Default currency | Exactly one, which must be enabled. "Make default" clears the old default in the same transaction. Disabling or deleting the default is rejected (make another the default first). A new currency is never the default | The shop must always have a valid fallback currency |
| Currency use cases | `App\Catalogue\Currency\CurrencyService` holds the rules, not `CatalogueService`. Rule violations raise `CurrencyChangeRejected` (field + message): the admin controller turns them into form field errors (create/edit) or error toasts (default/delete). An unknown code raises `CurrencyNotFound` → 404 | Keeps `CatalogueService` about products/enquiries (like `AuthService`); one place for the rules, tested through the admin endpoints |
| Admin UI | `/admin/currencies`: list (default first), add, edit (code immutable; base rate read-only), make default, delete (confirm; hidden for base and default), "Sync live rates" (throttled 6/min; the other writes 30/min). The page shows "Rates last updated" and the per-row update time. Logs record code + admin id only | Admin-page skill pattern; a sync calls a third party, hence the tighter limit |
| Deleting currencies | Allowed for non-base, non-default currencies; nothing references them (enquiries store no currency). A shopper whose saved choice disappears gets a 422 and should fall back to the default | Simple; the web client handles the fallback |
| Enquiries / email | Unchanged: the sales email shows the stored USD price | Enquiries are about the product, not a quote; multi-currency quotes are a later feature |

### Admin QA fixes (see docs/qa/ADMIN_QA.md "Fixes")
| Area | Decision | Why |
|---|---|---|
| Login timing (B1) | One `Hash::check` per attempt; non-admins are checked against a cached dummy hash (`admin-login-dummy-hash:{rounds}`) | Making the dummy hash per request would add a second hash's time and leak the other way |
| Login throttle message (B2) | Kept `throttle:login` (5/min per email+IP) and render its exception for `login.store` as an `email` field error with the seconds left | Same limiter as before; the admin sees an inline message instead of Inertia's error modal |
| Back after logout (B3) | Inertia `EncryptHistory` middleware on all admin routes (not the global `INERTIA_ENCRYPT_HISTORY`, so no new env var), `Inertia::clearHistory()` in a custom Fortify `LogoutResponse`, `Cache-Control: no-store` | Encrypted history can't be read after the session's key is gone; no-store keeps it out of the HTTP cache |
| Signed-in user at `/admin/login` (B4) | Admin → `/admin`; **customer → the login form** (not a 403) | Shop and admin share one session: staff signed in to the shop as a customer can switch to their admin account without logging out of the shop first. Admin pages still 403 for customers |
| Invalid list filters (B5) | Ignored (all statuses, page 1), always 200 | Stale bookmarks and hand-edited URLs should still show the list |
| Security headers | `AdminSecurityHeaders` on `/admin` only, added to the middleware priority list before `auth` | Redirects and 403s must carry them too; the API keeps its own (stateless) headers |
| Toast duration | 6s (was Sonner's 4s) | Slow actions like "Sync live rates" finished before the admin looked back at the corner |
