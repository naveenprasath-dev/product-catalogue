---
name: laravel-testing
description: Write or fix PHPUnit tests in api/ - feature tests for endpoints and artisan commands, unit tests for mappers/sources/services, using RefreshDatabase on catalogue_test, factories, Http::fake, Mail::fake, throttle and JSON shape assertions. Use whenever adding or debugging a backend test.
---

# Laravel tests

The required cases are listed per file in PLAN.md §7 — implement those first.

## Setup
- `phpunit.xml` forces the test DB: `<env name="DB_DATABASE" value="catalogue_test" force="true"/>`,
  plus `MAIL_MAILER=array`, `CACHE_STORE=array`, `CATALOGUE_SOURCE=fakestore`.
- Base `tests/TestCase.php` calls `Http::preventStrayRequests();` in `setUp()` so no test can hit the network.
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
// Mail failure → bind a mailer that throws, or Mail::shouldReceive('to->send')->andThrow(new TransportException('down'));

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
