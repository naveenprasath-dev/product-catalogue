<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Models\Enquiry;
use App\Models\Product;
use Inertia\Testing\AssertableInertia as Assert;

final class EnquiriesTest extends AdminTestCase
{
    public function test_the_list_is_newest_first_and_paginated_by_20(): void
    {
        $product = Product::factory()->create();
        $old = Enquiry::factory()->create(['product_id' => $product->id, 'created_at' => now()->subDays(2)]);
        Enquiry::factory()->count(20)->create(['product_id' => $product->id, 'created_at' => now()->subDay()]);
        $newest = Enquiry::factory()->create(['product_id' => $product->id, 'created_at' => now()]);

        $this->actingAs($this->admin());

        $this->get('/admin/enquiries')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('enquiries/index')
                ->has('enquiries.data', 20)
                ->where('enquiries.data.0.id', $newest->id)
                ->where('enquiries.meta.total', 22)
                ->where('enquiries.meta.last_page', 2)
                ->where('filters.status', null));

        $this->get('/admin/enquiries?page=2')
            ->assertInertia(fn (Assert $page) => $page
                ->has('enquiries.data', 2)
                ->where('enquiries.data.1.id', $old->id));
    }

    public function test_the_list_can_be_filtered_by_status(): void
    {
        $failed = Enquiry::factory()->failed()->create();
        Enquiry::factory()->count(2)->create();

        $this->actingAs($this->admin())
            ->get('/admin/enquiries?status=failed')
            ->assertInertia(fn (Assert $page) => $page
                ->component('enquiries/index')
                ->has('enquiries.data', 1)
                ->where('enquiries.data.0.id', $failed->id)
                ->where('enquiries.data.0.status', 'failed')
                ->where('enquiries.data.0.resendable', true)
                ->where('filters.status', 'failed'));
    }

    public function test_an_invalid_filter_is_a_validation_error(): void
    {
        $this->actingAs($this->admin())
            ->from('/admin/enquiries')
            ->get('/admin/enquiries?status=archived')
            ->assertRedirect('/admin/enquiries')
            ->assertSessionHasErrors('status');
    }

    public function test_the_detail_page_shows_only_the_listed_fields(): void
    {
        $enquiry = Enquiry::factory()->create(['message' => '<b>Hi</b>', 'phone' => null]);

        $this->actingAs($this->admin())
            ->get("/admin/enquiries/{$enquiry->id}")
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('enquiries/show')
                // has() with a closure fails on any prop the closure doesn't check: no extra fields leak.
                ->has('enquiry', fn (Assert $e) => $e
                    ->where('id', $enquiry->id)
                    ->where('product_id', $enquiry->product_id)
                    ->where('product_title', $enquiry->product_title)
                    ->where('name', $enquiry->name)
                    ->where('email', $enquiry->email)
                    ->where('phone', null)
                    ->where('message', '<b>Hi</b>')
                    ->where('status', 'sent')
                    ->where('customer_account', false)
                    ->where('resendable', false)
                    ->whereType('created_at', 'string')
                    ->whereType('mailed_at', 'string')));
    }

    public function test_an_unknown_or_malformed_id_is_a_404(): void
    {
        $this->actingAs($this->admin());

        $this->get('/admin/enquiries/999999')->assertNotFound();
        $this->get('/admin/enquiries/abc')->assertNotFound();
        $this->get('/admin/enquiries/9999999999999999999')->assertNotFound();
    }
}
