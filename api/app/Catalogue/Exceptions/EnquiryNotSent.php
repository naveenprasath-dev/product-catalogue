<?php

declare(strict_types=1);

namespace App\Catalogue\Exceptions;

use RuntimeException;

/** The enquiry was stored but the email to the sales inbox could not be sent. */
final class EnquiryNotSent extends RuntimeException
{
    public function __construct(public readonly int $enquiryId, ?\Throwable $previous = null)
    {
        parent::__construct('The enquiry could not be sent.', 0, $previous);
    }
}
