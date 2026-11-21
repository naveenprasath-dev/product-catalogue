<?php

declare(strict_types=1);

namespace App\Http\Responses;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LogoutResponse;

/**
 * After admin logout: back to the login page and clear the browser's Inertia history, so Back can't
 * show enquiry personal data. (Fortify has already invalidated the session, so the flag lands in the new one.)
 */
final class AdminLogoutResponse implements LogoutResponse
{
    /** @param  Request  $request */
    public function toResponse($request): RedirectResponse
    {
        Inertia::clearHistory();

        return redirect()->route('login');
    }
}
