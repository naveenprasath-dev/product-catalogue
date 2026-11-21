<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Currency;
use App\Models\Product;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Feature\SyncCurrencyRatesCommandTest;

/** /admin/currencies: list, create, edit, default, delete, live sync. Seeded: USD (base, default), EUR, GBP, INR. */
final class CurrenciesTest extends AdminTestCase
{
    /** @var array<string, mixed> */
    private const array VALID = ['code' => 'chf', 'name' => 'Swiss Franc', 'symbol' => 'CHF', 'rate' => '0.8812', 'enabled' => 'on'];

    protected function setUp(): void
    {
        parent::setUp();

        config(['currency.rates_retries' => 0]);
    }

    private function asAdmin(): self
    {
        return $this->actingAs($this->admin());
    }

    /** @return array<string, mixed> the edit form for a seeded currency, with overrides */
    private function editForm(string $code, array $overrides = []): array
    {
        $c = Currency::query()->findOrFail($code);

        return [...['name' => $c->name, 'symbol' => $c->symbol, 'rate' => $c->rate, 'enabled' => 'on'], ...$overrides];
    }

    public function test_guests_are_redirected_and_customers_get_403(): void
    {
        $requests = [
            ['get', '/admin/currencies'], ['get', '/admin/currencies/create'], ['get', '/admin/currencies/EUR/edit'],
            ['post', '/admin/currencies'], ['put', '/admin/currencies/EUR'], ['post', '/admin/currencies/EUR/default'],
            ['delete', '/admin/currencies/EUR'], ['post', '/admin/currencies/sync'],
        ];
        Http::fake();

        foreach ($requests as [$method, $url]) {
            $this->{$method}($url, self::VALID)->assertRedirect('/admin/login');
        }

        $this->actingAs($this->customer());
        foreach ($requests as [$method, $url]) {
            $this->{$method}($url, self::VALID)->assertForbidden();
        }

        Http::assertNothingSent();
        $this->assertDatabaseCount('currencies', 4);
        $this->assertTrue(Currency::query()->findOrFail('USD')->is_default);
    }

