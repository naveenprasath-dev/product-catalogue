# Admin panel and login QA

- **Date:** 2026-11-21 (18:03–18:17 UTC)
- **Branch:** `feature/catalogue-poc` (clean at start, HEAD `fb227c9`). Other agents were writing currency code in parallel; the currency selector in the shop header comes from that work, and nothing failed because of it.
- **Environment:** fresh `docker compose up --build`. web http://localhost:5173, api/admin http://localhost:8000, Postgres 16.16, Mailpit v1.34.0. `APP_ENV=local`, `APP_DEBUG=false`, `ADMIN_ENABLED=true`, `SESSION_DRIVER=file`. Laravel 13.43.0.
- **Tools:** Playwright (Chromium, 1280×800 and 375×812, browser timezone IST so times show as UTC+5:30), curl, `psql` against the dev DB, the Mailpit API.
- **Scope:** `/admin` panel, the admin login, shop login/register/logout, the shared session, CSRF, throttling, error pages and the 375px layout. No code was changed.

## Summary

| | Count |
|---|---|
| Cases run | 71 |
| Pass | 65 |
| Fail | 6 (L15, L20, L21, F6, S14, X7) |
| Bugs | 7 (B1–B7; B1 was found by timing the passing cases L8/L11) |
| Not tested | 2 (see Notes) |

## Test matrix

### Admin login (`/admin/login`)

| # | Case | Steps | Expected | Actual | Result |
|---|---|---|---|---|---|
| L1 | Guest → `/admin` | curl without a cookie | 302 → `/admin/login` | 302 → `/admin/login` | Pass |
| L2 | Guest → `/admin/enquiries` | curl | 302 → login | 302 → login | Pass |
| L3 | Guest → `/admin/enquiries/1` | curl | 302 → login | 302 → login | Pass |
| L4 | Intended URL after login | Visit `/admin/enquiries/2` as a guest, then log in | Land on `/admin/enquiries/2` | Landed on `/admin/enquiries/2` | Pass |
| L5 | Valid login (mixed-case email) | `Admin@Example.com` / correct password | Dashboard | Logged in (email lower-cased) | Pass |
| L6 | Dashboard numbers match the DB | Compare cards with `psql` | Same | products 20, categories 4, sent 10, pending 7, failed 6, last import 18:01 UTC (23:31 IST). All match | Pass |
| L7 | Wrong password | Admin email, bad password | Generic error | 422 "These credentials do not match our records." | Pass |
| L8 | Unknown email | `qa-unknown-…@example.com` | Same message as L7 | Same message | Pass (but see B1, timing) |
| L9 | Empty fields | Empty email and password | Required errors | 422 "The email field is required." + password required | Pass |
| L10 | Bad email format | `not-an-email` | Generic error, no leak | Generic "credentials do not match" | Pass |
| L11 | Customer at admin login | `qa-customer@example.com` + correct password | Generic error | Same generic 422 | Pass (but see B1, timing) |
| L12 | Customer session → `/admin`, `/admin/enquiries`, `/admin/enquiries/1` | Log in to the shop as a customer, open admin URLs | 403, no crash | 403 "This action is unauthorized." on all three | Pass |
| L13 | Customer session → POST re-send | fetch with XSRF header | 403 | 403 | Pass |
| L14 | Throttle (curl) | 7 failed POSTs, same email+IP | 6th → 429 | 1–5 → 422, 6 and 7 → 429 "Too Many Attempts." Another email from the same IP still gets 422 (limit is per email+IP, as documented) | Pass |
| L15 | Throttle message in the UI | 6 failed logins in the browser | Inline throttled message | Inertia's error modal with a bare "429 Too Many Requests" page | **Fail (B2)** |
| L16 | Remember me | Log in with `remember=true`, drop the session cookie, GET `/admin` | Still logged in | 200; `remember_web_*` cookie, 400-day expiry | Pass |
| L17 | Logout clears the remember token | Log out, replay the old remember cookie | Back to login | 302 → `/admin/login` | Pass |
| L18 | Session survives a reload | Reload `/admin` | Still logged in | Still on the dashboard | Pass |
| L19 | Logout | User menu → Log out | `/admin/login` | `/admin/login`; direct visit to `/admin/enquiries/1` → login | Pass |
| L20 | Back button after logout | Log out from `/admin/enquiries/1`, press Back | No data shown | Full enquiry with the customer's name, email, phone and message, loaded from `history.state` without a request | **Fail (B3)** |
| L21 | Logged-in user opens `/admin/login` | Admin (or customer) session, GET `/admin/login` | Admin → `/admin`; customer → somewhere useful | 302 → `/`, which is a 404 for both | **Fail (B4)** |
| L22 | Removed Fortify routes | GET and POST on `/register`, `/admin/register`, `/forgot-password`, `/admin/forgot-password`, `/two-factor-challenge`, `/admin/two-factor-challenge`, `/passkeys/login`, `/passkeys/options`, `/user/confirm-password`, `/admin/user/confirm-password`, `/email/verify`, `/admin/email/verify`, `/reset-password/x`, `/user/two-factor-authentication`, `/settings/profile`, `/dashboard`, `/login` | 404 | All 404 (GET and POST) | Pass |
| L23 | CSRF: POST `/admin/login` with no token | curl, with and without a session cookie; JSON variant | 419 | 419 "Page Expired" / JSON `CSRF token mismatch.` | Pass |
| L24 | CSRF on admin POSTs | curl POST re-send and logout with the session cookie but no token | 419 | 419 for both, no side effects | Pass |
| L25 | Session cookie flags | Check `Set-Cookie` | HttpOnly, SameSite=lax, path / | session cookie `httponly; samesite=lax; path=/`; XSRF cookie readable by JS (by design) | Pass |

