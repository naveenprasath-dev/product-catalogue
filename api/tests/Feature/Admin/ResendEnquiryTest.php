<?php

declare(strict_types=1);

namespace Tests\Feature\Admin;

use App\Mail\EnquiryReceived;
use App\Models\Enquiry;
use Illuminate\Support\Facades\Mail;

final class ResendEnquiryTest extends AdminTestCase
{
    public function test_a_failed_enquiry_is_re_sent_to_the_sales_inbox(): void
    {
        Mail::fake();
        $enquiry = Enquiry::factory()->failed()->create();

        $this->actingAs($this->admin())
            ->from("/admin/enquiries/{$enquiry->id}")
            ->post("/admin/enquiries/{$enquiry->id}/resend")
            ->assertRedirect("/admin/enquiries/{$enquiry->id}")
            ->assertInertiaFlash('toast.type', 'success');

        Mail::assertSent(EnquiryReceived::class, fn (EnquiryReceived $mail): bool => $mail->hasTo(config('catalogue.sales_inbox')));
        $fresh = $enquiry->fresh();
        $this->assertSame('sent', $fresh?->status->value);
        $this->assertNotNull($fresh?->mailed_at);
    }

    public function test_a_mail_failure_keeps_it_failed_and_shows_an_error_toast_not_a_502(): void
    {
        $this->useFailingMailer();
        $enquiry = Enquiry::factory()->failed()->create();

        $this->actingAs($this->admin())
            ->from('/admin/enquiries')
            ->post("/admin/enquiries/{$enquiry->id}/resend")
            ->assertRedirect('/admin/enquiries')
            ->assertInertiaFlash('toast', ['type' => 'error', 'message' => 'Still failing, try again later.']);

        $this->assertSame('failed', $enquiry->fresh()?->status->value);
    }

    public function test_a_sent_enquiry_is_not_re_sent(): void
    {
        Mail::fake();
        $enquiry = Enquiry::factory()->create();

        $this->actingAs($this->admin())
            ->from('/admin/enquiries')
            ->post("/admin/enquiries/{$enquiry->id}/resend")
            ->assertRedirect('/admin/enquiries')
            ->assertInertiaFlash('toast.type', 'warning');

        Mail::assertNothingSent();
    }

    public function test_an_unknown_enquiry_is_a_404(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())
            ->post('/admin/enquiries/999999/resend')
            ->assertNotFound();

        Mail::assertNothingSent();
    }

    public function test_re_send_is_throttled(): void
    {
        Mail::fake();
        $enquiry = Enquiry::factory()->create();
        $this->actingAs($this->admin());

        for ($i = 0; $i < 6; $i++) {
            $this->post("/admin/enquiries/{$enquiry->id}/resend")->assertRedirect();
        }

        $this->post("/admin/enquiries/{$enquiry->id}/resend")->assertStatus(429);
    }
}
