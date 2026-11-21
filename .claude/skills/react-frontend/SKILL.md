---
name: react-frontend
description: General React + TypeScript conventions for web/ - project structure, typed API client and data-fetching hook, routing, forms, state, theme tokens/CSS, accessibility, dependencies. Use whenever writing or reviewing code in web/ (pair with web-page for adding a page, react-testing for tests).
---

# React + TypeScript conventions

CLAUDE.md has the rules; `web-page` has the add-a-page checklist. This skill is the house style.

## Structure
```
web/src/
├── api/        client.ts (only place with VITE_API_URL + fetch), types.ts (mirrors PLAN.md "API contract")
├── auth/       AuthContext.tsx (AuthProvider), context.ts, useAuth.ts, redirect.ts (safeNext/authPath), messages.ts
├── currency/   CurrencyProvider.tsx, context.ts, useCurrency.ts, storage.ts (saved choice, guarded localStorage)
├── hooks/      useApi.ts (loading/data/error for GET calls), useProductApi.ts (useApi + selected currency),
│               useCategories.ts
├── lib/        format.ts (formatPrice, formatRating)
├── components/ ProductTile, ProductGrid, SearchBar, EnquiryForm, CategoryNav, UserMenu, CurrencySelect,
│               Footer, Status (Loading/Empty/Error), icons/
├── pages/      HomePage, CategoryPage, SearchPage, ProductPage, LoginPage, RegisterPage, NotFoundPage
├── styles/     tokens.css (theme variables), global.css
├── test/       setup.ts, server.ts, handlers.ts, fixtures.ts, render.tsx
├── App.tsx     routes      main.tsx
```
One component per file, named export, file named after the component. Co-locate `*.test.tsx`.

## TypeScript
- `strict: true`; no `any`, no non-null `!` to silence errors; use `unknown` + narrowing at boundaries.
- Types for API data live only in `api/types.ts`; components import them, never redeclare.
- Props as `type XProps = { … }`; no `React.FC`.

