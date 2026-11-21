<?php

declare(strict_types=1);

namespace App\Catalogue\Sources;

use App\Catalogue\Contracts\ProductSource;
use App\Catalogue\Exceptions\CatalogueUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;

/** The ONLY class that calls the Fake Store API. Used by the import, never during an API request. */
final readonly class FakeStoreProductSource implements ProductSource
{
    public function __construct(
        private HttpFactory $http,
        private FakeStoreProductMapper $mapper,
        private string $baseUrl,
        private int $timeoutSeconds = 10,
        private int $retries = 2,
    ) {}

    public function fetchProducts(): iterable
    {
        try {
            $response = $this->http
                ->acceptJson()
                ->timeout($this->timeoutSeconds)
                ->retry($this->retries + 1, 200)
                ->get($this->baseUrl.'/products');
        } catch (ConnectionException|RequestException $e) {
            throw CatalogueUnavailable::because('Fake Store request failed: '.$e->getMessage(), $e);
        }

        try {
            $payload = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw CatalogueUnavailable::because('Fake Store returned invalid JSON.', $e);
        }

        return $this->mapper->mapAll($payload);
    }

    public function origin(): string
    {
        return 'fakestore';
    }
}
