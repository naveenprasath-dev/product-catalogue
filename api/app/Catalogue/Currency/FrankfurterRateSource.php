<?php

declare(strict_types=1);

namespace App\Catalogue\Currency;

use App\Catalogue\Contracts\CurrencyRateSource;
use App\Catalogue\Exceptions\RatesUnavailable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\RequestException;

/**
 * The ONLY class that calls Frankfurter (https://frankfurter.dev, open source, no API key, ECB rates).
 * v2: GET {url}/rates?base=USD → [{"date":"…","base":"USD","quote":"EUR","rate":0.91}, …].
 * All quotes are fetched (not `quotes=`): v2 rejects the whole request if one code is unknown.
 */
final readonly class FrankfurterRateSource implements CurrencyRateSource
{
    public function __construct(
        private HttpFactory $http,
        private string $baseUrl,
        private int $timeoutSeconds = 10,
        private int $retries = 2,
    ) {}

    public function latest(string $base): array
    {
        try {
            $response = $this->http
                ->acceptJson()
                ->timeout($this->timeoutSeconds)
                ->retry($this->retries + 1, 200)
                ->get($this->baseUrl.'/rates', ['base' => $base]);
        } catch (ConnectionException|RequestException $e) {
            throw RatesUnavailable::because('Frankfurter request failed: '.$e->getMessage(), $e);
        }

        try {
            $payload = json_decode($response->body(), true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            throw RatesUnavailable::because('Frankfurter returned invalid JSON.', $e);
        }

        if (! is_array($payload) || ! array_is_list($payload) || $payload === []) {
            throw RatesUnavailable::because('Frankfurter returned no rates.');
        }

        $rates = [];
        foreach ($payload as $item) {
            if (! is_array($item)
                || ($item['base'] ?? null) !== $base
                || ! is_string($item['quote'] ?? null)
                || ! (is_int($item['rate'] ?? null) || is_float($item['rate'] ?? null))
                || $item['rate'] <= 0) {
                throw RatesUnavailable::because('Frankfurter returned an unexpected rate entry.');
            }
            $rates[strtoupper($item['quote'])] = (float) $item['rate'];
        }

        return $rates;
    }

    public function name(): string
    {
        return 'Frankfurter';
    }
}
