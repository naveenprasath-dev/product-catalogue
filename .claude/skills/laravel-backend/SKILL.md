---
name: laravel-backend
description: General Laravel + PHP 8.4 conventions for any code in api/ - layering, service container bindings, readonly DTOs, config/env, exception rendering, logging, mail, rate limiting, CORS, Pint. Use whenever writing or reviewing PHP in api/ (pair with api-endpoint, product-source or laravel-database for specific tasks).
---

# Laravel backend conventions

CLAUDE.md has the rules; this skill has the house style for applying them. Task-specific skills:
`api-endpoint` (routes/controllers), `product-source` (import), `laravel-database` (schema),
`laravel-testing` (tests).

## Layering (request → response)
```
Route → FormRequest → thin Controller → CatalogueService → ProductRepository (contract) → Eloquent
                                                         ↘ Mail (EnquiryReceived)
Controller ← Resource ← Product DTO
```
- Controllers never touch Eloquent, `Http`, `Mail` or `config()` directly; they call the service.
- `CatalogueService` depends on **contracts** (`ProductRepository`), injected by constructor.
- Models stay inside repositories and the importer. Everything above returns `Product` DTOs.

## Currency layer (`app/Catalogue/Currency/`)
```
?currency=EUR → ProductQueryRequest (validates; unknown/disabled/malformed → 422 on `currency`)
             → SelectedCurrency::use()   (scoped binding: one per request; unset → the default currency)
ProductResource → CurrencyConverter::fromBase($usdPrice, SelectedCurrency::current())
/api/v1/currencies, /admin/currencies → CurrencyService → CurrencyRepository → Eloquent
currencies:sync-rates, admin sync → CurrencyService::syncRates() → RateSyncer → CurrencyRateSource
```
- **Conversion happens only in `CurrencyConverter::fromBase()`** (`round(round(usd × rate, 8), 2, HALF_UP)`).
  `CatalogueService`, the `Product` DTO and the repositories stay in USD (the base, `config('currency.base')`).
  Never convert in a controller, a repository, another resource or the client.