    public function test_index_lists_currencies_default_first_with_base_and_last_update(): void
    {
        Currency::query()->whereKey('EUR')->update(['rate_updated_at' => '2026-11-21 09:30:00+00']);
        Currency::factory()->disabled()->create(['code' => 'XAA']);

        $this->asAdmin()->get('/admin/currencies')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('currencies/index')
                ->where('base', 'USD')
                ->where('rate_source', 'Frankfurter')
                ->where('rates_updated_at', '2026-11-21T09:30:00+00:00')
                ->has('currencies', 5)
                ->where('currencies.0', [
                    'code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'rate' => 1, 'enabled' => true,
                    'is_default' => true, 'is_base' => true, 'rate_updated_at' => null,
                ])
                ->where('currencies.1.code', 'EUR')
                ->where('currencies.4.code', 'XAA')
                ->where('currencies.4.enabled', false));
    }

    public function test_create_and_edit_pages_render(): void
    {
        $this->asAdmin()->get('/admin/currencies/create')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('currencies/create')->where('base', 'USD'));

        $this->get('/admin/currencies/EUR/edit')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('currencies/edit')
                ->where('currency.code', 'EUR')
                ->where('currency.is_base', false));

        $this->get('/admin/currencies/XYZ/edit')->assertNotFound();
        $this->get('/admin/currencies/eur/edit')->assertNotFound();
    }

    public function test_an_admin_adds_a_currency_and_the_shop_can_use_it(): void
    {
        $this->asAdmin()->post('/admin/currencies', self::VALID)
            ->assertRedirect('/admin/currencies')
            ->assertInertiaFlash('toast.type', 'success');

        $chf = Currency::query()->findOrFail('CHF');
        $this->assertSame(['Swiss Franc', 'CHF', '0.88120000', true, false],
            [$chf->name, $chf->symbol, $chf->rate, $chf->enabled, $chf->is_default]);
        $this->assertNotNull($chf->rate_updated_at);
        $codes = array_column($this->getJson('/api/v1/currencies')->json('data'), 'code');
        $this->assertSame(['USD', 'CHF', 'EUR', 'GBP', 'INR'], $codes);
    }

    public function test_an_unchecked_enabled_box_creates_a_disabled_currency(): void
    {
        $this->asAdmin()->post('/admin/currencies', [...self::VALID, 'enabled' => null])->assertRedirect('/admin/currencies');

        $this->assertFalse(Currency::query()->findOrFail('CHF')->enabled);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidCreates(): array
    {
        return [
            'code missing' => [['code' => ''], 'code'],
            'code too long' => [['code' => 'EURO'], 'code'],
            'code with digits' => [['code' => 'E1R'], 'code'],
            'code taken' => [['code' => 'eur'], 'code'],
            'name missing' => [['name' => ''], 'name'],
            'name too long' => [['name' => str_repeat('a', 101)], 'name'],
            'symbol missing' => [['symbol' => ''], 'symbol'],
            'symbol too long' => [['symbol' => str_repeat('$', 11)], 'symbol'],
            'rate missing' => [['rate' => ''], 'rate'],
            'rate zero' => [['rate' => '0'], 'rate'],
            'rate negative' => [['rate' => '-1'], 'rate'],
            'rate not a number' => [['rate' => 'abc'], 'rate'],
            'rate too many decimals' => [['rate' => '0.123456789'], 'rate'],
            'rate too large' => [['rate' => '1000001'], 'rate'],
        ];
    }

    /** @param array<string, mixed> $override */
    #[DataProvider('invalidCreates')]
    public function test_create_validation(array $override, string $field): void
    {
        $this->asAdmin()->from('/admin/currencies/create')
            ->post('/admin/currencies', [...self::VALID, ...$override])
            ->assertRedirect('/admin/currencies/create')
            ->assertSessionHasErrors($field);

        $this->assertDatabaseCount('currencies', 4);
    }

    public function test_an_admin_edits_a_rate_and_the_shop_price_follows(): void
    {
        $product = Product::factory()->create(['price' => 10.0]);

        $this->asAdmin()->put('/admin/currencies/EUR', $this->editForm('EUR', ['name' => 'Euro (EU)', 'rate' => '0.5']))
            ->assertRedirect('/admin/currencies')
            ->assertInertiaFlash('toast.type', 'success');

        $eur = Currency::query()->findOrFail('EUR');
        $this->assertSame(['Euro (EU)', '0.50000000'], [$eur->name, $eur->rate]);
        $this->assertNotNull($eur->rate_updated_at);
        $this->getJson("/api/v1/products/{$product->id}?currency=EUR")->assertJsonPath('data.price', 5);
    }

    public function test_editing_only_the_name_does_not_touch_rate_updated_at(): void
    {
        $this->asAdmin()->put('/admin/currencies/GBP', $this->editForm('GBP', ['name' => 'Pound sterling']))
            ->assertRedirect('/admin/currencies');

        $this->assertNull(Currency::query()->findOrFail('GBP')->rate_updated_at);
    }

    public function test_disabling_a_currency_hides_it_from_the_shop(): void
    {
        $this->asAdmin()->put('/admin/currencies/INR', $this->editForm('INR', ['enabled' => null]))
            ->assertRedirect('/admin/currencies');

        $this->assertFalse(Currency::query()->findOrFail('INR')->enabled);
        $this->assertNotContains('INR', array_column($this->getJson('/api/v1/currencies')->json('data'), 'code'));
    }

    public function test_the_base_currency_rate_is_fixed_and_it_cannot_be_disabled(): void
    {
        $this->asAdmin()->from('/admin/currencies/USD/edit')
            ->put('/admin/currencies/USD', $this->editForm('USD', ['rate' => '1.1']))
            ->assertRedirect('/admin/currencies/USD/edit')
            ->assertSessionHasErrors('rate');

        // USD is also the default here; first make EUR the default so only the base rule applies.
        $this->post('/admin/currencies/EUR/default');
        $this->from('/admin/currencies/USD/edit')
            ->put('/admin/currencies/USD', $this->editForm('USD', ['enabled' => null]))
            ->assertSessionHasErrors(['enabled' => 'The base currency (USD) can\'t be disabled.']);

        $usd = Currency::query()->findOrFail('USD');
        $this->assertSame(['1.00000000', true], [$usd->rate, $usd->enabled]);

        // Saving the base with rate 1 still works (e.g. renaming it).
        $this->put('/admin/currencies/USD', $this->editForm('USD', ['name' => 'U.S. dollar']))->assertSessionHasNoErrors();
        $this->assertSame('U.S. dollar', Currency::query()->findOrFail('USD')->name);
    }

    public function test_the_default_currency_cannot_be_disabled(): void
    {
        $this->asAdmin()->post('/admin/currencies/GBP/default');

        $this->from('/admin/currencies/GBP/edit')
            ->put('/admin/currencies/GBP', $this->editForm('GBP', ['enabled' => null]))
            ->assertSessionHasErrors('enabled');

        $this->assertTrue(Currency::query()->findOrFail('GBP')->enabled);
    }

    public function test_make_default_leaves_exactly_one_default(): void
    {
        $this->asAdmin()->from('/admin/currencies')
            ->post('/admin/currencies/EUR/default')
            ->assertRedirect('/admin/currencies')
            ->assertInertiaFlash('toast.type', 'success');

        $this->assertSame(['EUR'], Currency::query()->where('is_default', true)->pluck('code')->all());
        $this->getJson('/api/v1/currencies')->assertJsonPath('data.0.code', 'EUR');
    }

    public function test_a_disabled_currency_cannot_become_the_default(): void
    {
        Currency::query()->whereKey('INR')->update(['enabled' => false]);

        $this->asAdmin()->from('/admin/currencies')
            ->post('/admin/currencies/INR/default')
            ->assertRedirect('/admin/currencies')
            ->assertInertiaFlash('toast.type', 'error');

        $this->assertSame(['USD'], Currency::query()->where('is_default', true)->pluck('code')->all());
    }

    public function test_delete_rules(): void
    {
        $this->asAdmin();

        $this->delete('/admin/currencies/USD')->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'The base currency (USD) can\'t be deleted.']);
        $this->post('/admin/currencies/GBP/default');
        $this->delete('/admin/currencies/GBP')->assertInertiaFlash('toast.type', 'error');
        $this->assertDatabaseHas('currencies', ['code' => 'USD']);
        $this->assertDatabaseHas('currencies', ['code' => 'GBP']);

        $this->delete('/admin/currencies/INR')->assertRedirect('/admin/currencies')->assertInertiaFlash('toast.type', 'success');
        $this->assertDatabaseMissing('currencies', ['code' => 'INR']);

        $this->delete('/admin/currencies/XYZ')->assertNotFound();
    }

    public function test_sync_live_rates_updates_rates_and_shows_the_count(): void
    {
        Http::fake([SyncCurrencyRatesCommandTest::UPSTREAM => Http::response(
            SyncCurrencyRatesCommandTest::frankfurterBody(['EUR' => 0.9, 'GBP' => 0.75, 'INR' => 93.0]),
        )]);

        $this->asAdmin()->from('/admin/currencies')
            ->post('/admin/currencies/sync')
            ->assertRedirect('/admin/currencies')
            ->assertInertiaFlash('toast', ['type' => 'success', 'message' => '3 rates updated from Frankfurter.']);

        $this->assertSame('0.90000000', Currency::query()->findOrFail('EUR')->rate);
    }

    public function test_sync_warns_about_currencies_without_a_live_rate(): void
    {
        Currency::factory()->create(['code' => 'XAA']);
        Http::fake([SyncCurrencyRatesCommandTest::UPSTREAM => Http::response(
            SyncCurrencyRatesCommandTest::frankfurterBody(['EUR' => 0.9, 'GBP' => 0.75, 'INR' => 93.0]),
        )]);

        $this->asAdmin()->post('/admin/currencies/sync')
            ->assertInertiaFlash('toast', ['type' => 'warning', 'message' => '3 rates updated from Frankfurter. No live rate for XAA (kept as is).']);
    }

    /** @return array<string, array{0: \Closure(): mixed}> */
    public static function upstreamFailures(): array
    {
        return [
            'HTTP 500' => [fn () => Http::response('oops', 500)],
            'timeout' => [fn () => Http::failedConnection()],
            'bad JSON' => [fn () => Http::response('<html>', 200)],
        ];
    }

    #[DataProvider('upstreamFailures')]
    public function test_sync_failure_shows_an_error_toast_and_changes_nothing(\Closure $response): void
    {
        $before = Currency::query()->orderBy('code')->pluck('rate', 'code')->all();
        Http::fake([SyncCurrencyRatesCommandTest::UPSTREAM => $response()]);

        $this->asAdmin()->from('/admin/currencies')
            ->post('/admin/currencies/sync')
            ->assertRedirect('/admin/currencies')
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Live rates unavailable; rates unchanged. Try again later.']);

        $this->assertSame($before, Currency::query()->orderBy('code')->pluck('rate', 'code')->all());
    }

    public function test_sync_is_throttled(): void
    {
        Http::fake([SyncCurrencyRatesCommandTest::UPSTREAM => Http::response(
            SyncCurrencyRatesCommandTest::frankfurterBody(['EUR' => 0.9]),
        )]);
        $this->asAdmin();

        for ($i = 0; $i < 6; $i++) {
            $this->post('/admin/currencies/sync')->assertRedirect();
        }

        $this->post('/admin/currencies/sync')->assertStatus(429);
    }
}
