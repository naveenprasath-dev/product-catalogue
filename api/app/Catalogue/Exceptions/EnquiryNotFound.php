<?php

declare(strict_types=1);

namespace App\Catalogue\Exceptions;

use RuntimeException;

/** No enquiry with this id. Rendered as a 404 (bootstrap/app.php). */
final class EnquiryNotFound extends RuntimeException
{
    public function __construct(public readonly int $enquiryId)
    {
        parent::__construct("Enquiry {$enquiryId} not found.");
    }
}
