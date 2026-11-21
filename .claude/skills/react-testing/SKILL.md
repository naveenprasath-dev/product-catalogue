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
  import { clearXsrfCookie } from './handlers';
  import { server } from './server';

  // MSW v3 renamed v2's `onUnhandledRequest` to `onUnhandledFrame`; the old key is silently ignored.
  beforeAll(() => server.listen({ onUnhandledFrame: 'error' }));
  // localStorage.clear(): the saved currency must not leak between tests.
  afterEach(() => { server.resetHandlers(); clearXsrfCookie(); localStorage.clear(); });
  afterAll(() => server.close());
  ```
- `src/test/server.ts`: `setupServer(...handlers)` from `msw/node`.
- `src/test/handlers.ts`: MSW happy-path handlers for every endpoint, URLs built from the same
  base URL `client.ts` uses (set `VITE_API_URL` in the test env, e.g. `.env.test`), returning
  `HttpResponse.json({ data: … })` with fixtures from `fixtures.ts` typed against `api/types.ts`.
  Auth defaults: `GET /auth/me` → **401** (a guest; existing page tests stay guest-only), logout → 401,
  login/register → the `ada` fixture. `CSRF_URL` (`/sanctum/csrf-cookie`) → 204 and sets
  `document.cookie` itself (MSW doesn't write cookies under Node); `TEST_XSRF_TOKEN` is its value.
  Currency defaults: `GET /currencies` → `currencyList` (USD default, EUR, GBP from `fixtures.ts`).
  Product handlers come from `productHandlers()` and honour `?currency=` like the API: absent → the
  fixtures as they are (USD); an enabled code → prices converted with `inCurrency(product, currency)`;
  anything else → 422 `errors.currency`.
- `signedIn(user = ada)` returns handlers for a signed-in session: `server.use(...signedIn())`.
- `productHandlers(enabled)` simulates a currency disabled after the list loaded:
  `server.use(...productHandlers([usd]))` makes `?currency=EUR` a 422 while `/currencies` still lists EUR.
- `renderWithRouter(ui = <App />, { route })` in `src/test/render.tsx` wraps `MemoryRouter`,
  **`AuthProvider` and `CurrencyProvider`**, so `useAuth()` and `useCurrency()` work in every test.
- To start with a saved currency, `localStorage.setItem(CURRENCY_STORAGE_KEY, 'EUR')`
  (from `currency/storage.ts`) before rendering. Assert the formatted price the mock server returns
  (e.g. `€51.51` for 55.99 × the fixture rate 0.92), with a comment showing the arithmetic.
- To assert which currency was requested, record `new URL(request.url).searchParams.get('currency')`
  in a handler (see `currency/currency.test.tsx`).

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
- **Handler order:** within one `server.use(...)` call (and across calls, newest first), the **first
  matching handler wins**. `signedIn()` includes `GET /auth/me` and `POST /auth/logout`, so a
  per-test override of those goes **before** the spread:
  ```ts
  server.use(
    http.post(`${API}/auth/logout`, () => HttpResponse.json({ message: 'x' }, { status: 500 })),
    ...signedIn(),
  );
  ```
- CSRF/credentials: assert the `X-XSRF-TOKEN` header in the handler (`request.headers.get('X-XSRF-TOKEN')`
  equals `TEST_XSRF_TOKEN`). MSW's intercepted `Request` drops `credentials`, so check
  `credentials: 'include'` with a pass-through `vi.spyOn(globalThis, 'fetch')` (see `api/client.test.ts`).

## Rules
- Never mock `client.ts` or `fetch` directly — MSW keeps the real client under test.
- `onUnhandledFrame: 'error'` stays on; an unexpected call means a missing handler or a bug.
- No `act()` warnings left in output; no arbitrary timeouts.

## Run
```bash
docker compose exec web npm test                                   # vitest run (single run)
docker compose exec web npm test -- src/pages/ProductPage.test.tsx

```
