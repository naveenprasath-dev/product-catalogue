<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/** currencies:sync-rates against a faked Frankfurter v2 (never the real network). */
final class SyncCurrencyRatesCommandTest extends TestCase
{
    use RefreshDatabase;

    public const string UPSTREAM = 'api.frankfurter.dev/v2/rates*';

    protected function setUp(): void
    {
        parent::setUp();

        // Keep retries instant in tests.
        config(['currency.rates_retries' => 0]);
    }

    /** Frankfurter v2 body: one entry per quote. */
    public static function frankfurterBody(array $rates, string $base = 'USD'): string
    {
        return (string) json_encode(array_map(
            fn (string $quote, float $rate): array => ['date' => '2026-11-21', 'base' => $base, 'quote' => $quote, 'rate' => $rate],
            array_keys($rates),
            array_values($rates),
        ));
    }

    /** @return array<string, string> code => stored rate */
    private function storedRates(): array
    {
        return Currency::query()->orderBy('code')->pluck('rate', 'code')->all();
    }

    public function test_updates_enabled_non_base_rates_from_frankfurter(): void
    {
        Currency::factory()->disabled()->create(['code' => 'XAA', 'rate' => 5]);
        Http::fake([self::UPSTREAM => Http::response(self::frankfurterBody(
            ['EUR' => 0.9, 'GBP' => 0.75, 'INR' => 93.123456789, 'XAA' => 7.0, 'JPY' => 155.2, 'AAVE' => 0.0102],
        ))]);

        $this->artisan('currencies:sync-rates')
            ->expectsOutputToContain('3 currency rates updated from Frankfurter.')
            ->assertExitCode(0);

        $this->assertSame(['EUR' => '0.90000000', 'GBP' => '0.75000000', 'INR' => '93.12345679', 'USD' => '1.00000000', 'XAA' => '5.00000000'], $this->storedRates());
        $this->assertNotNull(Currency::query()->findOrFail('EUR')->rate_updated_at);
        $this->assertNull(Currency::query()->findOrFail('USD')->rate_updated_at);
        $this->assertDatabaseMissing('currencies', ['code' => 'JPY']);
        Http::assertSent(fn (Request $request): bool => $request->url() === 'https://api.frankfurter.dev/v2/rates?base=USD');
    }

    public function test_reports_enabled_currencies_without_a_live_rate_and_keeps_them(): void
    {
        Currency::factory()->create(['code' => 'XAA', 'rate' => 5]);
        Http::fake([self::UPSTREAM => Http::response(self::frankfurterBody(['EUR' => 0.9, 'GBP' => 0.75, 'INR' => 93.0]))]);

        $this->artisan('currencies:sync-rates')
            ->expectsOutputToContain('No rate for: XAA.')
            ->assertExitCode(0);

        $this->assertSame('5.00000000', Currency::query()->findOrFail('XAA')->rate);
    }

    /** @return array<string, array{0: \Closure(): mixed}> */
    public static function failures(): array
    {
        return [
            'HTTP 500' => [fn () => Http::response('oops', 500)],
            'timeout / connection error' => [fn () => Http::failedConnection()],
            'invalid JSON' => [fn () => Http::response('{not json', 200)],
            'empty list' => [fn () => Http::response('[]', 200)],
            'v1 object shape' => [fn () => Http::response(['base' => 'USD', 'rates' => ['EUR' => 0.9]], 200)],
            'wrong base' => [fn () => Http::response(self::frankfurterBody(['EUR' => 0.9], 'EUR'), 200)],
            'non-numeric rate' => [fn () => Http::response('[{"date":"2026-11-21","base":"USD","quote":"EUR","rate":"0.9"}]', 200)],
            'zero rate' => [fn () => Http::response(self::frankfurterBody(['EUR' => 0.0]), 200)],
        ];
    }

    #[DataProvider('failures')]
    public function test_any_upstream_failure_exits_1_and_changes_nothing(\Closure $response): void
    {
        $before = $this->storedRates();
        Http::fake([self::UPSTREAM => $response()]);

        $this->artisan('currencies:sync-rates')
            ->expectsOutputToContain('Currency rates unavailable, nothing was changed')
            ->assertExitCode(1);

        $this->assertSame($before, $this->storedRates());
        $this->assertSame(0, Currency::query()->whereNotNull('rate_updated_at')->count());
    }

    public function test_if_never_updated_skips_once_a_rate_was_set(): void
    {
        Http::fake();
        Currency::query()->whereKey('EUR')->update(['rate_updated_at' => now()]);

        $this->artisan('currencies:sync-rates --if-never-updated')
            ->expectsOutputToContain('skipping')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_if_never_updated_syncs_the_seeded_rates(): void
    {
        Http::fake([self::UPSTREAM => Http::response(self::frankfurterBody(['EUR' => 0.95, 'GBP' => 0.8, 'INR' => 90.0]))]);

        $this->artisan('currencies:sync-rates --if-never-updated')->assertExitCode(0);

        $this->assertSame('0.95000000', Currency::query()->findOrFail('EUR')->rate);
    }

    public function test_uses_the_configured_rates_url(): void
    {
        config(['currency.rates_url' => 'https://rates.example.test/v2']);
        Http::fake(['rates.example.test/*' => Http::response(self::frankfurterBody(['EUR' => 0.9]))]);

        $this->artisan('currencies:sync-rates')->assertExitCode(0);

        Http::assertSent(fn (Request $request): bool => str_starts_with($request->url(), 'https://rates.example.test/v2/rates?base=USD'));
    }
}