## API client
- `client.ts` exports typed functions (`getFeatured(opts)`, `getCategoryProducts(slug, opts)`,
  `searchProducts(term, opts)`, `getProduct(id, opts)`, `getCategories(signal)`, `getCurrencies(signal)`,
  `createEnquiry(input)`), each unwrapping `{ data }` (`getCurrencies` returns `{ data, meta }`).
  Product functions take `opts: ProductRequestOptions = { currency?, signal? }`; `currency` is added as
  `?currency=` only when set (omitted = the server's default currency).
- Non-2xx → throw an `ApiError` class with `status` and optional `fieldErrors: Record<string, string[]>`
  (from 422). Network failure → `ApiError` with `status: 0`.
- Accept an `AbortSignal` on GETs so effects can cancel.
- Auth (Sanctum SPA cookies): every request sends `credentials: 'include'`. Before a
  POST/PUT/PATCH/DELETE, `client.ts` fetches `/sanctum/csrf-cookie` if no `XSRF-TOKEN` cookie exists
  and sends its value as `X-XSRF-TOKEN`; a 419 refreshes the cookie and retries once. The CSRF URL
  (`CSRF_COOKIE_URL`) is derived from `VITE_API_URL`, so `client.ts` stays the only module with the URL.
- **No tokens or user data in localStorage/sessionStorage.** The session is an HttpOnly cookie.
  localStorage is allowed **only for display preferences** (today: the selected currency,
  `catalogue.currency` in `currency/storage.ts`), never for auth or personal data. Wrap every access
  in try/catch (private mode, blocked storage) and fall back to the server default.

## Auth state
- `AuthProvider` (in `main.tsx` and the test `render.tsx`) calls `me()` once: 401 → `guest` (not an
  error). Read it with `useAuth()` → `{ user, status: 'loading' | 'guest' | 'authenticated', login,
  register, logout }`. Never call the auth functions in `client.ts` from components directly.
- Redirects after login/register use `?next=` only through `safeNext()` / `authPath()`
  (`auth/redirect.ts`): same-app paths only (`/…`, not `//…`, not `/login`/`/register`), so it can't
  be an open redirect.
- Identity is never sent in a body (e.g. no user id on enquiries); the API reads the session.

## Data fetching
- `useApi(fn, deps)` returns a discriminated union:
  `{ status: 'loading' } | { status: 'error', error: ApiError } | { status: 'success', data: T }`.
  Abort on unmount/deps change. Pages `switch` on `status` and render Loading / Error / Empty / content.
- **Product data goes through `useProductApi((signal, currency) => getProduct(id, { currency, signal }), [id])`**,
  not plain `useApi`. It passes the selected currency, re-fetches when it changes, waits while a saved
  currency is being checked (no flash of the wrong currency), and on a 422 on `currency` (disabled
  meanwhile) falls back to the default. ESLint checks its deps (`additionalHooks: '^(useApi|useProductApi)$'`).
  Non-product GETs (`getCategories`) keep `useApi`.
- No global state library — URL params (`useParams`, `useSearchParams`) are the state. Search term
  lives in `?q=` so results are linkable. Two small contexts hold app-wide state: auth (`useAuth()`)
  and currency (`useCurrency()` → `{ currencies, status, selected, requestCurrency, ready, setCurrency,
  rejectCurrency }`, from `CurrencyProvider` in `main.tsx` and the test `render.tsx`).

## Currency
- The server converts; the client **never** multiplies by a rate. Each `Product` carries `price` and its
  `currency` code; render with `formatPrice(product.price, product.currency)`.
- `CurrencySelect` (header) renders only when `/currencies` loaded (`status === 'ready'`); when it
  failed, the dropdown is hidden and prices show in the server default.

## Routing (React Router)
`/` Home · `/categories/:slug` · `/search?q=` · `/products/:id` (detail + enquiry) · `/login` ·
`/register` · `*` NotFound.
404 from the API renders the not-found message inside the page, not a crash.

## Forms (EnquiryForm)
- Controlled inputs, `<form noValidate onSubmit>`; client checks (required name/email, email format,
  message ≤ 2000) for UX only.
- States: `idle | submitting | success | failed`. Disable the button while submitting.
- 422 → show `fieldErrors` under each field (`aria-describedby`, `aria-invalid`). 502/0 → retry
  message, keep values. 429 → "too many requests, try again shortly". Success → Thank-you.
- Honeypot `website` input: visually hidden via a class, `tabIndex={-1}`, `autoComplete="off"`, `aria-hidden`.

## Styling
- All colours, spacing, radii, fonts, shadows as CSS variables in `styles/tokens.css` (`--color-primary`,
  `--space-3`, …). Components use only `var(--…)`. A rebrand = editing tokens.css.
- Plain CSS files per component or BEM-ish class names; mobile-first responsive grid
  (`grid-template-columns: repeat(auto-fill, minmax(220px, 1fr))`).
- Prices: `formatPrice(amount, currencyCode)` in `lib/format.ts` (`Intl.NumberFormat`, fixed `en-US`
  locale, cached per code; a malformed code falls back to `"12.34 XYZ"`), never inline. It never converts.
- **Small screens** (≤ 480px header): the small-screen rules sit at the **end** of `global.css`,
  because a later rule with the same specificity wins the cascade (an earlier `display: none` lost to
  `.button`). Hide text visually (visually-hidden class), not with `display: none`, when it is an
  accessible name. Verify in a real browser at 320/375/414px with bounding boxes (brand vs account vs
  search: no overlap, one row, no horizontal overflow), not by eye.

## Accessibility
Landmarks (`header`, `nav`, `main`), one `h1` per page, labelled inputs, `alt` = product title,
visible focus styles, status messages in `role="status"` / errors in `role="alert"`.

## Dependencies
Only: react, react-dom, react-router, and dev: vite, typescript, eslint (+ typescript-eslint and plugins),
vitest, @testing-library/*, jsdom, msw.
Anything else needs a stated reason (CLAUDE.md).

## Done when
`/run-checks` web part passes: `npm test` (single run), `npm run lint`, `npx tsc --noEmit`.

