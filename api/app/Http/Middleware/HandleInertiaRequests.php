<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

/** From the starter kit. Runs on the web group only (admin panel); the /api group never sees it. */
class HandleInertiaRequests extends Middleware
{
    /** resources/views/app.blade.php */
    protected $rootView = 'app';

    /**
     * Props shared with every admin page. Only the fields listed here reach the browser.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return [
            ...parent::share($request),
            'name' => 'Admin',
            'auth' => [
                'user' => $user === null ? null : [
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
            'sidebarOpen' => ! $request->hasCookie('sidebar_state') || $request->cookie('sidebar_state') === 'true',
        ];
    }
}
