<?php

declare(strict_types=1);

namespace App\Catalogue\Exceptions;

use App\Enums\EnquiryStatus;
use RuntimeException;

/** Only failed (or stuck pending) enquiries may be re-sent. */
final class EnquiryNotResendable extends RuntimeException
{
    public function __construct(public readonly int $enquiryId, public readonly EnquiryStatus $status)
    {
        parent::__construct("Enquiry {$enquiryId} is {$status->value} and cannot be re-sent.");
    }
}
