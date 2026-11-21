<?php

declare(strict_types=1);

use App\Catalogue\Exceptions\CurrencyNotFound;
use App\Catalogue\Exceptions\EnquiryNotFound;
use App\Catalogue\Exceptions\EnquiryNotSent;
use App\Http\Middleware\AdminSecurityHeaders;
use App\Http\Middleware\HandleInertiaRequests;
use Illuminate\Contracts\Auth\Middleware\AuthenticatesRequests;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\ThrottleRequestsException;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

// JSON errors for the API (and anything that asks for JSON); normal web responses for /admin.
$wantsJson = fn (Request $request): bool => $request->is('api/*') || $request->expectsJson();

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        // The admin panel's routes (Inertia, under /admin). The file decides whether the panel is enabled.
        web: file_exists(__DIR__.'/../routes/web.php') ? __DIR__.'/../routes/web.php' : null,
        api: __DIR__.'/../routes/api.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Requests from the shop origin (SANCTUM_STATEFUL_DOMAINS) get the session + CSRF, so cookie
        // login works. Requests from anywhere else (curl, tests, other origins) stay stateless.
        $middleware->statefulApi();

        // Inertia only on the web (admin) group; the api group is untouched.
        $middleware->web(append: [
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Only /admin uses the redirect; API auth failures render as 401 JSON (see $wantsJson).
        $middleware->redirectGuestsTo('/admin/login');
        // `guest` fallback (default `/` is a 404 here). /admin/login itself uses RedirectAdminsFromLogin.
        $middleware->redirectUsersTo('/admin');

        // Admin security headers wrap auth/can, so guest redirects and 403s get them too
        // (priority sorting would otherwise run `auth` first).
        $middleware->prependToPriorityList(
            before: AuthenticatesRequests::class,
            prepend: AdminSecurityHeaders::class,
        );
    })
    ->withExceptions(function (Exceptions $exceptions) use ($wantsJson): void {
        $exceptions->shouldRenderJsonWhen($wantsJson);

        // A domain "not found" is a plain 404 (JSON for the API, the normal 404 page for /admin).
        $exceptions->map(EnquiryNotFound::class, fn (EnquiryNotFound $e): NotFoundHttpException => new NotFoundHttpException($e->getMessage(), $e));
        $exceptions->map(CurrencyNotFound::class, fn (CurrencyNotFound $e): NotFoundHttpException => new NotFoundHttpException($e->getMessage(), $e));

        // Unknown routes, unknown ids and model lookups all give the same short 404 body (API only).
        $exceptions->render(function (NotFoundHttpException|ModelNotFoundException $e, Request $request) use ($wantsJson) {
            return $wantsJson($request) ? response()->json(['message' => 'Not found.'], 404) : null;
        });

        // A throttled admin login is a normal form error under the email field, not Inertia's 429 modal (QA B2).
        $exceptions->render(function (ThrottleRequestsException $e, Request $request) {
            if (! $request->routeIs('login.store')) {
                return null;
            }
            $seconds = max(1, (int) ($e->getHeaders()['Retry-After'] ?? 60));

            return redirect()->route('login')
                ->withInput($request->only('email', 'remember'))
                ->withErrors(['email' => "Too many attempts. Try again in {$seconds} seconds."]);
        });

        // The enquiry is stored (status=failed) but the email could not be sent: tell the client to retry.
        $exceptions->render(function (EnquiryNotSent $e, Request $request) {
            return response()->json([
                'message' => 'Your enquiry could not be sent right now. Please try again in a moment.',
            ], 502);
        });

        // Already handled (marked failed + logged by CatalogueService).
        $exceptions->dontReport(EnquiryNotSent::class);
    })->create();
