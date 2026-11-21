<?php

declare(strict_types=1);

namespace App\Catalogue\Exceptions;

use RuntimeException;

/** No currency with this code. Rendered as a 404 (bootstrap/app.php). */
final class CurrencyNotFound extends RuntimeException
{
    public function __construct(public readonly string $currencyCode)
    {
        parent::__construct("Currency {$currencyCode} not found.");
    }
}
