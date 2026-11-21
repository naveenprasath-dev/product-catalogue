<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Replaces `guest` on /admin/login: a signed-in admin goes to the dashboard. A signed-in shop customer
 * still gets the login form (so staff signed in to the shop as a customer can switch to their admin
 * account); `guest` sent both to `/`, which is a 404 in this app.
 */
final class RedirectAdminsFromLogin
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->is_admin) {
            return redirect()->route('admin.dashboard');
        }

        return $next($request);
    }
}
