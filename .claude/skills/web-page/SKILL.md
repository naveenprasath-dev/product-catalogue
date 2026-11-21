---
name: web-page
description: Add or change a page or component in the React + TypeScript client (web/) - routing, API calls via client.ts, theme tokens, loading/empty/error states, Vitest + RTL + MSW tests. Use for any frontend work.
---

# Add or change a web page or component

PLAN.md §5 lists the pages and components; TECH_STACK.md §4 lists the stack.

## Rules
- **API access only through `web/src/api/client.ts`.** Add a typed function there (for example
  `getCategoryProducts(slug)`), with response types in `src/api/types.ts`. Components never call `fetch`
  and never read `VITE_API_URL`. Never call Fake Store.
- TypeScript strict mode, no `any`. Type API responses to match `ProductResource`.
- **Styling** uses CSS variables from the theme tokens file (`src/styles/tokens.css`). No hex colours,
  font names or brand values inside components.
- Every page that loads data has **loading, empty and error** states.
- Routing uses React Router; an unknown route shows a not-found page.
- Forms: client-side validation is for UX only, and the API's 422 field errors are shown next to the
  matching fields. After a 502, show a retry message and keep what the user typed.
- Accessibility basics: every input has a label, every image has `alt`, buttons are real `<button>` elements.
- **Auth:** read the user with `useAuth()` (from `AuthProvider`); a page must work for guests too.
  `client.ts` handles cookies (`credentials: 'include'`) and CSRF; never store tokens or user data in
  localStorage. Links to `/login`/`/register` carry `?next=` via `authPath()`, and reading `next`
  goes through `safeNext()` (no open redirects). Never send a user id in a request body.
- **Small screens:** header/layout overrides for ≤ 480px go at the end of `global.css` (a later rule
  wins the cascade). Check at 320/375/414px with bounding boxes (no overlap, no horizontal overflow).


## Tests
- `*.test.tsx` next to the page or component. Use React Testing Library queries by role or label text.
- Mock the API with MSW handlers (`src/test/handlers.ts`), so the real `client.ts` code runs.
- At minimum, cover the happy path plus the error state; for forms, also cover validation and the
  "Thank you" / retry results.

## Done when
`docker compose exec web npm test`, `npm run lint` and `npx tsc --noEmit` all pass.
