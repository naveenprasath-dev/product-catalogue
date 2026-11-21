<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Enquiry;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;

final class AdminAccessTest extends AdminTestCase
{
    protected function tearDown(): void
    {
        // Leave the panel enabled for the next test.
        putenv('ADMIN_ENABLED=true');
        $_ENV['ADMIN_ENABLED'] = $_SERVER['ADMIN_ENABLED'] = 'true';

        parent::tearDown();
    }

    /** @return list<string> */
    private function adminPages(int $enquiryId): array
    {
        return ['/admin', '/admin/enquiries', "/admin/enquiries/{$enquiryId}"];
    }

    public function test_guests_are_redirected_to_the_login_page(): void
    {
        $enquiry = Enquiry::factory()->failed()->create();

        foreach ($this->adminPages($enquiry->id) as $url) {
            $this->get($url)->assertRedirect('/admin/login');
        }
        $this->post("/admin/enquiries/{$enquiry->id}/resend")->assertRedirect('/admin/login');
    }

    public function test_logged_in_customers_get_403_everywhere(): void
    {
        $enquiry = Enquiry::factory()->failed()->create();
        $this->actingAs($this->customer());

        foreach ($this->adminPages($enquiry->id) as $url) {
            $this->get($url)->assertForbidden();
        }
        $this->post("/admin/enquiries/{$enquiry->id}/resend")->assertForbidden();
        $this->assertSame('failed', $enquiry->fresh()?->status->value);
    }

    public function test_admins_can_open_every_page(): void
    {
        $enquiry = Enquiry::factory()->create();
        $this->actingAs($this->admin());

        foreach ($this->adminPages($enquiry->id) as $url) {
            $this->get($url)->assertOk();
        }
    }

    public function test_every_admin_route_except_login_and_logout_requires_an_admin(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'admin'))
            ->reject(fn (RoutingRoute $route): bool => in_array($route->getName(), ['login', 'login.store', 'logout'], true));

        $this->assertNotEmpty($routes);
        foreach ($routes as $route) {
            $middleware = $route->gatherMiddleware();
            $this->assertContains('auth', $middleware, $route->uri());
            $this->assertContains('can:admin', $middleware, $route->uri());
        }
    }

    public function test_no_registration_reset_verification_2fa_or_passkey_routes_exist(): void
    {
        foreach (['register', 'password.request', 'password.reset', 'password.email', 'password.confirm',
            'verification.notice', 'two-factor.login', 'user-profile-information.update', 'user-password.update'] as $name) {
            $this->assertFalse(Route::has($name), "Route {$name} should not exist");
        }

        $uris = collect(Route::getRoutes()->getRoutes())->map(fn (RoutingRoute $route): string => $route->uri());
        $this->assertEmpty($uris->filter(fn (string $uri): bool => str_contains($uri, 'passkey') || str_starts_with($uri, 'user/') || str_starts_with($uri, 'admin/user/')));

        foreach (['/register', '/login', '/admin/register', '/admin/forgot-password', '/admin/two-factor-challenge',
            '/passkeys/login/options', '/admin/passkeys/login/options', '/user/confirm-password'] as $url) {
            $this->get($url)->assertNotFound();
        }
    }

    public function test_the_panel_is_a_404_when_disabled(): void
    {
        $this->refreshApplicationWithAdmin(false);

        $this->get('/admin')->assertNotFound();
        $this->get('/admin/login')->assertNotFound();
        $this->assertFalse(Route::has('admin.dashboard'));
    }

    public function test_api_responses_still_set_no_cookies(): void
    {
        $this->get('/admin/login')->assertOk()->assertCookie(config('session.cookie'));

        $response = $this->getJson('/api/v1/products/featured')->assertOk();
        $this->assertSame([], $response->headers->getCookies());
    }
}
