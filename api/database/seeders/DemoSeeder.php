<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\EnquiryStatus;
use App\Models\Enquiry;
use App\Models\Product;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Demo data so a reviewer can log in and see a populated admin panel:
 * the ADMIN_* admin, a demo shop customer and 12 sample enquiries ("[demo]" messages).
 *
 * Idempotent: existing accounts are never changed and enquiries are only created when no "[demo]"
 * enquiry exists yet. Rows are inserted directly, so no email is ever sent. Needs the catalogue
 * (catalogue:import) first. Refuses to run in production.
 *
 *   php artisan db:seed --class=DemoSeeder
 */
final class DemoSeeder extends Seeder
{
    /** Every demo enquiry's message starts with this, so they are easy to spot and to skip. */
    public const string MARKER = '[demo]';

    /**
     * [name, email, status, minutes ago, linked to the demo customer, message].
     * Pending rows older than 5 minutes are re-sendable in the admin (StoredEnquiry::STUCK_PENDING_MINUTES).
     *
     * @var list<array{0: string, 1: string, 2: EnquiryStatus, 3: int, 4: bool, 5: string}>
     */
    private const array ENQUIRIES = [
        ['Alice Martin', 'alice@example.com', EnquiryStatus::Sent, 60 * 24 * 6, false, 'Do you ship to Ireland?'],
        ['', '', EnquiryStatus::Sent, 60 * 24 * 5, true, 'Is this available in a larger size?'],
        ['Ben Okafor', 'ben@example.com', EnquiryStatus::Sent, 60 * 24 * 4, false, 'Can I order 20 units for our office?'],
        ['Chloe Dubois', 'chloe@example.com', EnquiryStatus::Sent, 60 * 24 * 3, false, 'What is the warranty on this item?'],
        ['', '', EnquiryStatus::Sent, 60 * 24 * 2, true, 'Could you send a quote including delivery?'],
        ['Dev Patel', 'dev@example.com', EnquiryStatus::Sent, 60 * 30, false, 'Is there a discount for repeat orders?'],
        ['Emma Rossi', 'emma@example.com', EnquiryStatus::Failed, 60 * 26, false, 'Please call me back about colours.'],
        ['', '', EnquiryStatus::Failed, 60 * 8, true, 'When will this be back in stock?'],
        ['Farid Haddad', 'farid@example.com', EnquiryStatus::Pending, 90, false, 'Do you offer gift wrapping?'],
        ['Grace Kim', 'grace@example.com', EnquiryStatus::Pending, 20, false, 'Is the price negotiable for 50 units?'],
        ['', '', EnquiryStatus::Pending, 1, true, 'Just checking the delivery time.'],
        ['Hugo Lambert', 'hugo@example.com', EnquiryStatus::Sent, 10, false, 'Thanks, can you confirm the dimensions?'],
    ];

    public function run(): void
    {
        if (app()->isProduction()) {
            throw new RuntimeException('DemoSeeder refuses to run in production (APP_ENV=production).');
        }

        $this->ensureAdmin();
        $customer = $this->ensureCustomer();
        $this->seedEnquiries($customer);
    }

    /** Reuses admin:create --from-env, so the password rules and "never reset" behaviour live in one place. */
    private function ensureAdmin(): void
    {
        if (blank(config('admin.bootstrap.email')) || blank(config('admin.bootstrap.password'))) {
            $this->say('warn', 'ADMIN_EMAIL/ADMIN_PASSWORD not set; skipping the demo admin.');

            return;
        }

        if (Artisan::call('admin:create', ['--from-env' => true]) !== 0) {
            $this->say('warn', 'admin:create --from-env failed: '.trim(Artisan::output()));

            return;
        }

        $this->say('info', trim(Artisan::output()));
    }

    private function ensureCustomer(): ?User
    {
        $email = Str::lower(trim((string) config('demo.customer.email')));
        $password = (string) config('demo.customer.password');

        if ($email === '' || filter_var($email, FILTER_VALIDATE_EMAIL) === false || $password === '') {
            $this->say('warn', 'DEMO_CUSTOMER_EMAIL/DEMO_CUSTOMER_PASSWORD not set or invalid; skipping the demo customer.');

            return null;
        }

        $existing = User::query()->where('email', $email)->first();
        if ($existing !== null) {
            // Never change an existing account (its password may have been changed on purpose).
            $this->say('info', "Demo customer {$email} already exists; nothing to do.");

            return $existing;
        }

        $customer = User::query()->forceCreate([
            'name' => (string) config('demo.customer.name'),
            'email' => $email,
            'password' => $password, // hashed by the model's `hashed` cast
            'is_admin' => false,
        ]);
        $this->say('info', "Demo customer {$email} created.");

        return $customer;
    }

    private function seedEnquiries(?User $customer): void
    {
        $products = Product::query()->orderBy('id')->get(['id', 'title']);
        if ($products->isEmpty()) {
            $this->say('warn', 'No products yet; skipping demo enquiries. Run `php artisan catalogue:import` first, then seed again.');

            return;
        }

        if (Enquiry::query()->where('message', 'like', self::MARKER.'%')->exists()) {
            $this->say('info', 'Demo enquiries already exist; nothing to do.');

            return;
        }

        $now = CarbonImmutable::now();

        DB::transaction(function () use ($products, $customer, $now): void {
            foreach (self::ENQUIRIES as $i => [$name, $email, $status, $minutesAgo, $linked, $message]) {
                $product = $products[$i % $products->count()];
                $createdAt = $now->subMinutes($minutesAgo);
                $user = $linked ? $customer : null;

                // forceCreate with explicit timestamps: Eloquent keeps a created_at that is already set.
                Enquiry::query()->forceCreate([
                    'product_id' => $product->id,
                    'user_id' => $user?->id,
                    'product_title' => $product->title,
                    'name' => $user->name ?? ($name !== '' ? $name : 'Sam Guest'),
                    'email' => $user->email ?? ($email !== '' ? $email : 'sam.guest@example.com'),
                    'phone' => $i % 3 === 0 ? '+44 7700 900'.str_pad((string) (100 + $i), 3, '0', STR_PAD_LEFT) : null,
                    'message' => self::MARKER.' '.$message,
                    'status' => $status,
                    'mailed_at' => $status === EnquiryStatus::Sent ? $createdAt->addSeconds(2) : null,
                    'created_at' => $createdAt,
                    'updated_at' => $createdAt,
                ]);
            }
        });

        $this->say('info', count(self::ENQUIRIES).' demo enquiries created (no email sent).');
    }

    /** Seeder output when run from artisan; silent otherwise. */
    private function say(string $style, string $line): void
    {
        $this->command?->{$style}($line);
    }
}
