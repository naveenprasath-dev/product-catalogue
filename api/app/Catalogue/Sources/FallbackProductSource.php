<?php

declare(strict_types=1);

namespace App\Catalogue\Sources;

use App\Catalogue\Contracts\ProductSource;
use App\Catalogue\Exceptions\CatalogueUnavailable;
use Psr\Log\LoggerInterface;

/**
 * Tries the primary source; if it is unavailable, logs a warning and uses the fallback.
 * origin() reports which one actually supplied the products (after fetchProducts()).
 */
final class FallbackProductSource implements ProductSource
{
    private ?string $usedOrigin = null;

    public function __construct(
        private readonly ProductSource $primary,
        private readonly ProductSource $fallback,
        private readonly LoggerInterface $logger,
    ) {}

    public function fetchProducts(): iterable
    {
        try {
            // Materialise here so a failure while iterating is still caught.
            $products = [...$this->primary->fetchProducts()];
            $this->usedOrigin = $this->primary->origin();

            return $products;
        } catch (CatalogueUnavailable $primaryError) {
            $this->logger->warning('Primary product source unavailable, using fallback', [
                'primary' => $this->primary->origin(),
                'fallback' => $this->fallback->origin(),
                'reason' => $primaryError->getMessage(),
            ]);
        }

        try {
            $products = [...$this->fallback->fetchProducts()];
        } catch (CatalogueUnavailable $fallbackError) {
            throw CatalogueUnavailable::because(
                "{$this->primary->origin()} unavailable ({$primaryError->getMessage()}) and fallback failed ({$fallbackError->getMessage()})",
                $fallbackError,
            );
        }

        $this->usedOrigin = "{$this->fallback->origin()} ({$this->primary->origin()} unavailable)";

        return $products;
    }

    public function origin(): string
    {
        return $this->usedOrigin ?? $this->primary->origin();
    }
}
