<?php

declare(strict_types=1);

namespace App\Catalogue\Contracts;

use App\Catalogue\Data\EnquiryData;
use App\Catalogue\Data\StoredEnquiry;
use App\Enums\EnquiryStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/** Stores enquiries and their delivery status. */
interface EnquiryRepository
{
    /** Saves the enquiry as pending and returns its id. */
    public function createPending(EnquiryData $data, string $productTitle): int;

    public function markSent(int $id): void;

    public function markFailed(int $id): void;

    public function find(int $id): ?StoredEnquiry;

    /**
     * Newest first, optionally only one status.
     *
     * @return LengthAwarePaginator<int, StoredEnquiry>
     */
    public function paginate(?EnquiryStatus $status, int $page, int $perPage): LengthAwarePaginator;

    /** @return array<value-of<EnquiryStatus>, int> every status, zero when none */
    public function countsByStatus(): array;
}
