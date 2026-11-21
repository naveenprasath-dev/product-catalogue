---
name: admin-page
description: Add or change an /admin page or action in api/ (Inertia + React starter kit) - route in routes/web.php, thin Admin controller, FormRequest, CatalogueService use case, Admin resource, React page with kit layout and token classes, assertInertia tests. Use for any admin panel work.
---

# Admin page

ADMIN_PLAN.md has the design; DECISIONS.md "Admin panel decisions" has what was built.

## Rules
- Every route goes in the `admin.` group in `routes/web.php` (inherits `auth` + `can:admin`). Throttle
  every POST. Only login/logout live outside it; `AdminAccessTest` walks the route table and fails on an
  unprotected route. The whole file returns early (404) unless `ADMIN_ENABLED=true`.
- Controllers in `app/Http/Controllers/Admin` stay thin: FormRequest in `app/Http/Requests/Admin` →
  `CatalogueService` → `Inertia::render('page', [...])` or `redirect()->back()`. No Eloquent, `Http`,
  `Mail` or `ProductSource` in controllers.
- Props go through `App\Http\Resources\Admin\*`. Pass a single resource as `->resolve($request)` (no
  `data` wrapper); a paginated `::collection()` gives `{data, links, meta}`.
- Results of actions are toasts: `Inertia::flash('toast', ['type' => 'success|info|warning|error', 'message' => '…'])`.
  **Catch domain exceptions** (`EnquiryNotSent`, `EnquiryNotResendable`): uncaught, `EnquiryNotSent`
  renders the API's 502 JSON on an admin page.
- Log ids only (`enquiry_id`, `admin_id`), never customer personal data.
- Pages: `resources/js/pages/<name>.tsx`, default export + `Page.layout = { breadcrumbs: [...] }`.
  Typed props in `resources/js/types/admin.ts`; no `any`. URLs only via Wayfinder
  (`@/routes/admin/...`), never hard-coded strings.
- UI from `@/components/ui/*` (shadcn). Token classes only (`bg-primary`, `text-muted-foreground`,
  `bg-success`, `bg-warning`, `bg-destructive`); never raw palette classes or hex. New colours → a
  variable in `resources/css/app.css` (`:root` + `.dark` + `@theme`). Admin styling never touches `web/`.
- New nav item → `mainNavItems` in `components/app-sidebar.tsx` (`matchPrefix: true` for sections with sub-pages).

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
