# AI working log (raw notes → becomes AI_USAGE.md)

Tool: Claude Code · Model: Claude Opus 5.5

## Key prompts / moments
1. Gave Claude the challenge PDF and asked for a plan. It proposed ports/adapters boundaries
   (ProductRepository, Mail drivers, theme tokens), driven by the brief's "data source, look and
   feel, email provider will change".
2. Changed the backend from Node to **Laravel** (my decision). The plan was re-mapped onto Laravel
   mechanisms (service container binding, Mail drivers, FormRequest, API Resources).
3. Asked for **two parallel agents**, one for the backend and one for the frontend (my decision, to fit
   the time-box). Before starting them, Claude wrote the API contract into PLAN.md so both sides built
   against the same JSON shape. Each agent owned its own directories and neither touched git.
4. Asked for Laravel and React **convention skills** on top of the task skills, so both agents wrote
   code in the same style. Recorded why in DECISIONS.md.
5. Asked for a third parallel agent to write the README while the backend was still being built.
6. Asked for an **admin panel built with Laravel's React starter kit**, planned by a parallel agent
   (ADMIN_PLAN.md) before any code is written (my decision; the plan had listed admin as a cut).
7. Reviewed ADMIN_PLAN.md's nine open questions and accepted Claude's recommendations (my decision):
   re-send failed enquiries in the MVP; no import trigger; local-only default admin credentials;
   keep Wayfinder pinned; featured products stay in config; product editing later; password reset
   CLI only; customer self-registration on; one shared session for shop and admin.
   Then started three parallel build agents: backend (shared foundation first, then admin service
   methods and shop auth endpoints), admin UI (starter-kit port into `api/`) and frontend (shop login).
   Each agent owns its own files inside `api/`, and the backend tells the coordinator when the
   foundation is ready so the admin agent can start changing `api/`.
8. Created the public GitHub repo `product-catalogue`. Changed the branch strategy: **`main` holds no
   code**, only an empty initial commit; everything (code, docs, config) lives on
   `feature/catalogue-poc` and goes in through the PR (my decision; PLAN.md had "main = scaffold").
9. Asked for a `git-workflow` skill (branch rules, safe staging, commit grouping, push/PR steps).
10. Reviewed the working shop and asked for the UI to be **refined to look like a modern e-commerce
    site** (my decision): sticky header with central search, hero + shop-by-category on home,
    richer product cards, breadcrumbs, skeleton loaders. Constraint kept: no invented store claims
    (shipping, returns, reviews) that the data doesn't support.
11. Decided to **keep Laravel Boost** (dev-only MCP + Laravel guidelines for the AI agents) and
    document it, and approved four new CLAUDE.md rules: Sanctum cookie auth with no tokens in
    localStorage; user id only from the session; JSON errors only for `api/*`; admin pages catch
    domain errors and show a toast (my decisions).
12. Asked for **configurable currencies**: a currency dropdown in the shop, prices converted on the
    backend, rates managed in the admin, and **live rates from an open-source API** (my decisions).
    Claude proposed Frankfurter (MIT, no key, ECB data); the agent found v1 deprecated and used v2.
    Same pattern as products: one `CurrencyRateSource`, never called during a shop request.
13. Asked for a **QA agent to test the admin panel and every login path**: 71 cases, 7 bugs found
    (including a login timing leak and personal data visible via Back after logout), all fixed with
    tests by the build agents. Claude's own browser check also caught the sync toast vanishing and
    the starter kit's Laravel logo still in the admin sidebar.
14. Asked for a separate **issues log** (ISSUES.md + `/dev-issues` skill) for development problems.
15. Disk full (727 MB free) made Docker read-only; chose to free space with build-cache and unused
    image prunes (my decision).

## Where the AI got something wrong / I corrected it
- **No login/logout for users.** The plan quietly listed "auth" as a cut and Claude never raised it as
  a gap worth discussing. I caught it while reviewing the running app and asked for login/logout.
  Change: user authentication added to the scope, planned together with the admin panel because
  both need the same `users` table, sessions and a stable `APP_KEY`. (caught by me)
- **Skill was out of date.** The `react-testing` skill used MSW v2's `onUnhandledRequest`, but the
  project installed MSW 3, which silently ignores it (renamed `onUnhandledFrame`), so unexpected
  requests would only warn. The frontend agent caught it; the skill was fixed. (caught by the AI agent)
- **Tests wiped the dev database.** PHPUnit's `<env force>` didn't override the container's env vars,
  so the first test run used the dev DB. The backend agent noticed, switched to `<server force>`,
  confirmed tests use `catalogue_test`, and re-imported (no enquiries existed yet, nothing lost).
  (caught by the AI agent)
- **`php artisan serve` dropped the container's env vars**, so the API silently ran on sqlite defaults.
  Switched to `php -S` with Laravel's router script; docs updated. (caught by the AI agent)
- **No root `.gitignore`**, so the root `.env` would have been committed. Found by the README agent;
  added. (caught by the AI agent)
- **Search heading capitalised the user's query** ("Results For “Jacket”") because `.page-title`
  had `text-transform: capitalize` for category names. Found in the Playwright screenshot review;
  scoped to `.page-title--category`. (caught by Claude during visual review)
- **The AI claimed a fix it hadn't verified.** After the redesign, the frontend agent reported the
  Register button was hidden below 480px, but the 375px screenshot showed the wordmark overlapping a
  wrapped "Log in" and Register still visible (`.button { display: inline-flex }` later in the CSS won
  the cascade). It had only checked horizontal overflow. Claude caught it reviewing the screenshot; the
  fix uses an icon-only header on small screens, verified with a bounding-box overlap check at 320/375/414px.
  Lesson: "done" claims about UI need a screenshot or a measurement of the exact thing. (caught by Claude)

## Time tracking
| Phase | Start | End | Minutes |
|---|---|---|---|
| Planning | | | |
| Building | | | |
| Testing | | | |
| Reviewing | | | |
