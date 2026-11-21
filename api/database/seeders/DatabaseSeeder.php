<?php

declare(strict_types=1);

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

final class DatabaseSeeder extends Seeder
{
    /** The catalogue is seeded by the import (Fake Store, or the snapshot as fallback). */
    public function run(): void
    {
        Artisan::call('catalogue:import', [], $this->command?->getOutput());
    }
}
