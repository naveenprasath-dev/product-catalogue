<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use Database\Factories\UserFactory;
use Inertia\Testing\AssertableInertia as Assert;

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

        $this->post('/admin/login', ['email' => $admin->email, 'password' => UserFactory::PASSWORD])
            ->assertStatus(429);
        $this->assertGuest();
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
