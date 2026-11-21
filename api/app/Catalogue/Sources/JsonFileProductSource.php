<?php

declare(strict_types=1);

namespace App\Catalogue\Sources;

use App\Catalogue\Contracts\ProductSource;
use App\Catalogue\Exceptions\CatalogueUnavailable;

/** Reads the committed Fake Store snapshot (`GET /products` response saved to a file). No network. */
final readonly class JsonFileProductSource implements ProductSource
{
    public function __construct(
        private FakeStoreProductMapper $mapper,
        private string $path,
    ) {}

    public function fetchProducts(): iterable
    {
        if (! is_file($this->path) || ! is_readable($this->path)) {
            throw CatalogueUnavailable::because("Snapshot file not found: {$this->path}");
        }

        try {
            $payload = json_decode((string) file_get_contents($this->path), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw CatalogueUnavailable::because("Snapshot file is not valid JSON: {$this->path}", $e);
        }

        return $this->mapper->mapAll($payload);
    }

    public function origin(): string
    {
        return 'snapshot';
    }
}
