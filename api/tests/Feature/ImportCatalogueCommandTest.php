<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class ImportCatalogueCommandTest extends TestCase
{
    use RefreshDatabase;

    private const UPSTREAM = 'fakestoreapi.com/*';

    protected function setUp(): void
    {
        parent::setUp();

        // Keep retries instant in tests.
        config(['catalogue.source_retries' => 0]);
    }

    private function fakeUpstream(?string $body = null, int $status = 200): void
    {
        Http::fake([self::UPSTREAM => Http::response($body ?? $this->snapshotJson(), $status)]);
    }

    private function useMissingSnapshot(): void
    {
        config(['catalogue.snapshot_path' => storage_path('framework/testing/missing-snapshot.json')]);
    }

    public function test_imports_products_and_categories_from_fake_store(): void
    {
        $this->fakeUpstream();

        $this->artisan('catalogue:import')
            ->expectsOutputToContain('20 products, 4 categories imported from fakestore')
            ->assertExitCode(0);

        $this->assertDatabaseCount('products', 20);
        $this->assertDatabaseCount('categories', 4);
        $this->assertDatabaseHas('categories', ['slug' => 'mens-clothing', 'name' => "men's clothing"]);
        $this->assertDatabaseHas('products', [
            'source' => 'fakestore',
            'external_id' => '1',
            'price' => 109.95,
            'rating_rate' => 3.9,
            'rating_count' => 120,
            'category_id' => Category::query()->where('slug', 'mens-clothing')->value('id'),
        ]);
        Http::assertSent(fn ($request): bool => $request->url() === 'https://fakestoreapi.com/products');
    }

    public function test_second_run_updates_changed_rows_without_duplicates(): void
    {
        $items = json_decode($this->snapshotJson(), true);
        $items[0]['price'] = 99.5;
        $items[0]['rating'] = ['rate' => 4.4, 'count' => 130];
        // First call returns the snapshot, the second the changed data.
        Http::fake([self::UPSTREAM => Http::sequence()
            ->push($this->snapshotJson())
            ->push(json_encode($items))]);

        $this->artisan('catalogue:import')->assertExitCode(0);
        $originalId = Product::query()->where('external_id', '1')->value('id');

        $this->artisan('catalogue:import')->assertExitCode(0);

        $this->assertDatabaseCount('products', 20);
        $this->assertDatabaseCount('categories', 4);
        $this->assertDatabaseHas('products', [
            'id' => $originalId, 'external_id' => '1', 'price' => 99.5, 'rating_rate' => 4.4, 'rating_count' => 130,
        ]);
    }

    public function test_if_empty_skips_when_products_exist(): void
    {
        $this->fakeUpstream();
        Product::factory()->create();

        $this->artisan('catalogue:import', ['--if-empty' => true])
            ->expectsOutputToContain('skipping')
            ->assertExitCode(0);

        $this->assertDatabaseCount('products', 1);
        Http::assertNothingSent();
    }

    public function test_if_empty_imports_into_an_empty_database(): void
    {
        $this->fakeUpstream();

        $this->artisan('catalogue:import', ['--if-empty' => true])->assertExitCode(0);

        $this->assertDatabaseCount('products', 20);
    }

    public function test_upstream_500_falls_back_to_snapshot(): void
    {
        $this->fakeUpstream('oops', 500);

        $this->artisan('catalogue:import')
            ->expectsOutputToContain('imported from snapshot (fakestore unavailable)')
            ->assertExitCode(0);

        $this->assertDatabaseCount('products', 20);
    }

    public function test_upstream_timeout_falls_back_to_snapshot(): void
    {
        Http::fake([self::UPSTREAM => Http::failedConnection()]);

        $this->artisan('catalogue:import')
            ->expectsOutputToContain('from snapshot')
            ->assertExitCode(0);

        $this->assertDatabaseCount('products', 20);
    }

    public function test_upstream_invalid_json_falls_back_to_snapshot(): void
    {
        $this->fakeUpstream('<html>not json</html>');

        $this->artisan('catalogue:import')
            ->expectsOutputToContain('from snapshot')
            ->assertExitCode(0);

        $this->assertDatabaseCount('products', 20);
    }

    public function test_file_source_via_config_makes_no_http_request(): void
    {
        Http::fake();
        config(['catalogue.source' => 'file']);

        $this->artisan('catalogue:import')
            ->expectsOutputToContain('20 products, 4 categories imported from snapshot')
            ->assertExitCode(0);

        $this->assertDatabaseCount('products', 20);
        Http::assertNothingSent();
    }

    public function test_file_source_via_option_makes_no_http_request(): void
    {
        Http::fake();

        $this->artisan('catalogue:import', ['--source' => 'file'])->assertExitCode(0);

        $this->assertDatabaseCount('products', 20);
        Http::assertNothingSent();
    }

    public function test_unknown_source_option_fails(): void
    {
        Http::fake();

        $this->artisan('catalogue:import', ['--source' => 'ftp'])
            ->expectsOutputToContain('Unknown product source')
            ->assertExitCode(1);

        $this->assertDatabaseCount('products', 0);
    }

    public function test_snapshot_then_live_import_updates_the_same_rows(): void
    {
        $this->fakeUpstream();
        $this->artisan('catalogue:import', ['--source' => 'file'])->assertExitCode(0);
        $idsBefore = Product::query()->orderBy('id')->pluck('id', 'external_id')->all();

        $this->artisan('catalogue:import')->expectsOutputToContain('from fakestore')->assertExitCode(0);

        $this->assertDatabaseCount('products', 20);
        $this->assertSame($idsBefore, Product::query()->orderBy('id')->pluck('id', 'external_id')->all());
    }

    public function test_fallback_disabled_and_upstream_down_exits_1_and_leaves_db_unchanged(): void
    {
        $existing = Product::factory()->create(['price' => 12.34]);
        config(['catalogue.fallback_to_file' => false]);
        Http::fake([self::UPSTREAM => Http::failedConnection()]);

        $this->artisan('catalogue:import')
            ->expectsOutputToContain('Catalogue import failed')
            ->assertExitCode(1);

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseHas('products', ['id' => $existing->id, 'price' => 12.34]);
    }

    public function test_upstream_down_and_snapshot_missing_exits_1_and_leaves_db_unchanged(): void
    {
        $existing = Product::factory()->create(['price' => 12.34]);
        $this->useMissingSnapshot();
        $this->fakeUpstream('down', 503);

        $this->artisan('catalogue:import')->assertExitCode(1);

        $this->assertDatabaseCount('products', 1);
        $this->assertDatabaseCount('categories', 1);
        $this->assertDatabaseHas('products', ['id' => $existing->id, 'price' => 12.34]);
    }

    public function test_upstream_down_and_snapshot_invalid_exits_1_and_leaves_db_unchanged(): void
    {
        $path = storage_path('framework/testing/invalid-snapshot.json');
        @mkdir(dirname($path), 0777, true);
        file_put_contents($path, '[{"id": 1, "title": "half a product"}]');
        config(['catalogue.snapshot_path' => $path]);
        $this->fakeUpstream('down', 503);

        try {
            $this->artisan('catalogue:import')->assertExitCode(1);
        } finally {
            @unlink($path);
        }

        $this->assertDatabaseCount('products', 0);
        $this->assertDatabaseCount('categories', 0);
    }

    public function test_a_write_failure_mid_import_rolls_back_everything(): void
    {
        // Second item has a category but an over-long title for varchar(255): the products upsert fails
        // after categories were upserted, so the transaction must undo the categories too.
        $items = json_decode($this->snapshotJson(), true);
        $items[1]['title'] = str_repeat('x', 300);
        $this->fakeUpstream(json_encode($items));

        $this->artisan('catalogue:import')
            ->expectsOutputToContain('nothing was changed')
            ->assertExitCode(1);

        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('products', 0);
    }
}
