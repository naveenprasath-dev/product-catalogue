<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Base for /admin tests: no Vite manifest needed, admin panel on, fresh catalogue_test DB. */
abstract class AdminTestCase extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    protected function admin(): User
    {
        return User::factory()->admin()->create();
    }

    protected function customer(): User
    {
        return User::factory()->create();
    }

    /**
     * routes/web.php registers the panel at boot only when ADMIN_ENABLED is true, so tests pin it
     * (instead of relying on the container env) and re-boot the app with the given value.
     */
    protected function refreshApplicationWithAdmin(bool $enabled): void
    {
        $value = $enabled ? 'true' : 'false';
        putenv("ADMIN_ENABLED={$value}");
        $_ENV['ADMIN_ENABLED'] = $_SERVER['ADMIN_ENABLED'] = $value;
        $this->refreshApplication();
        $this->withoutVite();
    }

    protected function refreshApplication(): void
    {
        if (getenv('ADMIN_ENABLED') === false) {
            putenv('ADMIN_ENABLED=true');
            $_ENV['ADMIN_ENABLED'] = $_SERVER['ADMIN_ENABLED'] = 'true';
        }

        parent::refreshApplication();
    }
}
