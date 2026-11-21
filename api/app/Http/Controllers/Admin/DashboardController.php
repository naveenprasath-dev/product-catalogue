<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Catalogue\CatalogueService;
use App\Http\Controllers\Controller;
use App\Http\Resources\Admin\DashboardStatsResource;
use Inertia\Inertia;
use Inertia\Response;

final class DashboardController extends Controller
{
    public function __construct(private readonly CatalogueService $catalogue) {}

    public function __invoke(): Response
    {
        return Inertia::render('dashboard', [
            'stats' => new DashboardStatsResource($this->catalogue->dashboardStats()),
        ]);
    }
}
