<?php

declare(strict_types=1);

namespace App\Catalogue;

use App\Catalogue\Contracts\EnquiryRepository;
use App\Catalogue\Contracts\ProductRepository;
use App\Catalogue\Data\Category;
use App\Catalogue\Data\CategoryListing;
use App\Catalogue\Data\DashboardStats;
use App\Catalogue\Data\EnquiryData;
use App\Catalogue\Data\EnquiryResult;
use App\Catalogue\Data\Product;
use App\Catalogue\Data\StoredEnquiry;
use App\Catalogue\Exceptions\EnquiryNotFound;
use App\Catalogue\Exceptions\EnquiryNotResendable;
use App\Catalogue\Exceptions\EnquiryNotSent;
use App\Enums\EnquiryStatus;
use App\Mail\EnquiryReceived;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Psr\Log\LoggerInterface;
use Throwable;

/** Use cases behind the API and the admin panel. Reads products only through ProductRepository, never from an upstream source. */
final readonly class CatalogueService
{
    /**
     * @param  list<string>  $featuredIds  curated Fake Store ids (external_id), in display order
     */
    public function __construct(
        private ProductRepository $products,
        private EnquiryRepository $enquiries,
        private MailFactory $mail,
        private LoggerInterface $logger,
        private array $featuredIds,
        private int $topRatedPerCategory,
        private string $salesInbox,
    ) {}

    /** @return list<Product> */
    public function featured(): array
    {
        return $this->products->featured($this->featuredIds);
    }

    /** @return list<Category> */
    public function categories(): array
    {
        return $this->products->categories();
    }

    public function categoryListing(string $slug): ?CategoryListing
    {
        $category = $this->products->findCategory($slug);
        if ($category === null) {
            return null;
        }

        [$topRated, $others] = self::splitTopRated($this->products->byCategory($slug), $this->topRatedPerCategory);

        return new CategoryListing($category, $topRated, $others);
    }

    /**
     * Top N by rating (highest first, ties by id); the rest keep the same order.
     *
     * @param  list<Product>  $products
     * @return array{0: list<Product>, 1: list<Product>}
     */
    public static function splitTopRated(array $products, int $count): array
    {
        usort($products, fn (Product $a, Product $b): int => [$b->ratingRate, $a->id] <=> [$a->ratingRate, $b->id]);

        return [array_slice($products, 0, $count), array_slice($products, $count)];
    }

    /** @return list<Product> */
    public function search(string $term): array
    {
        return $this->products->search($term);
    }

    public function product(int $id): ?Product
    {
        return $this->products->find($id);
    }

    /**
     * Save → email the sales inbox → mark sent. If the email fails the enquiry is kept as failed.
     *
     * @throws EnquiryNotSent
     */
    public function submitEnquiry(EnquiryData $data): EnquiryResult
    {
        // Honeypot: pretend it worked so bots learn nothing, but store and send nothing.
        if ($data->honeypotFilled) {
            $this->logger->info('Enquiry honeypot triggered; ignored', ['product_id' => $data->productId]);

            return new EnquiryResult(0, EnquiryStatus::Sent);
        }

        // product_id is validated with exists:products,id before we get here.
        $product = $this->products->find($data->productId)
            ?? throw new \LogicException("Product {$data->productId} not found.");

        $id = $this->enquiries->createPending($data, $product->title);
        $this->deliver($id, $data, $product);

        return new EnquiryResult($id, EnquiryStatus::Sent);
    }

    public function dashboardStats(): DashboardStats
    {
        $counts = $this->enquiries->countsByStatus();

        return new DashboardStats(
            products: $this->products->countProducts(),
            categories: $this->products->countCategories(),
            enquiriesPending: $counts[EnquiryStatus::Pending->value],
            enquiriesSent: $counts[EnquiryStatus::Sent->value],
            enquiriesFailed: $counts[EnquiryStatus::Failed->value],
            lastImportAt: $this->products->lastImportedAt(),
        );
    }

    /**
     * Newest first, optionally filtered by status.
     *
     * @return LengthAwarePaginator<int, StoredEnquiry>
     */
    public function enquiries(?EnquiryStatus $status = null, int $page = 1, int $perPage = 20): LengthAwarePaginator
    {
        return $this->enquiries->paginate($status, max(1, $page), max(1, $perPage));
    }

    public function enquiry(int $id): ?StoredEnquiry
    {
        return $this->enquiries->find($id);
    }

    /**
     * Sends a failed (or stuck pending) enquiry to the sales inbox again, through the same path as a new one.
     *
     * @throws EnquiryNotFound unknown id (rendered as 404)
     * @throws EnquiryNotResendable already sent, or pending for less than a few minutes
     * @throws EnquiryNotSent the email failed again (the enquiry stays failed)
     */
    public function resendEnquiry(int $id): EnquiryResult
    {
        $stored = $this->enquiries->find($id)
            ?? throw new EnquiryNotFound($id);

        if (! $stored->canBeResent(CarbonImmutable::now())) {
            throw new EnquiryNotResendable($id, $stored->status);
        }

        // Products are never deleted while enquiries reference them (FK), so this only fails on a broken DB.
        $product = $this->products->find($stored->productId)
            ?? throw new \LogicException("Product {$stored->productId} not found.");

        $this->deliver($id, $stored->toData(), $product);
        $this->logger->info('Enquiry re-sent', ['enquiry_id' => $id]);

        return new EnquiryResult($id, EnquiryStatus::Sent);
    }

    /**
     * The one mail path for enquiries: email the sales inbox, then mark sent, or mark failed and throw.
     *
     * @throws EnquiryNotSent
     */
    private function deliver(int $id, EnquiryData $data, Product $product): void
    {
        try {
            $this->mail->mailer()
                ->to($this->salesInbox)
                ->send(new EnquiryReceived($id, $data, $product));
        } catch (Throwable $e) {
            $this->enquiries->markFailed($id);
            // Log the id only, not the customer's personal data.
            $this->logger->error('Enquiry email failed', ['enquiry_id' => $id, 'error' => $e->getMessage()]);

            throw new EnquiryNotSent($id, $e);
        }

        $this->enquiries->markSent($id);
    }
}
