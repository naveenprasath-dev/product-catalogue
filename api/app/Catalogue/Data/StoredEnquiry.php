<?php

declare(strict_types=1);

namespace App\Catalogue\Data;

use App\Enums\EnquiryStatus;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** An enquiry as stored, for the admin panel (never an Eloquent model). */
final readonly class StoredEnquiry
{
    /** A pending enquiry older than this never got a send result (e.g. the worker died), so it may be re-sent. */
    public const int STUCK_PENDING_MINUTES = 5;

    public function __construct(
        public int $id,
        public int $productId,
        public string $productTitle,
        public ?int $userId,
        public string $name,
        public string $email,
        public ?string $phone,
        public ?string $message,
        public EnquiryStatus $status,
        public CarbonImmutable $createdAt,
        public ?CarbonImmutable $mailedAt,
    ) {}

    /** Failed, or stuck in pending. A sent enquiry is never re-sent (the sales inbox would get it twice). */
    public function canBeResent(CarbonInterface $now): bool
    {
        return match ($this->status) {
            EnquiryStatus::Failed => true,
            EnquiryStatus::Pending => $this->createdAt->lte($now->copy()->subMinutes(self::STUCK_PENDING_MINUTES)),
            EnquiryStatus::Sent => false,
        };
    }

    /** Rebuilds the customer input, for sending the same email again. */
    public function toData(): EnquiryData
    {
        return new EnquiryData(
            productId: $this->productId,
            name: $this->name,
            email: $this->email,
            phone: $this->phone,
            message: $this->message,
            userId: $this->userId,
        );
    }
}
