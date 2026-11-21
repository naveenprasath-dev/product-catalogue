<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Catalogue\Currency\CurrencyService;
use App\Catalogue\Exceptions\RatesUnavailable;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;

final class SyncCurrencyRates extends Command
{
    protected $signature = 'currencies:sync-rates
        {--if-never-updated : Skip when any rate has already been synced or edited (used on container start)}';

    protected $description = 'Refresh the exchange rates of enabled currencies from the live rate source (Frankfurter)';

    public function handle(CurrencyService $currencies): int
    {
        if ($this->option('if-never-updated') && $currencies->ratesUpdatedAt() !== null) {
            $this->info('Currency rates already set; skipping (--if-never-updated).');

            return self::SUCCESS;
        }

        try {
            $result = $currencies->syncRates();
        } catch (RatesUnavailable $e) {
            $this->error('Currency rates unavailable, nothing was changed: '.$e->getMessage());

            return self::FAILURE;
        } catch (QueryException $e) {
            report($e);
            $this->error('Currency rate sync failed while writing to the database; nothing was changed.');

            return self::FAILURE;
        }

        $line = "{$result->updated} currency rates updated from {$currencies->rateSourceName()}.";
        if ($result->missing !== []) {
            $line .= ' No rate for: '.implode(', ', $result->missing).'.';
        }
        $this->info($line);

        return self::SUCCESS;
    }
}
