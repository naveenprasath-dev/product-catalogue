<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Catalogue\Data\EnquiryData;
use App\Catalogue\Data\Product as ProductData;
use App\Mail\EnquiryReceived;
use App\Models\Product;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Symfony\Component\Mailer\Exception\TransportException;
use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;
use Tests\TestCase;

final class StoreEnquiryTest extends TestCase
{
    use RefreshDatabase;

    private Product $product;

    protected function setUp(): void
    {
        parent::setUp();

        $this->product = Product::factory()->create(['title' => 'Fjallraven Backpack']);
    }

    /** @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'product_id' => $this->product->id,
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'phone' => '+44 20 7946 0000',
            'message' => 'Do you have this in blue?',
            'website' => '',
        ], $overrides);
    }

    public function test_valid_enquiry_is_stored_as_sent_and_emailed_to_the_sales_inbox(): void
    {
        Mail::fake();

        $response = $this->postJson('/api/v1/enquiries', $this->validPayload())
            ->assertCreated()
            ->assertExactJsonStructure(['data' => ['id', 'status']])
            ->assertJsonPath('data.status', 'sent');

        $id = $response->json('data.id');
        $this->assertIsInt($id);
        $this->assertDatabaseHas('enquiries', [
            'id' => $id,
            'product_id' => $this->product->id,
            'product_title' => 'Fjallraven Backpack',
            'name' => 'Ada Lovelace',
            'email' => 'ada@example.com',
            'status' => 'sent',
        ]);
        $this->assertDatabaseMissing('enquiries', ['id' => $id, 'mailed_at' => null]);

        Mail::assertSent(EnquiryReceived::class, fn (EnquiryReceived $mail): bool => $mail->hasTo('sales@example.test')
            && $mail->product->title === 'Fjallraven Backpack'
            && $mail->enquiryId === $id);
        Mail::assertSentCount(1);
    }

    public function test_phone_and_message_are_optional_whether_missing_or_empty(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/enquiries', [
            'product_id' => $this->product->id, 'name' => 'Ada', 'email' => 'ada@example.com', 'website' => '',
        ])->assertCreated();

        $this->postJson('/api/v1/enquiries', $this->validPayload(['phone' => '', 'message' => '']))
            ->assertCreated();

        $this->assertDatabaseCount('enquiries', 2);
        $this->assertDatabaseMissing('enquiries', ['phone' => '']);
    }

    public function test_mail_transport_failure_marks_enquiry_failed_and_returns_502(): void
    {
        Mail::extend('failing', fn () => new class extends AbstractTransport
        {
            protected function doSend(SentMessage $message): void
            {
                throw new TransportException('SMTP is down');
            }

            public function __toString(): string
            {
                return 'failing://';
            }
        });
        config(['mail.mailers.failing' => ['transport' => 'failing'], 'mail.default' => 'failing']);

        $this->postJson('/api/v1/enquiries', $this->validPayload())
            ->assertStatus(502)
            ->assertExactJsonStructure(['message'])
            ->assertJsonMissingPath('trace')
            ->assertJsonMissingPath('exception');

        $this->assertDatabaseHas('enquiries', ['email' => 'ada@example.com', 'status' => 'failed', 'mailed_at' => null]);
    }

    public function test_missing_name_and_email_are_rejected(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/enquiries', $this->validPayload(['name' => '', 'email' => null]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['name', 'email']);

        $this->assertDatabaseCount('enquiries', 0);
        Mail::assertNothingSent();
    }

    public function test_invalid_email_is_rejected(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/enquiries', $this->validPayload(['email' => 'not-an-email']))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['email'])
            ->assertJsonMissingValidationErrors(['name', 'product_id']);

        $this->assertDatabaseCount('enquiries', 0);
        Mail::assertNothingSent();
    }

    public function test_too_long_fields_are_rejected(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/enquiries', $this->validPayload([
            'message' => str_repeat('a', 2001),
            'name' => str_repeat('a', 101),
            'phone' => str_repeat('1', 31),
        ]))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['message', 'name', 'phone']);

        $this->assertDatabaseCount('enquiries', 0);
        Mail::assertNothingSent();
    }

    public function test_unknown_or_invalid_product_id_is_rejected(): void
    {
        Mail::fake();

        foreach ([999999, 'abc', null, '99999999999999999999999'] as $productId) {
            $this->postJson('/api/v1/enquiries', $this->validPayload(['product_id' => $productId]))
                ->assertUnprocessable()
                ->assertJsonValidationErrors(['product_id']);
        }

        $this->assertDatabaseCount('enquiries', 0);
        Mail::assertNothingSent();
    }

    public function test_filled_honeypot_is_silently_accepted_without_storing_or_sending(): void
    {
        Mail::fake();

        $this->postJson('/api/v1/enquiries', $this->validPayload(['website' => 'http://spam.example']))
            ->assertCreated()
            ->assertJsonPath('data.status', 'sent');

        $this->assertDatabaseCount('enquiries', 0);
        Mail::assertNothingSent();
    }

    public function test_enquiry_from_a_logged_in_customer_is_linked_to_their_account(): void
    {
        Mail::fake();
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/v1/enquiries', $this->validPayload())->assertCreated();

        $this->assertDatabaseHas('enquiries', ['email' => 'ada@example.com', 'user_id' => $user->id]);
    }

    public function test_guest_enquiry_has_no_account_and_user_id_in_the_body_is_ignored(): void
    {
        Mail::fake();
        $someoneElse = User::factory()->create();

        $this->postJson('/api/v1/enquiries', $this->validPayload(['user_id' => $someoneElse->id]))->assertCreated();

        $this->assertDatabaseHas('enquiries', ['email' => 'ada@example.com', 'user_id' => null]);
    }

    public function test_enquiries_are_throttled(): void
    {
        Mail::fake();
        $limit = (int) config('catalogue.enquiry_rate_limit');

        for ($i = 0; $i < $limit; $i++) {
            $this->postJson('/api/v1/enquiries', $this->validPayload())->assertCreated();
        }

        $this->postJson('/api/v1/enquiries', $this->validPayload())
            ->assertTooManyRequests()
            ->assertJsonStructure(['message']);

        $this->assertDatabaseCount('enquiries', $limit);
    }

    public function test_html_in_user_input_is_escaped_in_the_email(): void
    {
        $mail = new EnquiryReceived(
            enquiryId: 1,
            enquiry: new EnquiryData(
                productId: 1,
                name: '<b>Mallory</b>',
                email: 'mallory@example.com',
                phone: null,
                message: '<script>alert("x")</script>',
            ),
            product: new ProductData(1, 'fakestore', '1', 'Backpack', 'd', 10.0, 'https://example.test/i.png', 'bags', 'bags', 4.0, 1),
        );

        $html = $mail->render();

        $this->assertStringContainsString('&lt;script&gt;', $html);
        $this->assertStringContainsString('&lt;b&gt;Mallory&lt;/b&gt;', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringNotContainsString('<b>Mallory', $html);
    }
}
