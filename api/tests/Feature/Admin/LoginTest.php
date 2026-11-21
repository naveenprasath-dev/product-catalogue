<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Database\Factories\UserFactory;
use Illuminate\Hashing\HashManager;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use PHPUnit\Framework\Attributes\DataProvider;

final class LoginTest extends AdminTestCase
{
    public function test_the_login_page_renders(): void
    {
        $this->get('/admin/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/login'));
    }

    public function test_an_admin_can_log_in_and_lands_on_the_dashboard(): void
    {
        $admin = $this->admin();

        $this->post('/admin/login', ['email' => $admin->email, 'password' => UserFactory::PASSWORD])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_a_wrong_password_is_rejected(): void
    {
        $admin = $this->admin();

        $this->from('/admin/login')
            ->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong-password'])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors('email');

        $this->assertGuest();
    }

    public function test_customer_credentials_are_rejected_with_the_same_generic_error(): void
    {
        $customer = $this->customer();

        $this->from('/admin/login')
            ->post('/admin/login', ['email' => $customer->email, 'password' => UserFactory::PASSWORD])
            ->assertRedirect('/admin/login')
            ->assertSessionHasErrors(['email' => __('auth.failed')]);

        $this->assertGuest();
    }

    public function test_the_sixth_attempt_in_a_minute_is_throttled(): void
    {
        $admin = $this->admin();

        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong-password'])->assertRedirect();
        }

        // A form error under the email field with the wait time, not a bare 429 (QA B2).
        $this->from('/admin/login')
            ->post('/admin/login', ['email' => $admin->email, 'password' => UserFactory::PASSWORD])
            ->assertRedirect('/admin/login')
            ->assertSessionHasInput('email', $admin->email)
            ->assertSessionHasErrors(['email' => 'Too many attempts. Try again in 60 seconds.']);
        $this->assertGuest();
    }

    public function test_the_throttle_message_counts_down(): void
    {
        $admin = $this->admin();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong-password']);
        }

        $this->travel(45)->seconds();

        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong-password'])
            ->assertSessionHasErrors(['email' => 'Too many attempts. Try again in 15 seconds.']);
    }

    /** @return array<string, array{0: string}> */
    public static function nonAdminLogins(): array
    {
        return ['unknown email' => ['unknown'], 'customer, right password' => ['customer'], 'admin, wrong password' => ['admin']];
    }

    /** Every failure runs exactly one password hash check, so timing doesn't reveal admin emails (QA B1). */
    #[DataProvider('nonAdminLogins')]
    public function test_every_failed_login_runs_one_hash_check(string $who): void
    {
        $email = match ($who) {
            'unknown' => 'nobody@example.test',
            'customer' => $this->customer()->email,
            'admin' => $this->admin()->email,
        };
        $password = $who === 'admin' ? 'wrong-password' : UserFactory::PASSWORD;

        $hasher = new class($this->app) extends HashManager
        {
            public int $checks = 0;

            public function check(#[\SensitiveParameter] $value, $hashedValue, array $options = []): bool
            {
                $this->checks++;

                return parent::check($value, $hashedValue, $options);
            }
        };
        Hash::swap($hasher);

        $this->from('/admin/login')
            ->post('/admin/login', ['email' => $email, 'password' => $password])
            ->assertSessionHasErrors(['email' => 'These credentials do not match our records.']);
        $this->assertGuest();
        $this->assertSame(1, $hasher->checks);
    }

    public function test_a_signed_in_admin_opening_the_login_page_goes_to_the_dashboard(): void
    {
        $this->actingAs($this->admin())->get('/admin/login')->assertRedirect('/admin');
    }

    /** DECISIONS.md: a signed-in shop customer sees the login form (to switch to an admin account), not a 404. */
    public function test_a_signed_in_customer_gets_the_login_form(): void
    {
        $this->actingAs($this->customer())
            ->get('/admin/login')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->component('auth/login'));
    }

    public function test_a_signed_in_customer_can_switch_to_an_admin_account(): void
    {
        $admin = $this->admin();

        $this->actingAs($this->customer())
            ->post('/admin/login', ['email' => $admin->email, 'password' => UserFactory::PASSWORD])
            ->assertRedirect('/admin');

        $this->assertAuthenticatedAs($admin);
    }

    public function test_logout_clears_the_inertia_history(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/logout')
            ->assertRedirect('/admin/login')
            ->assertSessionHas('inertia.clear_history', true);

        // The next page tells the Inertia client to wipe its (encrypted) history; only once.
        $this->assertTrue($this->get('/admin/login')->viewData('page')['clearHistory'] ?? false);
        $this->assertFalse($this->get('/admin/login')->viewData('page')['clearHistory'] ?? false);
    }

    public function test_an_admin_can_log_out(): void
    {
        $this->actingAs($this->admin())
            ->post('/admin/logout')
            ->assertRedirect('/admin/login');

        $this->assertGuest();
        $this->get('/admin')->assertRedirect('/admin/login');
    }
}
