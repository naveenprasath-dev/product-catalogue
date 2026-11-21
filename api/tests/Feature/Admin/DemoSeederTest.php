<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\User;
use Database\Seeders\DemoSeeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use RuntimeException;

/** DemoSeeder: admin + demo customer + "[demo]" enquiries, idempotent, no mail, never in production. */
final class DemoSeederTest extends AdminTestCase
{
    private const string ADMIN_EMAIL = 'admin@example.test';

    private const string ADMIN_PASSWORD = 'demo-admin-password';

    private const string CUSTOMER_EMAIL = 'customer@example.test';

    private const string CUSTOMER_PASSWORD = 'demo-customer-password';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        config([
            'admin.bootstrap' => ['name' => 'Admin', 'email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD],
            'demo.customer' => ['name' => 'Demo Customer', 'email' => self::CUSTOMER_EMAIL, 'password' => self::CUSTOMER_PASSWORD],
        ]);
    }

    public function test_it_creates_the_admin_the_customer_and_demo_enquiries_without_sending_mail(): void
    {
        Product::factory()->count(5)->create();

        $this->seed(DemoSeeder::class);

        $admin = User::query()->where('email', self::ADMIN_EMAIL)->sole();
        $customer = User::query()->where('email', self::CUSTOMER_EMAIL)->sole();
        $this->assertTrue($admin->is_admin);
        $this->assertFalse($customer->is_admin);
        $this->assertSame('Demo Customer', $customer->name);

        $this->assertSame(12, Enquiry::query()->count());
        $this->assertSame(12, Enquiry::query()->where('message', 'like', DemoSeeder::MARKER.'%')->count());
        foreach (EnquiryStatus::cases() as $status) {
            $this->assertTrue(Enquiry::query()->where('status', $status)->exists(), "no {$status->value} demo enquiry");
        }
        $this->assertTrue(Enquiry::query()->where('user_id', $customer->id)->exists());
        $this->assertTrue(Enquiry::query()->whereNull('user_id')->exists());
        // At least one pending enquiry is old enough to be re-sent, and one is not.
        $this->assertTrue(Enquiry::query()->where('status', EnquiryStatus::Pending)->where('created_at', '<', now()->subMinutes(5))->exists());
        $this->assertTrue(Enquiry::query()->where('status', EnquiryStatus::Pending)->where('created_at', '>', now()->subMinutes(5))->exists());

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_running_it_twice_creates_no_duplicates_and_keeps_existing_passwords(): void
    {
        Product::factory()->count(5)->create();

        $this->seed(DemoSeeder::class);
        User::query()->where('email', self::CUSTOMER_EMAIL)->sole()->forceFill(['password' => 'changed-by-the-user'])->save();
        $this->seed(DemoSeeder::class);

        $this->assertSame(2, User::query()->count());
        $this->assertSame(12, Enquiry::query()->count());
        $this->assertTrue(Hash::check('changed-by-the-user', User::query()->where('email', self::CUSTOMER_EMAIL)->sole()->password));
        Mail::assertNothingSent();
    }

    public function test_without_products_it_creates_the_accounts_but_skips_enquiries(): void
    {
        $this->artisan('db:seed', ['--class' => DemoSeeder::class])
            ->expectsOutputToContain('No products yet')
            ->assertExitCode(0);

        $this->assertSame(2, User::query()->count());
        $this->assertSame(0, Enquiry::query()->count());
    }

    public function test_it_refuses_to_run_in_production(): void
    {
        Product::factory()->count(5)->create();
        $this->app->detectEnvironment(fn (): string => 'production');

        try {
            $this->app->make(DemoSeeder::class)->__invoke();
            $this->fail('DemoSeeder ran in production.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('production', $e->getMessage());
        }

        $this->assertSame(0, User::query()->count());
        $this->assertSame(0, Enquiry::query()->count());
    }

    public function test_the_demo_customer_can_log_in_to_the_shop_but_not_the_admin(): void
    {
        Product::factory()->count(5)->create();
        $this->seed(DemoSeeder::class);

        $this->postJson('/api/v1/auth/login', ['email' => self::CUSTOMER_EMAIL, 'password' => self::CUSTOMER_PASSWORD], self::SHOP)
            ->assertOk()
            ->assertJsonPath('data.email', self::CUSTOMER_EMAIL);
        $this->get('/admin')->assertForbidden();
    }

    public function test_the_seeded_admin_can_log_in_and_see_the_demo_enquiries(): void
    {
        Product::factory()->count(5)->create();
        $this->seed(DemoSeeder::class);

        $this->post('/admin/login', ['email' => self::ADMIN_EMAIL, 'password' => self::ADMIN_PASSWORD])
            ->assertRedirect('/admin');
        $this->get('/admin/enquiries')->assertOk()->assertSee(DemoSeeder::MARKER, false);
    }
}
