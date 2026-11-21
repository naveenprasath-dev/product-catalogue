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
├── hooks/      useApi.ts (loading/data/error for GET calls)
├── components/ ProductTile, ProductGrid, SearchBar, EnquiryForm, CategoryNav, Status (Loading/Empty/Error)
├── pages/      HomePage, CategoryPage, SearchPage, ProductPage, NotFoundPage
├── styles/     tokens.css (theme variables), global.css
├── test/       setup.ts, server.ts, handlers.ts, fixtures.ts
├── App.tsx     routes      main.tsx
```
One component per file, named export, file named after the component. Co-locate `*.test.tsx`.

## TypeScript
- `strict: true`; no `any`, no non-null `!` to silence errors; use `unknown` + narrowing at boundaries.
- Types for API data live only in `api/types.ts`; components import them, never redeclare.
- Props as `type XProps = { … }`; no `React.FC`.

## API client
- `client.ts` exports typed functions (`getFeatured()`, `getCategoryProducts(slug)`, `searchProducts(term)`,
  `getProduct(id)`, `getCategories()`, `createEnquiry(input)`), each unwrapping `{ data }`.
- Non-2xx → throw an `ApiError` class with `status` and optional `fieldErrors: Record<string, string[]>`
  (from 422). Network failure → `ApiError` with `status: 0`.
- Accept an `AbortSignal` on GETs so effects can cancel.

## Data fetching
- `useApi(fn, deps)` returns a discriminated union:
  `{ status: 'loading' } | { status: 'error', error: ApiError } | { status: 'success', data: T }`.
  Abort on unmount/deps change. Pages `switch` on `status` and render Loading / Error / Empty / content.
- No global state library — URL params (`useParams`, `useSearchParams`) are the state. Search term
  lives in `?q=` so results are linkable.

## Routing (React Router)
`/` Home · `/categories/:slug` · `/search?q=` · `/products/:id` (detail + enquiry) · `*` NotFound.
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
- Prices: `Intl.NumberFormat` in one helper (`formatPrice`), not inline.

## Accessibility
Landmarks (`header`, `nav`, `main`), one `h1` per page, labelled inputs, `alt` = product title,
visible focus styles, status messages in `role="status"` / errors in `role="alert"`.

## Dependencies
Only: react, react-dom, react-router, and dev: vite, typescript, eslint, vitest, @testing-library/*, jsdom, msw.
Anything else needs a stated reason (CLAUDE.md).

## Done when
`/run-checks` web part passes: `npm test`, `npm run lint`, `npx tsc --noEmit`.
