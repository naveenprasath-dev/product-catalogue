<?php

declare(strict_types=1);

namespace App\Catalogue\Data;

/** Outcome of a live rate sync. */
final readonly class RateSyncResult
{
    /**
     * @param  int  $updated  enabled non-base currencies whose rate was refreshed
     * @param  list<string>  $missing  enabled non-base currency codes the rate source has no rate for
     */
    public function __construct(
        public int $updated,
        public array $missing,
    ) {}
}