- New product routes use a FormRequest that extends `ProductQueryRequest`, so `?currency=` works and
  `SelectedCurrency` is set before the resource runs. `SelectedCurrency` must stay `scoped()`, never
  `singleton()` (it would leak one request's currency into the next under Octane/queues).
- Currency rules (base fixed at rate 1 and never disabled/deleted; exactly one default, which is
  enabled) live in `CurrencyService`, not `CatalogueService`. A rule violation throws
  `CurrencyChangeRejected` (field + message); unknown code → `CurrencyNotFound` (404).
- Live rates: `FrankfurterRateSource` is the only class that calls Frankfurter, only via `RateSyncer`.
  Any failure → `RatesUnavailable` **before** any write; the sync updates rates in one transaction.
  Shop requests read stored rates only.

## PHP style
- Every file starts with `declare(strict_types=1);`.
- `final` classes by default; `readonly` for DTOs and services with only injected deps.
- Constructor property promotion; typed params and returns everywhere (`: void`, `: ?Product`,
  `@return list<Product>` in PHPDoc for array shapes).
- Backed enums for fixed sets (e.g. `EnquiryStatus: string { Pending = 'pending'; Sent = 'sent'; Failed = 'failed'; }`),
  cast on the model.
- No facades inside domain classes (`app/Catalogue/**`) when an injectable contract exists
  (`Illuminate\Contracts\Mail\Mailer`, `Psr\Log\LoggerInterface`, `Illuminate\Http\Client\Factory`).
  Facades are fine in controllers, commands, providers and tests.

## Container bindings
- All catalogue and currency bindings live in `App\Providers\CatalogueServiceProvider` (registered in
  `bootstrap/providers.php`). Use `$this->app->bind()` / `singleton()` / `scoped()` with closures that read
  `config('catalogue.*')` / `config('currency.*')` — never `env()` outside `config/*.php` (config caching breaks it).
- Swapping an implementation = changing a binding, not editing callers.

## Config and env
- New setting → `config/catalogue.php` key with an `env('X', default)` → `.env.example` →
  TECH_STACK.md §8. Read it with `config('catalogue.x')`.
- Lists from env (e.g. `FEATURED_PRODUCT_IDS`) are parsed in the config file:
  `array_values(array_filter(array_map('trim', explode(',', env('FEATURED_PRODUCT_IDS', '')))))`.

## Errors (bootstrap/app.php → `withExceptions`)
- Errors are JSON **only** for `api/*` or `expectsJson()`:
  `$exceptions->shouldRenderJsonWhen(fn ($request) => $request->is('api/*') || $request->expectsJson());`
  `/admin` is a web app and gets Laravel's normal (debug-off) error pages.
- `ModelNotFoundException` / `NotFoundHttpException` → `{ "message": "Not found." }` 404 (same JSON rule);
  `EnquiryNotFound` is mapped to a 404.
- Domain exceptions (`CatalogueUnavailable`, `RatesUnavailable`, mail failures) are caught in the service/command, not leaked.
  Email failure → service marks the enquiry `failed` and throws `EnquiryNotSent`, which the global
  renderer turns into 502 `{ "message": … }` **as JSON, for every request**.
- So **admin controllers must catch domain exceptions themselves** (`EnquiryNotSent`,
  `EnquiryNotResendable`, `CurrencyChangeRejected`, `RatesUnavailable`) and flash a toast or field error +
  redirect back (see `Admin\EnquiryController::resend`, `Admin\CurrencyController`); otherwise the admin
  gets a raw 502 JSON body.
- `ThrottleRequestsException` on `login.store` is rendered as an `email` field error ("Too many
  attempts. Try again in N seconds.") instead of Inertia's raw 429 modal (QA B2).
- `APP_DEBUG=false` means no trace in bodies; never return `$e->getMessage()` from third-party exceptions.

## Logging
- `Log::warning()` / injected `LoggerInterface` with context arrays, e.g.
  `$logger->warning('Fake Store unavailable, using snapshot', ['reason' => $e->getMessage()])`.
- Never log full enquiry contents (personal data); log the enquiry id.

## Mail
- One Mailable per message (`EnquiryReceived`), Blade view with `{{ }}` (escaped) — never `{!! !!}` for user input.
- Recipient from `config('catalogue.sales_inbox')`. Provider only via `MAIL_MAILER`.
- Send synchronously for the PoC; wrap in try/catch for `Symfony\Component\Mailer\Exception\TransportExceptionInterface`
  (and `Throwable` as a last resort) to mark the enquiry `failed`.

## HTTP edges
- API routes in `routes/api.php` under `Route::prefix('v1')`; admin routes in `routes/web.php`
  (only registered when `config('admin.enabled')`).
- Sessions: `bootstrap/app.php` calls `$middleware->statefulApi()`, so Sanctum gives requests from
  `SANCTUM_STATEFUL_DOMAINS` (the shop) a session + CSRF on `/api`; everyone else stays stateless.
  Cookie auth only (`Sanctum::getAccessTokenFromRequestUsing(fn () => null)`): bearer tokens are ignored.
- Rate limit: define `RateLimiter::for('enquiries', …)` in `AppServiceProvider::boot()`, apply `throttle:enquiries`.
- `/admin` middleware: `AdminSecurityHeaders` (no framing, `no-store`) + Inertia `EncryptHistory` on
  every admin route; see the `admin-page` skill.
- CORS (`config/cors.php`): `allowed_origins` from `env('CORS_ALLOWED_ORIGIN')`, paths `api/*` +
  `sanctum/csrf-cookie`, `supports_credentials: true`.

- Responses: API Resources only. Cast decimals to numbers in the Resource (`(float) $this->price`),
  because `decimal:2` casts return strings.

## Commands
- Artisan commands return `self::SUCCESS` / `self::FAILURE`; print a one-line summary; no stack traces.
  `catalogue:import` and `currencies:sync-rates` exit 1 and change nothing when their upstream fails.

## Done when
`/run-checks` passes (tests + `./vendor/bin/pint --test`).
