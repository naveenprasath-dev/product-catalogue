---
name: admin-page
description: Add or change an /admin page or action in api/ (Inertia + React starter kit) - route in routes/web.php, thin Admin controller, FormRequest, CatalogueService or CurrencyService use case, Admin resource, React page with kit layout and token classes, assertInertia tests. Use for any admin panel work.
---

# Admin page

ADMIN_PLAN.md has the design; DECISIONS.md "Admin panel decisions" and "Admin QA fixes" have what was
built; docs/qa/ADMIN_QA.md has the QA cases a change must not regress.

## Rules
- Every route goes in the `admin.` group in `routes/web.php` (inherits `AdminSecurityHeaders`, `auth`,
  `can:admin`, `EncryptHistory`). Throttle every POST/PUT/DELETE (writes 30/min; actions that call a third
  party or send mail 6/min). Only login/logout live outside it, in their own group that also has
  `AdminSecurityHeaders` + `EncryptHistory`. `AdminAccessTest` walks the route table and fails on an
  unprotected route; `SecurityHeadersTest` fails on an admin route without both middlewares.
  The whole file returns early (404) unless `ADMIN_ENABLED=true`.
- Security (from the QA pass, don't undo): `AdminSecurityHeaders` sets `X-Frame-Options: DENY`,
  `CSP frame-ancestors 'none'`, `Cache-Control: no-store, private`, `nosniff`, `Referrer-Policy:
  same-origin` on every admin response (prioritised before `auth`, so redirects and 403s get them);
  Inertia history is encrypted (`EncryptHistory`) and cleared on logout (`AdminLogoutResponse` →
  `Inertia::clearHistory()`), so Back after logout shows nothing.
- Controllers in `app/Http/Controllers/Admin` stay thin: FormRequest in `app/Http/Requests/Admin` →
  a use case on a service → `Inertia::render('page', [...])` or `redirect()->back()`. Products and
  enquiries: `CatalogueService`. **Currencies: `App\Catalogue\Currency\CurrencyService`**, which holds the
  currency rules (base fixed, one enabled default) and throws `CurrencyChangeRejected` → field error
  (forms) or error toast (actions). No Eloquent, `Http`, `Mail`, `ProductSource` or `CurrencyRateSource`
  in controllers.
- Props go through `App\Http\Resources\Admin\*`. Pass a single resource as `->resolve($request)` (no
  `data` wrapper); a paginated `::collection()` gives `{data, links, meta}`.
- Results of actions are toasts: `Inertia::flash('toast', ['type' => 'success|info|warning|error', 'message' => '…'])`.
  Toasts last 6s (`components/ui/sonner.tsx`, hover pauses), long enough for slow actions like a sync.
  **Catch domain exceptions** (`EnquiryNotSent`, `EnquiryNotResendable`, `CurrencyChangeRejected`,
  `RatesUnavailable`): uncaught, `EnquiryNotSent` renders the API's 502 JSON on an admin page.
- List filters in a FormRequest are read leniently (unknown value → ignored, always 200), not
  validated: a failed GET validation silently redirects back (QA B5).
- Log ids only (`enquiry_id`, `admin_id`), never customer personal data.
- Pages: `resources/js/pages/<name>.tsx`, default export + `Page.layout = { breadcrumbs: [...] }`.
  Typed props in `resources/js/types/admin.ts`; no `any`. URLs only via Wayfinder
  (`@/routes/admin/...`), never hard-coded strings.
- UI from `@/components/ui/*` (shadcn). Token classes only (`bg-primary`, `text-muted-foreground`,
  `bg-success`, `bg-warning`, `bg-destructive`); never raw palette classes or hex. New colours → a
  variable in `resources/css/app.css` (`:root` + `.dark` + `@theme`). Admin styling never touches `web/`.
- New nav item → `mainNavItems` in `components/app-sidebar.tsx` (`matchPrefix: true` for sections with sub-pages).
- Small screens: a list table must not scroll sideways at 375px. Render stacked cards under `sm`
  (`grid gap-3 sm:hidden`) and the table from `sm` up (see `pages/enquiries/index.tsx`). Check at
  375px in a browser.

## Steps
1. Feature test first in `tests/Feature/Admin/` extending `AdminTestCase` (withoutVite, RefreshDatabase):
   `assertInertia(fn (Assert $p) => $p->component('x')->where(...))`, guest → redirect `/admin/login`,
   customer → 403, `Mail::fake()` / `useFailingMailer()` / `Http::fake()`,
   `assertInertiaFlash('toast.type', 'success')` for actions.
2. Service method in `app/Catalogue/**` (with its own test) → route → FormRequest → controller → resource.
3. Page + types + nav.
4. `docker compose exec api npm run build` (regenerates Wayfinder types), then `npm run types:check`,
   `npm run check`, `php artisan test`, `./vendor/bin/pint --test`.

## Done when
All of the above are green, the route is covered by `AdminAccessTest`, and docs (PLAN/README/DECISIONS)
mention the new page.
