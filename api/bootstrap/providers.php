<?php

declare(strict_types=1);

use App\Providers\AppServiceProvider;
use App\Providers\CatalogueServiceProvider;
use App\Providers\FortifyServiceProvider;

return [
    AppServiceProvider::class,
    CatalogueServiceProvider::class,
    FortifyServiceProvider::class,
];
