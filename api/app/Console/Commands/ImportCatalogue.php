<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Catalogue\Exceptions\CatalogueUnavailable;
use App\Catalogue\Import\CatalogueImporter;
use App\Catalogue\Sources\ProductSourceFactory;
use App\Models\Product;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use InvalidArgumentException;

final class ImportCatalogue extends Command
{
    protected $signature = 'catalogue:import
        {--if-empty : Skip when the products table already has rows}
        {--source= : Override CATALOGUE_SOURCE for this run (fakestore|file)}';

    protected $description = 'Import products into the database from the configured product source (Fake Store, with the JSON snapshot as fallback)';

    public function handle(CatalogueImporter $importer, ProductSourceFactory $sources): int
    {
        if ($this->option('if-empty') && Product::query()->exists()) {
            $this->info('Catalogue already imported; skipping (--if-empty).');

            return self::SUCCESS;
        }

        $sourceOption = $this->option('source');
        $sourceName = is_string($sourceOption) && $sourceOption !== ''
            ? $sourceOption
            : (string) config('catalogue.source');

        try {
            $source = $sources->make($sourceName, (bool) config('catalogue.fallback_to_file'));
            $result = $importer->import($source);
        } catch (CatalogueUnavailable|InvalidArgumentException $e) {
            $this->error('Catalogue import failed, nothing was changed: '.$e->getMessage());

            return self::FAILURE;
        } catch (QueryException $e) {
            // The import transaction was rolled back. Report briefly; the SQL detail goes to the log.
            report($e);
            $this->error('Catalogue import failed while writing to the database; nothing was changed.');

            return self::FAILURE;
        }

        $this->info("{$result->products} products, {$result->categories} categories imported from {$result->origin}.");

        return self::SUCCESS;
    }
}
