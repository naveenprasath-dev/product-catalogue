<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Catalogue\CatalogueService;
use App\Catalogue\Data\StoredEnquiry;
use App\Catalogue\Exceptions\EnquiryNotFound;
use App\Catalogue\Exceptions\EnquiryNotResendable;
use App\Catalogue\Exceptions\EnquiryNotSent;
use App\Enums\EnquiryStatus;
use App\Http\Resources\Admin\DashboardStatsResource;
use App\Http\Resources\Admin\EnquiryResource;
use App\Mail\EnquiryReceived;
use App\Models\Category;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/** The admin use cases in CatalogueService (the /admin pages are tested in tests/Feature/Admin). */
final class AdminCatalogueServiceTest extends TestCase
{
    use RefreshDatabase;

    private function service(): CatalogueService
    {
        return $this->app->make(CatalogueService::class);
    }

    public function test_dashboard_stats_count_products_categories_and_enquiries_by_status(): void
    {
        $category = Category::factory()->create();
        Product::factory()->count(3)->for($category)->create(['imported_at' => '2026-11-20 10:00:00']);
        $latest = Product::factory()->for(Category::factory())->create(['imported_at' => '2026-11-21 09:30:00']);
        Enquiry::factory()->count(2)->failed()->create(['product_id' => $latest->id]);
        Enquiry::factory()->pending()->create(['product_id' => $latest->id]);
        Enquiry::factory()->count(4)->create(['product_id' => $latest->id]);

        $stats = $this->service()->dashboardStats();

        $this->assertSame(4, $stats->products);
        $this->assertSame(2, $stats->categories);
        $this->assertSame([1, 4, 2, 7], [$stats->enquiriesPending, $stats->enquiriesSent, $stats->enquiriesFailed, $stats->enquiriesTotal()]);
        $this->assertSame('2026-11-21 09:30:00', $stats->lastImportAt?->format('Y-m-d H:i:s'));

        $json = (new DashboardStatsResource($stats))->resolve(Request::create('/'));
        $this->assertSame(['pending' => 1, 'sent' => 4, 'failed' => 2], $json['enquiries']);
    }

    public function test_dashboard_stats_on_an_empty_database(): void
    {
        $stats = $this->service()->dashboardStats();

        $this->assertSame([0, 0, 0, null], [$stats->products, $stats->enquiriesTotal(), $stats->enquiriesFailed, $stats->lastImportAt]);
    }

    public function test_enquiries_are_newest_first_paginated_and_filterable_by_status(): void
    {
        $product = Product::factory()->create();
        $this->travelTo('2026-11-01 10:00:00');
        $oldFailed = Enquiry::factory()->failed()->create(['product_id' => $product->id]);
        $this->travelTo('2026-11-02 10:00:00');
        Enquiry::factory()->count(2)->create(['product_id' => $product->id]);
        $this->travelTo('2026-11-03 10:00:00');
        $newFailed = Enquiry::factory()->failed()->create(['product_id' => $product->id]);
        $this->travelBack();

        $page = $this->service()->enquiries(perPage: 3);
        $this->assertSame(4, $page->total());
        $this->assertSame(2, $page->lastPage());
        $this->assertContainsOnlyInstancesOf(StoredEnquiry::class, $page->items());
        $this->assertSame($newFailed->id, $page->items()[0]->id);
        $this->assertSame($oldFailed->id, $this->service()->enquiries(page: 2, perPage: 3)->items()[0]->id);

        $failed = $this->service()->enquiries(EnquiryStatus::Failed);
        $this->assertSame([$newFailed->id, $oldFailed->id], array_map(fn (StoredEnquiry $e): int => $e->id, $failed->items()));
    }

    public function test_enquiry_detail_maps_every_field_and_unknown_id_is_null(): void
    {
        $user = User::factory()->create();
        $model = Enquiry::factory()->forUser($user)->create(['phone' => '123', 'message' => 'Hi']);

        $enquiry = $this->service()->enquiry($model->id);

        $this->assertNotNull($enquiry);
        $this->assertSame([$user->id, $user->email, '123', 'Hi', EnquiryStatus::Sent], [$enquiry->userId, $enquiry->email, $enquiry->phone, $enquiry->message, $enquiry->status]);
        $this->assertNull($this->service()->enquiry(999_999));

        $json = (new EnquiryResource($enquiry))->resolve(Request::create('/'));
        $this->assertSame(['id', 'product_id', 'product_title', 'name', 'email', 'phone', 'message', 'status', 'customer_account', 'created_at', 'mailed_at', 'resendable'], array_keys($json));
        $this->assertTrue($json['customer_account']);
        $this->assertFalse($json['resendable']);
    }

    public function test_resend_of_a_failed_enquiry_emails_the_sales_inbox_and_marks_it_sent(): void
    {
        Mail::fake();
        $enquiry = Enquiry::factory()->failed()->create();

        $result = $this->service()->resendEnquiry($enquiry->id);

        $this->assertSame([$enquiry->id, EnquiryStatus::Sent], [$result->id, $result->status]);
        Mail::assertSent(EnquiryReceived::class, fn (EnquiryReceived $mail): bool => $mail->hasTo('sales@example.test'));
        $this->assertSame(EnquiryStatus::Sent, $enquiry->fresh()?->status);
        $this->assertNotNull($enquiry->fresh()?->mailed_at);
    }

    public function test_resend_that_fails_again_keeps_it_failed(): void
    {
        $this->useFailingMailer();
        $enquiry = Enquiry::factory()->failed()->create();

        try {
            $this->service()->resendEnquiry($enquiry->id);
            $this->fail('Expected EnquiryNotSent');
        } catch (EnquiryNotSent $e) {
            $this->assertSame($enquiry->id, $e->enquiryId);
        }

        $this->assertSame(EnquiryStatus::Failed, $enquiry->fresh()?->status);
        $this->assertNull($enquiry->fresh()?->mailed_at);
    }

    public function test_sent_and_recent_pending_enquiries_are_not_resendable(): void
    {
        Mail::fake();
        $sent = Enquiry::factory()->create();
        $recentPending = Enquiry::factory()->pending()->create(['created_at' => now()->subMinutes(2)]);

        foreach ([$sent, $recentPending] as $enquiry) {
            try {
                $this->service()->resendEnquiry($enquiry->id);
                $this->fail('Expected EnquiryNotResendable');
            } catch (EnquiryNotResendable) {
                // expected
            }
        }

        Mail::assertNothingSent();
    }

    public function test_pending_enquiry_stuck_for_over_five_minutes_can_be_resent(): void
    {
        Mail::fake();
        $stuck = Enquiry::factory()->pending()->create(['created_at' => now()->subMinutes(6)]);

        $this->service()->resendEnquiry($stuck->id);

        Mail::assertSentCount(1);
        $this->assertSame(EnquiryStatus::Sent, $stuck->fresh()?->status);
    }

    public function test_resend_of_unknown_enquiry_throws_not_found(): void
    {
        $this->expectException(EnquiryNotFound::class);

        $this->service()->resendEnquiry(999_999);
    }
}
