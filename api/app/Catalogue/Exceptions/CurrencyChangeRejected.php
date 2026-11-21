<?php

declare(strict_types=1);

namespace App\Catalogue\Exceptions;

use RuntimeException;

/** An admin change that would break a currency rule (base rate fixed at 1, exactly one enabled default, …). */
final class CurrencyChangeRejected extends RuntimeException
{
    /** @param  string  $field  the form field the message belongs to */
    public function __construct(string $message, public readonly string $field = 'code')
    {
        parent::__construct($message);
    }
}
