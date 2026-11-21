<?php

declare(strict_types=1);

namespace App\Providers;

use App\Catalogue\CatalogueService;
use App\Catalogue\Contracts\CurrencyRateSource;
use App\Catalogue\Contracts\CurrencyRepository;
use App\Catalogue\Contracts\EnquiryRepository;
use App\Catalogue\Contracts\ProductRepository;
use App\Catalogue\Contracts\ProductSource;
use App\Catalogue\Currency\CurrencyService;
use App\Catalogue\Currency\FrankfurterRateSource;
use App\Catalogue\Currency\RateSyncer;
use App\Catalogue\Currency\SelectedCurrency;
use App\Catalogue\Repositories\EloquentCurrencyRepository;
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

        $this->registerCurrencies();
    }

    private function registerCurrencies(): void
    {
        $this->app->bind(CurrencyRepository::class, EloquentCurrencyRepository::class);

        // Live rates: only RateSyncer (currencies:sync-rates, admin "Sync live rates") uses this.
        $this->app->bind(CurrencyRateSource::class, fn (Container $app): CurrencyRateSource => new FrankfurterRateSource(
            http: $app->make(HttpFactory::class),
            baseUrl: (string) config('currency.rates_url'),
            timeoutSeconds: (int) config('currency.rates_timeout'),
            retries: (int) config('currency.rates_retries'),
        ));

        $this->app->bind(RateSyncer::class, fn (Container $app): RateSyncer => new RateSyncer(
            source: $app->make(CurrencyRateSource::class),
            currencies: $app->make(CurrencyRepository::class),
            baseCode: (string) config('currency.base'),
        ));

        $this->app->bind(CurrencyService::class, fn (Container $app): CurrencyService => new CurrencyService(
            currencies: $app->make(CurrencyRepository::class),
            syncer: $app->make(RateSyncer::class),
            baseCode: (string) config('currency.base'),
        ));

        // One per request: the currency chosen with ?currency= (ProductQueryRequest), read by ProductResource.
        $this->app->scoped(SelectedCurrency::class);
    }
}
