<?php

declare(strict_types=1);

namespace App\Catalogue\Exceptions;

use RuntimeException;

/** The live rate source could not be reached or returned unusable data. Stored rates stay unchanged. */
final class RatesUnavailable extends RuntimeException
{
    public static function because(string $reason, ?\Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
