<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

final class DatabaseSeeder extends Seeder
{
    /**
     * The catalogue is seeded by the import (Fake Store, or the snapshot as fallback), then the demo
     * data when SEED_DEMO_DATA=true (never in production).
     */
    public function run(): void
    {
        Artisan::call('catalogue:import', [], $this->command?->getOutput());

        if (config('demo.seed_on_start') && ! app()->isProduction()) {
            $this->call(DemoSeeder::class);
        }
    }
}
