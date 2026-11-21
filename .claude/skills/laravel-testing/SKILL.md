---
name: laravel-testing
description: Write or fix PHPUnit tests in api/ - feature tests for endpoints and artisan commands, unit tests for mappers/sources/services, using RefreshDatabase on catalogue_test, factories, Http::fake, Mail::fake, throttle and JSON shape assertions. Use whenever adding or debugging a backend test.
---

# Laravel tests

The required cases are listed per file in PLAN.md §7 — implement those first.

## Setup
- `phpunit.xml` forces the test env with **both** `<env … force="true"/>` and `<server … force="true"/>`:
  the api container already exports `APP_ENV=local`, `DB_*`, `ADMIN_ENABLED` etc., and Laravel reads
  `$_SERVER` first, so `<env>` alone would lose. Forced: `APP_ENV=testing` (Laravel's test-only
  behaviour, e.g. the CSRF bypass, depends on it), `ADMIN_ENABLED=true` (`routes/web.php` registers
  `/admin` only when on), `DB_DATABASE=catalogue_test`, `MAIL_MAILER=array`, `CACHE_STORE=array`,
  `APP_DEBUG=false`, the `CATALOGUE_*` settings. **`APP_KEY` is never in `phpunit.xml`**: `tests/bootstrap.php`
  generates a random one per run (a hard-coded key, even a test one, is flagged by secret scanners). `SESSION_DRIVER=array`.
  Add a new env-dependent setting the same way (env + server, force).
- Base `tests/TestCase.php` calls `Http::preventStrayRequests();` in `setUp()` so no test can hit the network.
  It also provides:
  - `self::SHOP`: `Origin`/`Referer` headers of the shop (`http://localhost:5173`). Pass them to make
    a request stateful (session + CSRF), e.g. `$this->postJson('/api/v1/auth/login', $body, self::SHOP)`.
    Without them the API is stateless; register/login then return 403.
  - `useFailingMailer()`: every email throws a transport error (SMTP down). Use instead of `Mail::fake()`
    to test the 502 / `failed` path.
  - `snapshotJson()`: the committed Fake Store snapshot as the upstream fixture.
- `UserFactory::PASSWORD` (`'password'`) is the plain password of factory users; `User::factory()->admin()`
  makes an admin. Admin tests extend `Tests\Feature\Admin\AdminTestCase` (`admin()`, `customer()`,
  `withoutVite()`, `refreshApplicationWithAdmin(bool)` to test the panel switched off).
- **CSRF is skipped in tests** (Laravel bypasses it while running unit tests). To test 419, do what
  `Auth/StatefulCsrfTest` does: in `setUp()` set `config(['sanctum.middleware.validate_csrf_token' =>
  EnforcedCsrf::class])`, a `ValidateCsrfToken` subclass that always enforces, and send `self::SHOP`.
- Feature tests: `use RefreshDatabase;`. Unit tests extend `PHPUnit\Framework\TestCase` unless they need the container.
- Name tests as behaviour: `public function test_unknown_slug_returns_404_json(): void`.

## Patterns
```php
// Endpoint + exact contract shape
$this->getJson('/api/v1/products/'.$product->id)
    ->assertOk()
    ->assertExactJsonStructure(['data' => ['id','title','description','price','image_url',
        'category' => ['slug','name'], 'rating' => ['rate','count']]])
    ->assertJsonPath('data.price', 109.95);          // number, not "109.95"

// Config-driven behaviour
config(['catalogue.featured_ids' => ['3','1']]);

// Upstream
Http::fake(['fakestoreapi.com/*' => Http::response(
    file_get_contents(database_path('data/fakestore-products.json')), 200)]);
Http::fake(['fakestoreapi.com/*' => Http::failedConnection()]);   // simulate outage
Http::assertNothingSent();                                          // --source=file

// Artisan
$this->artisan('catalogue:import')->expectsOutputToContain('from snapshot')->assertExitCode(0);

// Mail
Mail::fake();
Mail::assertSent(EnquiryReceived::class, fn ($m) => $m->hasTo(config('catalogue.sales_inbox')));
Mail::assertNothingSent();
// Mail failure → $this->useFailingMailer(); then assertStatus(502) + assertDatabaseHas('enquiries', ['status' => 'failed'])

// Auth (shop): log in as a factory user, or go through the endpoint with the shop headers
$this->actingAs(User::factory()->create())->getJson('/api/v1/auth/me')->assertOk();
$this->postJson('/api/v1/auth/login', ['email' => $user->email, 'password' => UserFactory::PASSWORD], self::SHOP);


// Escaping
$html = (new EnquiryReceived($enquiry))->render();
$this->assertStringContainsString('&lt;script&gt;', $html);

// Throttle: loop until the limit, then assertStatus(429)
```
- Assert DB state, not just responses: `assertDatabaseHas('enquiries', ['status' => 'sent'])`,
  `assertDatabaseCount('products', 20)`.
- Rollback tests: seed a row, make every source fail, assert exit code 1 and the original row unchanged.

## Rules
- Never delete or weaken a failing test to go green; fix the code or explain why the test is wrong.
- No sleeps, no real time — use `$this->travel()` / `Carbon::setTestNow()` when time matters.

## Run
```bash
docker compose exec api php artisan test --filter=StoreEnquiryTest
docker compose exec api php artisan test
```
