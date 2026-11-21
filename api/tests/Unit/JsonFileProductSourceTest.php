<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Catalogue\Exceptions\CatalogueUnavailable;
use App\Catalogue\Sources\FakeStoreProductMapper;
use App\Catalogue\Sources\JsonFileProductSource;
use PHPUnit\Framework\TestCase;

final class JsonFileProductSourceTest extends TestCase
{
    private ?string $tempFile = null;

    protected function tearDown(): void
    {
        if ($this->tempFile !== null) {
            @unlink($this->tempFile);
        }
        parent::tearDown();
    }

    private function source(string $path): JsonFileProductSource
    {
        return new JsonFileProductSource(new FakeStoreProductMapper, $path);
    }

    public function test_reads_the_committed_snapshot(): void
    {
        $source = $this->source(__DIR__.'/../../database/data/fakestore-products.json');

        $products = [...$source->fetchProducts()];

        $this->assertCount(20, $products);
        $this->assertSame('fakestore', $products[0]->source);
        $this->assertSame('snapshot', $source->origin());
    }

    public function test_missing_file_throws(): void
    {
        $this->expectException(CatalogueUnavailable::class);

        [...$this->source('/nonexistent/snapshot.json')->fetchProducts()];
    }

    public function test_invalid_json_throws(): void
    {
        $this->tempFile = (string) tempnam(sys_get_temp_dir(), 'snap');
        file_put_contents($this->tempFile, '{not json');

        $this->expectException(CatalogueUnavailable::class);

        [...$this->source($this->tempFile)->fetchProducts()];
    }
}
