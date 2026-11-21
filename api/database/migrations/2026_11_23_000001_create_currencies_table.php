<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Currencies the shop can show prices in. `rate` = units of this currency per 1 unit of the base
 * currency (USD, the Fake Store price currency). The defaults are inserted here so a fresh
 * `docker compose up` (which runs `migrate`) always has them; insertOrIgnore keeps it idempotent.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('currencies', function (Blueprint $table): void {
            $table->string('code', 3)->primary();
            $table->string('name', 100);
            $table->string('symbol', 10);
            $table->decimal('rate', 18, 8);
            $table->boolean('enabled')->default(true);
            $table->boolean('is_default')->default(false);
            // When this rate last changed (live sync or a manual edit); null = the seeded starting value.
            $table->timestampTz('rate_updated_at')->nullable();
            $table->timestamps();
        });

        $now = now();
        // Starting rates (ECB via Frankfurter, 2026-11-21); `currencies:sync-rates` refreshes them.
        DB::table('currencies')->insertOrIgnore(array_map(
            fn (array $row): array => $row + ['enabled' => true, 'rate_updated_at' => null, 'created_at' => $now, 'updated_at' => $now],
            [
                ['code' => 'USD', 'name' => 'US Dollar', 'symbol' => '$', 'rate' => '1', 'is_default' => true],
                ['code' => 'EUR', 'name' => 'Euro', 'symbol' => '€', 'rate' => '0.91003', 'is_default' => false],
                ['code' => 'GBP', 'name' => 'British Pound', 'symbol' => '£', 'rate' => '0.76131', 'is_default' => false],
                ['code' => 'INR', 'name' => 'Indian Rupee', 'symbol' => '₹', 'rate' => '92.93', 'is_default' => false],
            ],
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('currencies');
    }
};
