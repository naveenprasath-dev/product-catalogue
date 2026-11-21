---
name: react-testing
description: Write or fix frontend tests in web/ with Vitest, React Testing Library, user-event and MSW - test setup, handlers built from the API contract, loading/empty/error/form cases. Use whenever adding or debugging a web test.
---

# Frontend tests (Vitest + RTL + MSW)

Required cases: PLAN.md §7 "web tests".

## Setup
- `vite.config.ts` → `test: { environment: 'jsdom', globals: true, setupFiles: './src/test/setup.ts' }`.
- `src/test/setup.ts`:
  ```ts
  import '@testing-library/jest-dom/vitest';
  import { server } from './server';
  // MSW v3 renamed v2's `onUnhandledRequest` to `onUnhandledFrame`; the old key is silently ignored.
  beforeAll(() => server.listen({ onUnhandledFrame: 'error' }));
  afterEach(() => server.resetHandlers());
  afterAll(() => server.close());
  ```
- `src/test/server.ts`: `setupServer(...handlers)` from `msw/node`.
- `src/test/handlers.ts`: MSW happy-path handlers for every endpoint, URLs built from the same
  base URL `client.ts` uses (set `VITE_API_URL` in the test env, e.g. `.env.test`), returning
  `HttpResponse.json({ data: … })` with fixtures from `fixtures.ts` typed against `api/types.ts`.
- A `renderWithRouter(ui, { route })` helper wrapping `MemoryRouter` + `Routes`.

## Patterns
```ts
// Override per test for error/empty cases
server.use(http.get(`${API}/products/featured`, () => HttpResponse.json({ message: 'x' }, { status: 500 })));
server.use(http.post(`${API}/enquiries`, () =>
  HttpResponse.json({ message: 'invalid', errors: { email: ['The email field must be a valid email address.'] } }, { status: 422 })));

// Interact like a user
const user = userEvent.setup();
await user.type(screen.getByLabelText(/email/i), 'bad');
await user.click(screen.getByRole('button', { name: /send enquiry/i }));

// Wait for async UI
expect(await screen.findByRole('alert')).toHaveTextContent(/try again/i);
expect(screen.getByLabelText(/name/i)).toHaveValue('Ada');   // values kept after 502
```
- Query priority: `getByRole` > `getByLabelText` > `getByText`; avoid test ids and class selectors.
- Assert the loading state first (`getByRole('status')`), then the result with `findBy…`.
- To assert a request was (not) sent, capture it in the handler (`let body; … body = await request.json()`).

## Rules
- Never mock `client.ts` or `fetch` directly — MSW keeps the real client under test.
- `onUnhandledFrame: 'error'` stays on; an unexpected call means a missing handler or a bug.
- No `act()` warnings left in output; no arbitrary timeouts.

## Run
```bash
docker compose exec web npm test -- --run
docker compose exec web npm test -- --run src/pages/ProductPage.test.tsx
```
