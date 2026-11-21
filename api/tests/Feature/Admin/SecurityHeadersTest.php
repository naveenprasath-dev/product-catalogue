<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Http\Middleware\AdminSecurityHeaders;
use App\Models\Enquiry;
use Illuminate\Routing\Route as RoutingRoute;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Middleware\EncryptHistory;

/** QA B3 + clickjacking hardening: admin responses are never cached or framed, and history is encrypted. */
final class SecurityHeadersTest extends AdminTestCase
{
    private function assertAdminHeaders(TestResponse $response): void
    {
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'")
            ->assertHeader('X-Content-Type-Options', 'nosniff');
    }

    public function test_admin_pages_login_and_redirects_carry_the_headers(): void
    {
        $this->assertAdminHeaders($this->get('/admin/login'));
        $this->assertAdminHeaders($this->get('/admin')->assertRedirect('/admin/login')); // guest redirect
        $this->assertAdminHeaders($this->actingAs($this->customer())->get('/admin')->assertForbidden());

        $enquiry = Enquiry::factory()->create();
        $this->actingAs($this->admin());
        foreach (['/admin', '/admin/enquiries', "/admin/enquiries/{$enquiry->id}", '/admin/currencies'] as $url) {
            $this->assertAdminHeaders($this->get($url)->assertOk());
        }
        $this->assertAdminHeaders($this->post('/admin/logout'));
    }

    public function test_admin_pages_encrypt_the_browser_history(): void
    {
        $page = $this->actingAs($this->admin())->get('/admin/enquiries')->viewData('page');

        $this->assertTrue($page['encryptHistory']);
    }

    public function test_every_admin_route_has_the_headers_and_history_encryption(): void
    {
        $routes = collect(Route::getRoutes()->getRoutes())
            ->filter(fn (RoutingRoute $route): bool => str_starts_with($route->uri(), 'admin'));

        $this->assertNotEmpty($routes);
        foreach ($routes as $route) {
            $this->assertContains(AdminSecurityHeaders::class, $route->gatherMiddleware(), $route->uri());
            $this->assertContains(EncryptHistory::class, $route->gatherMiddleware(), $route->uri());
        }
    }

    public function test_the_api_is_unaffected(): void
    {
        $response = $this->getJson('/api/v1/currencies')->assertOk();

        $response->assertHeaderMissing('X-Frame-Options');
    }
}
