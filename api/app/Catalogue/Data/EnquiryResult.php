<?php

declare(strict_types=1);

namespace App\Catalogue\Data;

use App\Enums\EnquiryStatus;

final readonly class EnquiryResult
{
    public function __construct(
        public int $id,
        public EnquiryStatus $status,
    ) {}
}