### Admin features

| # | Case | Steps | Expected | Actual | Result |
|---|---|---|---|---|---|
| F1 | Dashboard cards | Open `/admin` | 5 cards; the status cards link to filtered lists | Products, Categories, Sent, Pending, Failed. The status cards link to `?status=…` | Pass |
| F2 | Enquiry list | 23 enquiries in the DB | Newest first, 20 per page | Newest first, "1–20 of 23", Previous disabled | Pass |
| F3 | Paging | Click Next | Page 2 shows 3 rows | "21–23 of 23", 3 rows, Previous is a link, Next disabled | Pass |
| F4 | Filter all/pending/sent/failed | Click each tab | Only that status; active tab marked | 20 / 7 / 10 / 6 rows, a single status each, `aria-current="page"` on the active tab | Pass |
| F5 | `page=999`, `status=failed&page=2` | Direct URL | Empty page, no error | 200 | Pass |
| F6 | Invalid query (`?status=bogus`, `?page=abc`, `?page=-1`, `?status[]=x`) | Direct URL after viewing `/admin/enquiries/3` | 422-style message or the unfiltered list | Silent 302 to the previously visited page (`/admin/enquiries/3`, or even `/admin/enquiries/0` → 404), no message | **Fail (B5)** |
| F7 | Detail page | Open `/admin/enquiries/2` | All fields; user input escaped | All fields; `<script>`, `<b>` and `<img onerror>` payloads show as text | Pass |
| F8 | "Customer account" label | Enquiry 25 made while signed in | Marked as a customer account | List shows "· customer account"; DB `user_id=2` | Pass |
| F9 | 404 for unknown/odd ids | `/99999`, `/0`, `/-1`, `/abc`, `/1.5`, a 19-digit id, `/admin/nonexistent` | 404 HTML, no trace | All 404 "Not Found", no trace | Pass |
| F10 | Re-send a failed enquiry | `UPDATE … status='failed'` on #1, click Re-send on the detail page | Success toast, status sent, email in Mailpit | Toast "Enquiry re-sent to the sales inbox.", status Sent, `mailed_at` set, button gone, 1 message to `sales@example.com` | Pass |
| F11 | Re-send from the list (failed filter) | Click Re-send on a row | Toast; row leaves the failed list | As expected (screenshot 05) | Pass |
| F12 | Re-send button on sent enquiries | Look at sent rows and detail | No button | No button | Pass |
| F13 | Forced re-send of a sent enquiry | POST `/admin/enquiries/2/resend` | Warning toast, no mail | Toast "Only failed enquiries can be re-sent.", no mail | Pass (copy nit, see Notes) |
| F14 | Re-send when mail is down | `docker compose stop mailpit`, re-send #12 | Error toast, still failed, no JSON 502 page | Toast "Still failing, try again later.", status Failed, normal page; log shows only `enquiry_id` | Pass |
| F15 | Re-send unknown id / GET on re-send | POST `/99999/resend`, `/abc/resend`; GET `/2/resend` | 404 / 405, no trace | 404 / 404 / 405 (Symfony's plain "Method Not Allowed" page), no trace | Pass |
| F16 | Re-send throttle | 7 POSTs in a minute | 7th → 429 | 1–6 → 302, 7 → 429 | Pass |
| F17 | Email escaping | Mailpit HTML for the re-sent #1 | Escaped | `&lt;script&gt;…`, no raw `<script>` | Pass |
| F18 | Personal data only behind auth | Guest GETs on admin URLs | Redirect, no data | Redirect to login, no props in the body | Pass |
| F19 | No traces in error pages | 403/404/405/419/429/500 bodies | No stack traces | None found (`APP_DEBUG=false`) | Pass |
| F20 | Logs carry no personal data | `docker compose logs api` during re-sends | ids only | `{"enquiry_id":…,"admin_id":1}` only | Pass |

### Shop logins (:5173)

| # | Case | Steps | Expected | Actual | Result |
|---|---|---|---|---|---|
| S1 | Register: empty submit | Click Create account | Client errors | "Please enter your name/email", "Password must be at least 8 characters", no request | Pass |
| S2 | Register: password mismatch / short | `short` / `different` | Client errors | "Password must be at least 8 characters." + "Passwords do not match." | Pass |
| S3 | Register: bad email (server) | `not-an-email`, valid password | 422 shown under the field | "The email field must be a valid email address." | Pass |
| S4 | Register: duplicate email | `admin@example.com` | 422 | "The email has already been taken." (see Notes: account enumeration) | Pass |
| S5 | Register: valid | `qa-customer@example.com` | 201, signed in | 201, "Hi, QA Customer", DB `is_admin=false` | Pass |
| S6 | Session survives a reload | Reload | Still signed in | `/auth/me` 200, header unchanged | Pass |
| S7 | Logout | Log out | Guest header | 204, Log in / Register shown; `/admin` → 302 | Pass |
| S8 | Login: wrong password | Bad password | 422 generic | 422, "These credentials do not match our records." | Pass |
| S9 | Login throttle | 6 bad logins | 429 + message | 6th → 429, "Too many attempts. Please wait a minute and try again." (successful logins also count, see Notes) | Pass |
| S10 | Login CSRF | Shop Origin, no `X-XSRF-TOKEN` | 419 | 419 "CSRF token mismatch." | Pass |
| S11 | `?next=/products/3` | Log in | Land on `/products/3` | `/products/3` | Pass |
| S12 | Open redirect: `//evil.com`, `https://evil.com`, `/\evil.com`, `javascript:alert(1)`, `%2F%2Fevil.com` | Log in with each | Stay on the shop | `/` for the first four; `/%2Fevil.com` (in-app path) for the last | Pass |
| S13 | Enquiry pre-fill when signed in | Open a product | Name/email filled | Filled; enquiry stored with `user_id=2` | Pass |
| S14 | Pre-fill cleared after logout | Log out on a product page | Form empties | Previous user's name/email stay in the form | **Fail (B6)** |
| S15 | Guest enquiry (UI and API) | Submit as a guest | 201, `user_id` null | "Thank you, QA Guest UI!", DB `user_id` null; API without Origin 201 | Pass |
| S16 | Shared session: admin signs in to the shop | Shop login as admin, then GET `/admin` | Admin panel opens (documented) | 200 dashboard | Pass |
| S17 | Shared session: customer → `/admin` | See L12 | 403 | 403 | Pass |
| S18 | Enquiry while mail is down (API) | POST with Mailpit stopped | 502 JSON, stored as failed | 502 `{"message":"Your enquiry could not be sent right now…"}`, row #24 failed | Pass |

### Cross-cutting

| # | Case | Steps | Expected | Actual | Result |
|---|---|---|---|---|---|
| X1 | Stateless API without Origin | `curl -i /api/v1/products/featured` | No `Set-Cookie` | No `Set-Cookie` | Pass |
| X2 | With the shop Origin | Same request with `Origin: http://localhost:5173` | Session + XSRF cookies | Both set, ACAO = shop origin, credentials true | Pass |
| X3 | Foreign Origin | `Origin: http://evil.example` | No cookies, no ACAO for evil | No cookies; ACAO is the fixed shop origin (the browser blocks it) | Pass |
| X4 | 375px: no page-level horizontal scroll | `/admin`, `/admin/enquiries`, `/admin/enquiries/12` | `scrollWidth == 375` | 375 on all three | Pass |
| X5 | 375px: sidebar | Toggle button `[20,18,28×28]`; open the sheet | Sheet usable | Sheet `[0,0,288×812]`, links `[8,96,271×32]` and `[8,132,271×32]` | Pass |
| X6 | 375px: no overlap | Pairwise bounding-box check of headings, dt/dd and buttons on the detail page; dashboard cards | No overlaps | None; cards stacked at x 16–359 | Pass |
| X7 | 375px: sheet closes after navigating | Open the sheet, tap Dashboard | Sheet closes | Sheet stays open over the new page | **Fail (B7)** |
| X8 | 375px: enquiry table | `/admin/enquiries` | Readable | Table 739px wide inside an `overflow-x:auto` wrapper (341px). Status and Re-send are off-screen until you scroll it sideways; acceptable, see Notes | Pass |

**Totals: 71 cases, 65 pass, 6 fail; 7 bugs.**

## Bugs

| ID | Severity | Title |
|---|---|---|
| B1 | Medium | Admin login timing reveals which emails are admin accounts |
| B2 | Low | Throttled admin login shows Inertia's raw "429 Too Many Requests" modal |
| B3 | Medium | After admin logout, Back shows customer personal data from browser history |
| B4 | Low | Logged-in user opening `/admin/login` is redirected to `/`, a 404 |
| B5 | Low | Invalid enquiry filters silently redirect to an unrelated previous page |
| B6 | Low | Shop enquiry form keeps the previous user's name/email after logout |
| B7 | Low | Mobile admin sidebar sheet doesn't close after navigating |

### B1 — Admin login timing reveals which emails are admin accounts (Medium)

- **Repro:** POST `/admin/login` (with CSRF) three times for each case and time it:
  - unknown email: 0.043 / 0.031 / 0.028 s
  - customer email + **correct** password: 0.028 / 0.025 / 0.024 s
  - `admin@example.com` + wrong password: 0.250 / 0.255 / 0.252 s
- **Impact:** the error text is the same in every case, but the response time differs. Only real admin emails take about 250 ms, because only they reach `Hash::check`. An attacker can confirm which emails are admins (about 10× slower) and then aim password guessing at them. The per-email throttle doesn't stop this, because each email gets its own 5/min bucket.
- **Suspected file:** `api/app/Providers/FortifyServiceProvider.php`, `authenticateUsing`. It short-circuits on `$user === null || ! $user->is_admin` before hashing. Possible fix: always run one hash check (against a dummy hash when there is no user or the user isn't an admin), or check the password first and `is_admin` second.

### B2 — Throttled admin login shows the raw 429 modal (Low)

- **Repro:** at `/admin/login`, submit a wrong password 6 times within a minute.
- **Expected:** an inline message under the field, like the shop's "Too many attempts. Please wait a minute and try again."
- **Actual:** Inertia opens its full-screen error modal, an iframe with Laravel's bare "429 | Too Many Requests" page. Console: `Failed to load resource: 429`. No trace, but it looks like a crash and doesn't say how long to wait.
- **Suspected cause:** `routes/web.php` applies the `throttle:login` middleware, which throws `ThrottleRequestsException`. Inertia shows any non-Inertia response in its modal. Fortify's own `EnsureLoginIsNotThrottled` pipeline step, which returns a validation error with seconds, is skipped because a limiter is configured. Possible fixes: render `ThrottleRequestsException` for `admin/login` as `back()->withErrors(['email' => trans('auth.throttle', …)])`, or let Fortify's pipeline handle the throttling.

### B3 — Back after admin logout shows customer personal data (Medium)

- **Repro:** log in as admin, open `/admin/enquiries/1`, log out from the user menu (you land on `/admin/login`), press the browser's Back button.
- **Actual:** the full enquiry page, with the customer's name, email, phone and message, is back on screen with **no network request**. `history.state.page.props.enquiry` holds the data in plain text. The same happens for the dashboard and the list.
- **Impact:** on a shared or kiosk machine, the next person can read customer personal data after the admin has logged out. Any further click goes to the login page, but the data is already visible.
- **Suspected cause:** `api/config/inertia.php` has `'history' => ['encrypt' => env('INERTIA_ENCRYPT_HISTORY', false)]`, and logout doesn't clear history. Fix: set `INERTIA_ENCRYPT_HISTORY=true` (or add the `inertia.encrypt` middleware to the admin group) and call `Inertia::clearHistory()` on logout (for example in a Fortify `LogoutResponse`). Adding `.env.example` / `docker-compose.yml` entries is a config change.

### B4 — Logged-in user opening `/admin/login` lands on a 404 (Low)

- **Repro:** (a) sign in to the shop as a customer, open http://localhost:8000/admin/login. (b) Same as an admin (from the shop or the panel).
- **Actual:** the `guest:web` middleware redirects to `/`, and `/` is a 404 "Not Found" page in both cases. Expected: admin → `/admin`; customer → the 403 page or the login form with a note, at least not a 404. A customer who wants to switch to an admin account gets no way to do it from the panel. They have to log out in the shop first.
- **Suspected file:** `api/bootstrap/app.php`. There is a `redirectGuestsTo('/admin/login')` but no `redirectUsersTo(...)`, so `RedirectIfAuthenticated` falls back to `/`.

### B5 — Invalid list filters silently redirect to the previous page (Low)

- **Repro:** logged in, open `/admin/enquiries/3`, then go to `/admin/enquiries?status=bogus` (or `?page=abc`, `?page=-1`, `?status[]=x`).
- **Actual:** 302 back to `/admin/enquiries/3` with no message. When the previous URL was itself a bad one, the request chains: `?status=bogus` once redirected to `/admin/enquiries/0`, a 404. For a stale bookmark or a hand-edited URL, the admin just bounces somewhere unrelated.
- **Expected:** ignore the invalid value and show the unfiltered list, or show an error on the list page.
- **Suspected file:** `api/app/Http/Requests/Admin/ListEnquiriesRequest.php`. A failed GET FormRequest redirects back by default. Override `failedValidation`/`getRedirectUrl()` to point at `admin.enquiries.index`, or sanitise instead of validating.

### B6 — Shop enquiry form keeps the previous user's details after logout (Low)

- **Repro:** sign in to the shop, open a product page (name and email are pre-filled), click Log out in the header while staying on the page.
- **Actual:** the form still holds the previous user's name and email (`Admin` / `admin@example.com` in the test). If the next person on the same browser sends the form, the enquiry goes out under the previous user's identity, stored as a guest (`user_id` null).
- **Suspected file:** `web/src/components/EnquiryForm.tsx`, lines 64–72. The "pre-fill once per user" logic only runs when `user !== null`. When `user` becomes `null`, nothing clears the pre-filled fields or resets `prefilledFor`. (Shop-side, not admin.)

### B7 — Mobile admin sidebar stays open after navigating (Low)

- **Repro:** at 375px, open `/admin/enquiries`, tap the sidebar toggle, tap "Dashboard".
- **Actual:** the URL changes to `/admin`, but the sheet (`role=dialog`) stays open over the new page and must be closed by hand.
- **Suspected file:** the kit's sidebar nav (`api/resources/js/components/nav-main.tsx` / `app-sidebar.tsx`). On mobile it should call `setOpenMobile(false)` on navigation.

## Notes and observations (not counted as failures)

- **Laravel Boost browser logger is injected into admin pages** (`APP_ENV=local`). Each page posts console output to the unauthenticated `POST /_boost/browser-logs`. A malformed payload there gives a 500 (the generic page, no trace; the full trace goes to the api log). It's a dev-only tool, but it means `APP_ENV=local` should never ship. Worth a line in DECISIONS/README.
- **Laravel 13 `PreventRequestForgery`** accepts same-origin browser requests (`Sec-Fetch-Site: same-origin`) without an XSRF header. A browser `fetch('/admin/enquiries/2/resend', {method:'POST'})` from an admin page went through, while curl got 419. Same-site requests (`:5173` → `:8000`) are not allowed (`allowSameSite=false`), so the shop origin can't forge admin POSTs. This is expected framework behaviour; noted so nobody mistakes it for a CSRF hole.
- **Shared site:** the shop (`:5173`) and the admin (`:8000`) are the same site, and the XSRF cookie is JS-readable on both. Any XSS in the shop could act as a signed-in admin. This is the documented trade-off of the shared session (ADMIN_PLAN §19c); worth keeping in mind for production (separate hostnames).
- **No security headers** on admin responses (`X-Frame-Options`, `Content-Security-Policy`, `X-Content-Type-Options`, `Referrer-Policy`). The admin pages can be framed, so the one-click Re-send could be clickjacked. Low risk for a local PoC; suggested hardening.
- **Registration reveals account existence** ("The email has already been taken.", including for `admin@example.com`). This is standard and accepted for the PoC, but combined with B1 it makes finding admin emails easy.
- **Shop login throttle counts successful logins** too (5 successful logins in a minute → 429). Middleware throttle behaviour; harmless.
- **Admin login keeps the wrong password** in the field after a failed attempt (the kit usually resets it). The email input doesn't get `aria-invalid` when it has an error. Minor.
- **Copy:** the warning toast says "Only failed enquiries can be re-sent." but pending enquiries older than 5 minutes can be re-sent too, and show the button.
- **Detail page breadcrumb** shows only "Enquiries" as a disabled item. There is no link back to the list or "Enquiry #N" crumb; the sidebar is the only way back.
- **405 page** (GET on a POST-only route) is Symfony's plain "Something is broken…" page instead of Laravel's styled error page. No trace.
- **375px enquiry table** scrolls sideways inside its card. The Status and Re-send columns are off-screen until you scroll the table; consider stacking rows on small screens.
- The `401` on `GET /api/v1/auth/me` for guests shows as a console error in the shop. It's expected (guest = 401), just noisy.

### Not tested

- **Session expiry** (`SESSION_LIFETIME=120` min): too long to wait for. Mid-session CSRF expiry (419 → Inertia reload) was not simulated.
- **Admin login in a second browser profile** while the first is still logged in (concurrent sessions). Not covered.

## Test data created (dev DB / Mailpit)

Not removed, so the panel has realistic data. To reset, run `docker compose exec -T db psql -U catalogue -d catalogue -c "DELETE FROM enquiries; DELETE FROM users WHERE email='qa-customer@example.com';"` (or reset the DB with the catalogue-data skill).

- **User:** `qa-customer@example.com` / `QaCustomer-Pass1` (id 2, `is_admin=false`). Registration attempts for `qa-throttle-*` emails failed, so those users don't exist.
- **Enquiries 1–3:** guest enquiries via the API with XSS payloads in name/message. #1 was set to `failed` and re-sent.
- **Enquiries 4–23:** "QA Paging N" rows inserted with SQL (mixed sent/pending/failed, no email sent). #6 and #9 were re-sent (now sent). #12 is still failed (re-sent while Mailpit was down).
- **Enquiry 24:** "QA MailDown", failed (posted while Mailpit was stopped).
- **Enquiry 25:** "QA Customer", signed-in enquiry (`user_id=2`). **Enquiry 26:** "QA Guest UI", guest via the shop UI.
- **Mailpit:** the inbox was cleared twice during testing (it was empty before the first clear). It now holds the re-send and new-enquiry emails.
- Mailpit was stopped and started again (`docker compose stop/start mailpit`) for F14/S18. It is running. No other containers were touched.

## Screenshots

`docs/screenshots/admin-01-login.png`, `admin-02-dashboard.png`, `admin-03-enquiries.png`, `admin-04-enquiry-detail.png`, `admin-05-resend-toast.png`, `admin-06-login-error.png`, `admin-07-mobile.png` (375px).

## Fixes (2026-11-21, after QA)

All in `api/`. Every fix has a test, and the whole set was also re-checked live (curl timing, Playwright at 1280px and 375px). B6 is in `web/` and was fixed separately: `web/src/components/EnquiryForm.tsx` clears only the values the pre-fill inserted when the user changes (tests in `web/src/pages/ProductPage.test.tsx`).

| Bug | Fix | Test |
|---|---|---|
| B1 timing leak | `FortifyServiceProvider::authenticateUsing` now always runs exactly one `Hash::check`: against the admin's hash, or against a dummy bcrypt hash (current cost, made once and cached) for unknown emails and customers. Live: unknown, customer and wrong-password logins all take about 0.25s (before: 0.03s vs 0.25s) | `LoginTest::test_every_failed_login_runs_one_hash_check` (unknown / customer / admin-wrong-password, counting hasher) |
| B2 raw 429 modal | `bootstrap/app.php` renders a `ThrottleRequestsException` on `login.store` as a redirect to the login page with an `email` error, "Too many attempts. Try again in N seconds." (N from `Retry-After`), keeping the typed email. `throttle:login` still applies | `LoginTest::test_the_sixth_attempt_in_a_minute_is_throttled` (now expects the form error), `test_the_throttle_message_counts_down` |
| B3 data after logout | All admin routes use Inertia's `EncryptHistory` middleware (history state is encrypted with a per-session key). Fortify's `LogoutResponse` is bound to `AdminLogoutResponse`, which calls `Inertia::clearHistory()` and redirects to `/admin/login`. Every admin response gets `Cache-Control: no-store, private`. Live: `history.state.page` is encrypted; Back after logout shows the login page | `LoginTest::test_logout_clears_the_inertia_history`, `SecurityHeadersTest` (headers on pages, guest redirect, 403, logout; `encryptHistory` true; every admin route has both middlewares) |
| B4 `/admin/login` → `/` 404 | `guest:web` on the login routes replaced by `RedirectAdminsFromLogin`: an admin goes to `/admin`; a signed-in customer gets the login form (and can switch to an admin account). `redirectUsersTo('/admin')` as the global fallback | `LoginTest::test_a_signed_in_admin_opening_the_login_page_goes_to_the_dashboard`, `test_a_signed_in_customer_gets_the_login_form`, `test_a_signed_in_customer_can_switch_to_an_admin_account` |
| B5 invalid filters redirect back | `ListEnquiriesRequest` no longer validates; it reads leniently: an unknown or array `status` means all, and a non-integer, < 1 or > 100000 `page` means page 1. Always 200 | `EnquiriesTest::test_invalid_filters_are_ignored` (8 bad queries) |
| B7 mobile sheet stays open | `NavMain` links call `setOpenMobile(false)` on mobile. Live at 375px: the sheet closes after tapping Dashboard | Checked live (no JS test runner in `api/`) |
| Clickjacking | `AdminSecurityHeaders` on every admin route (prioritised before `auth`, so redirects and 403s get it too): `X-Frame-Options: DENY`, `Content-Security-Policy: frame-ancestors 'none'`, plus `X-Content-Type-Options: nosniff`, `Referrer-Policy: same-origin`. The API is unaffected | `SecurityHeadersTest` |
| Re-send copy | Warning toast: "Only failed enquiries, or pending ones older than 5 minutes, can be re-sent." (from `StoredEnquiry::STUCK_PENDING_MINUTES`) | `ResendEnquiryTest::test_a_sent_enquiry_is_not_re_sent` |
| Detail page way back | "Back to enquiries" link at the top of `/admin/enquiries/{id}` | Checked live |
| 375px enquiry table | Under `sm`, the list renders as stacked cards (date link + status badge, customer, product, Re-send). The table shows from `sm` up. Live: no horizontal scroll, Status and Re-send inside the viewport | Checked live |

Also from the coordinator's browser pass:
- **Sync toast:** the flash was firing (it appeared 0.27s after the click), but Sonner's default 4s duration meant it was gone by the time anyone looked. Toasts now last 6s (hover pauses them); live, the sync toast is still visible at 4.8s. Covered by `Admin\CurrenciesTest::test_sync_live_rates_updates_rates_and_shows_the_count`.
- **Logo:** the starter kit's Laravel mark is replaced by the shop's shopping-bag mark (inline SVG, `currentColor` + token classes), and the app name is "Product Catalogue Admin".

Checks: `php artisan test` 229 passed; Pint, `npm run types:check`, `npm run check` and `npm run build` all pass.
