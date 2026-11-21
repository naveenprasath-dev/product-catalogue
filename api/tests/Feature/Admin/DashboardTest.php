<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Category;
use App\Models\Enquiry;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

final class DashboardTest extends AdminTestCase
{
    public function test_the_dashboard_shows_catalogue_and_enquiry_counts(): void
    {
        $product = Product::factory()->for(Category::factory())->create(['imported_at' => '2026-11-21 09:30:00']);
        Product::factory()->count(2)->for(Category::factory())->create(['imported_at' => '2026-11-20 08:00:00']);
        Enquiry::factory()->count(2)->failed()->create(['product_id' => $product->id]);
        Enquiry::factory()->pending()->create(['product_id' => $product->id]);
        Enquiry::factory()->count(3)->create(['product_id' => $product->id]);

        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('stats.products', 3)
                ->where('stats.categories', 2)
                ->where('stats.enquiries', ['pending' => 1, 'sent' => 3, 'failed' => 2])
                ->where('stats.last_import_at', fn (string $at): bool => str_starts_with($at, '2026-11-21T09:30:00'))
                ->where('auth.user.email', fn (string $email): bool => $email !== '')
                ->missing('auth.user.is_admin')
                ->missing('auth.user.password'));
    }

    public function test_an_empty_catalogue_shows_zeros(): void
    {
        $this->actingAs($this->admin())
            ->get('/admin')
            ->assertInertia(fn (Assert $page) => $page
                ->component('dashboard')
                ->where('stats.products', 0)
                ->where('stats.enquiries', ['pending' => 0, 'sent' => 0, 'failed' => 0])
                ->where('stats.last_import_at', null));
    }
}
