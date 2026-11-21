<?php

declare(strict_types=1);

namespace App\Catalogue\Data;

/** Validated enquiry input from the customer. */
final readonly class EnquiryData
{
    public function __construct(
        public int $productId,
        public string $name,
        public string $email,
        public ?string $phone,
        public ?string $message,
        /** True when the hidden honeypot field was filled in (a bot). */
        public bool $honeypotFilled = false,
        /** The logged-in customer, from the session (never from the request body); null for guests. */
        public ?int $userId = null,
    ) {}
}
