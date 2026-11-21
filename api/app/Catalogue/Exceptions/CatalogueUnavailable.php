<?php

declare(strict_types=1);

namespace App\Catalogue\Exceptions;

use RuntimeException;

/** A product source could not be reached or returned unusable data. */
final class CatalogueUnavailable extends RuntimeException
{
    public static function because(string $reason, ?\Throwable $previous = null): self
    {
        return new self($reason, 0, $previous);
    }
}
