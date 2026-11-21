<?php

declare(strict_types=1);

namespace App\Catalogue\Sources;

use App\Catalogue\Contracts\ProductSource;
use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;
use Psr\Log\LoggerInterface;

/** Builds the ProductSource for a CATALOGUE_SOURCE name; wraps Fake Store in the snapshot fallback when enabled. */
final readonly class ProductSourceFactory
{
    public const SOURCES = ['fakestore', 'file'];

    public function __construct(private Container $container) {}

    public function make(string $name, bool $fallbackToFile): ProductSource
    {
        return match ($name) {
            'file' => $this->container->make(JsonFileProductSource::class),
            'fakestore' => $fallbackToFile
                ? new FallbackProductSource(
                    primary: $this->container->make(FakeStoreProductSource::class),
                    fallback: $this->container->make(JsonFileProductSource::class),
                    logger: $this->container->make(LoggerInterface::class),
                )
                : $this->container->make(FakeStoreProductSource::class),
            default => throw new InvalidArgumentException(
                "Unknown product source '{$name}' (use ".implode(' or ', self::SOURCES).').'
            ),
        };
    }
}
