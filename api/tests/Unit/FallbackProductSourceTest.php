<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Catalogue\Contracts\ProductSource;
use App\Catalogue\Data\Product;
use App\Catalogue\Exceptions\CatalogueUnavailable;
use App\Catalogue\Sources\FallbackProductSource;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;

final class FallbackProductSourceTest extends TestCase
{
    private function product(string $externalId): Product
    {
        return new Product(null, 'fakestore', $externalId, 't', 'd', 1.0, 'https://x.test/i.png', 'c', 'c', 4.0, 1);
    }

    /** @param  list<Product>|null  $products  null = throw */
    private function source(string $origin, ?array $products, int &$calls): ProductSource
    {
        return new class($origin, $products, $calls) implements ProductSource
        {
            /** @param  list<Product>|null  $products */
            public function __construct(private string $origin, private ?array $products, private int &$calls) {}

            public function fetchProducts(): iterable
            {
                $this->calls++;

                return $this->products ?? throw CatalogueUnavailable::because("{$this->origin} down");
            }

            public function origin(): string
            {
                return $this->origin;
            }
        };
    }

    private function logger(): AbstractLogger
    {
        return new class extends AbstractLogger
        {
            /** @var list<array{string, string, array<mixed>}> */
            public array $records = [];

            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = [(string) $level, (string) $message, $context];
            }
        };
    }

    public function test_primary_ok_means_fallback_is_never_called(): void
    {
        $primaryCalls = $fallbackCalls = 0;
        $logger = $this->logger();
        $source = new FallbackProductSource(
            $this->source('fakestore', [$this->product('1')], $primaryCalls),
            $this->source('snapshot', [$this->product('2')], $fallbackCalls),
            $logger,
        );

        $products = [...$source->fetchProducts()];

        $this->assertSame('1', $products[0]->externalId);
        $this->assertSame(1, $primaryCalls);
        $this->assertSame(0, $fallbackCalls);
        $this->assertSame('fakestore', $source->origin());
        $this->assertSame([], $logger->records);
    }

    public function test_primary_failure_uses_fallback_and_logs_a_warning(): void
    {
        $primaryCalls = $fallbackCalls = 0;
        $logger = $this->logger();
        $source = new FallbackProductSource(
            $this->source('fakestore', null, $primaryCalls),
            $this->source('snapshot', [$this->product('2')], $fallbackCalls),
            $logger,
        );

        $products = [...$source->fetchProducts()];

        $this->assertSame('2', $products[0]->externalId);
        $this->assertSame(1, $fallbackCalls);
        $this->assertSame('snapshot (fakestore unavailable)', $source->origin());
        $this->assertCount(1, $logger->records);
        $this->assertSame('warning', $logger->records[0][0]);
        $this->assertSame('fakestore down', $logger->records[0][2]['reason']);
    }

    public function test_both_failing_throws_catalogue_unavailable(): void
    {
        $primaryCalls = $fallbackCalls = 0;
        $source = new FallbackProductSource(
            $this->source('fakestore', null, $primaryCalls),
            $this->source('snapshot', null, $fallbackCalls),
            $this->logger(),
        );

        $this->expectException(CatalogueUnavailable::class);
        $this->expectExceptionMessage('fakestore unavailable (fakestore down) and fallback failed (snapshot down)');

        $source->fetchProducts();
    }
}
