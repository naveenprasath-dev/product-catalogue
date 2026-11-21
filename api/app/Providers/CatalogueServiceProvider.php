<?php

declare(strict_types=1);

namespace App\Providers;

use App\Catalogue\CatalogueService;
use App\Catalogue\Contracts\EnquiryRepository;
use App\Catalogue\Contracts\ProductRepository;
use App\Catalogue\Contracts\ProductSource;
use App\Catalogue\Repositories\EloquentEnquiryRepository;
use App\Catalogue\Repositories\EloquentProductRepository;
use App\Catalogue\Sources\FakeStoreProductMapper;
use App\Catalogue\Sources\FakeStoreProductSource;
use App\Catalogue\Sources\JsonFileProductSource;
use App\Catalogue\Sources\ProductSourceFactory;
use Illuminate\Contracts\Container\Container;
use Illuminate\Contracts\Mail\Factory as MailFactory;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

/** The one place that decides which implementations the catalogue uses. */
final class CatalogueServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ProductRepository::class, EloquentProductRepository::class);
        $this->app->bind(EnquiryRepository::class, EloquentEnquiryRepository::class);

        $this->app->bind(FakeStoreProductSource::class, fn (Container $app): FakeStoreProductSource => new FakeStoreProductSource(
            http: $app->make(HttpFactory::class),
            mapper: $app->make(FakeStoreProductMapper::class),
            baseUrl: (string) config('catalogue.source_url'),
            timeoutSeconds: (int) config('catalogue.source_timeout'),
            retries: (int) config('catalogue.source_retries'),
        ));

        $this->app->bind(JsonFileProductSource::class, function (Container $app): JsonFileProductSource {
            $path = (string) config('catalogue.snapshot_path');

            return new JsonFileProductSource(
                mapper: $app->make(FakeStoreProductMapper::class),
                path: str_starts_with($path, '/') ? $path : base_path($path),
            );
        });

        // Bound per resolution (not singleton) so it always reflects the current config.
        $this->app->bind(ProductSource::class, fn (Container $app): ProductSource => $app->make(ProductSourceFactory::class)->make(
            (string) config('catalogue.source'),
            (bool) config('catalogue.fallback_to_file'),
        ));

        $this->app->bind(CatalogueService::class, fn (Container $app): CatalogueService => new CatalogueService(
            products: $app->make(ProductRepository::class),
            enquiries: $app->make(EnquiryRepository::class),
            mail: $app->make(MailFactory::class),
            logger: $app->make(LoggerInterface::class),
            featuredIds: array_values(array_map('strval', (array) config('catalogue.featured_ids'))),
            topRatedPerCategory: (int) config('catalogue.top_rated_per_category'),
            salesInbox: (string) config('catalogue.sales_inbox'),
        ));
    }
}
