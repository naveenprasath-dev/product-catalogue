# Development issues log

A running log of the technical and environment problems hit while building this project. Each entry
records **symptom → cause → fix → prevention**, so the same problem costs less time the next time.

- **How to add an entry:** use the `/dev-issues` skill (`.claude/skills/dev-issues/SKILL.md`). It
  gives the template, numbering and the severity/status vocabulary, and keeps the summary table in sync.
- **How this differs from the other logs:**
  - [AI_LOG.md](AI_LOG.md): how the AI was used: key prompts, my decisions, AI mistakes and how they
    were caught. When an issue here was caused or caught by the AI, it is cross-linked there.
  - [DECISIONS.md](DECISIONS.md): assumptions and design choices, and why.
  - **ISSUES.md** (this file): problems with the build, environment, tooling, tests or UI, and how
    they were fixed.

Severity: **blocker** (work stops) · **major** (wrong behaviour, data or security risk, or a lot of
time lost) · **minor** (annoyance, quick fix). Status: **fixed** · **workaround** · **open** ·
**won't fix**.

## Summary

| # | Date | Title | Area | Severity | Status |
|---|---|---|---|---|---|
| 1 | 2026-11-21 | Docker daemon not running | env/docker | minor | fixed |
| 2 | 2026-11-21 | GitHub CLI not logged in | tooling | minor | fixed |
| 3 | 2026-11-21 | `php artisan serve` dropped container env vars (API ran on sqlite defaults) | api/docker | major | fixed |
| 4 | 2026-11-21 | First API test run wiped the dev catalogue | tests | major | fixed |
| 5 | 2026-11-21 | Vite template's TypeScript 7 + oxlint incompatible with typescript-eslint | web/tooling | minor | workaround |
| 6 | 2026-11-21 | MSW 3 silently ignored `onUnhandledRequest` | tests | minor | fixed |
| 7 | 2026-11-21 | No root `.gitignore` (root `.env` would have been committed) | git | major | fixed |
| 8 | 2026-11-21 | Search heading capitalised the user's query | ui | minor | fixed |
| 9 | 2026-11-21 | api container restart-looped on missing Admin controllers | admin | minor | fixed |
| 10 | 2026-11-21 | Fortify registered default auth/passkey routes | admin/security | major | fixed |
| 11 | 2026-11-21 | Persisted `APP_KEY` not visible to `docker compose exec` | api/docker | minor | workaround |
| 12 | 2026-11-21 | Vite in Docker on macOS didn't see host file edits | web/docker | minor | fixed |
| 13 | 2026-11-21 | Admin `npm ci` wrote `node_modules` into the host's `api/` | docker | minor | fixed |
| 14 | 2026-11-21 | Mobile header overlap at 375px | ui | major | fixed |
| 15 | 2026-11-21 | Console error for guest `GET /api/v1/auth/me` 401 | web | minor | won't fix |
| 16 | 2026-11-21 | Host disk nearly full; Docker disk went read-only | env/docker | blocker | fixed |

---

## 1. Docker daemon not running

- **Symptom:** Docker commands failed at the start of the session; the daemon wasn't reachable.
- **Cause:** Docker Desktop wasn't running.
- **Fix:** Started Docker Desktop.
- **Prevention / lesson:** Check `docker info` before the first `docker compose` command.
- **Refs:** none.

## 2. GitHub CLI not logged in

- **Symptom:** `gh` couldn't create the repository.
- **Cause:** The GitHub CLI had no authenticated session.
- **Fix:** I ran `gh auth login` before the repo was created.
- **Prevention / lesson:** Check `gh auth status` before any GitHub step (the `git-workflow` skill).
- **Refs:** `.claude/skills/git-workflow/SKILL.md`.

## 3. `php artisan serve` dropped container env vars

- **Symptom:** The API ran, but silently on Laravel's sqlite defaults instead of the Postgres
  settings the container exports.
- **Cause:** `php artisan serve` starts worker processes without the container's environment
  variables, so `env()` fell back to defaults.
- **Fix:** The entrypoint now runs PHP's built-in server directly (`php -S 0.0.0.0:8000`) with
  Laravel's router script (`vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php`).
- **Prevention / lesson:** Silent fallbacks to defaults hide config problems; verify the effective
  config (DB connection) after changing how the server starts.
- **Refs:** `api/docker/entrypoint.sh`; commit `f9d4850`.

## 4. First API test run wiped the dev catalogue

- **Symptom:** After the first `php artisan test`, the dev database's catalogue was empty.
- **Cause:** PHPUnit's `<env … force="true">` doesn't override variables the container already
  exports, and Laravel reads `$_SERVER` first, so `RefreshDatabase` ran against the dev database
  instead of `catalogue_test`.
- **Fix:** Added matching `<server … force="true">` entries in `phpunit.xml` so tests use
  `catalogue_test`. The dev catalogue was re-imported; no enquiries were lost. The same mechanism was
  later needed for `APP_ENV=testing` and `ADMIN_ENABLED`.
- **Prevention / lesson:** Every env var that tests must override needs both `<env>` and `<server>`
  with `force="true"`. Check which database tests hit before the first run.
- **Refs:** `api/phpunit.xml`.

## 5. Vite template's TypeScript 7 + oxlint incompatible with typescript-eslint

- **Symptom:** The Vite React template shipped oxlint and TypeScript 7; typescript-eslint only
  supports TypeScript < 6.1. Also `tsc --noEmit` checked nothing.
- **Cause:** Template defaults differed from TECH_STACK.md (ESLint). The template's root tsconfig
  only had project references, so `tsc --noEmit` at the root type-checked no files.
- **Fix:** Removed oxlint, pinned TypeScript `~6.0`, set up ESLint as TECH_STACK.md says, and used a
  single `tsconfig.json`.
- **Prevention / lesson:** Check generated templates against the stack decisions. Revisit when
  typescript-eslint supports TypeScript 7.
- **Refs:** `web/package.json`, `web/tsconfig.json`, `TECH_STACK.md`.

## 6. MSW 3 silently ignored `onUnhandledRequest`

- **Symptom:** Requests without a handler only produced warnings instead of failing tests.
- **Cause:** MSW 3 renamed `onUnhandledRequest` to `onUnhandledFrame`; the old key is ignored
  without an error. The `react-testing` skill used the old key.
- **Fix:** `server.listen({ onUnhandledFrame: 'error' })` in the test setup; skill corrected.
- **Prevention / lesson:** After a major-version upgrade, check that "fail on unexpected" settings
  actually fail. Skills can go stale (see DECISIONS.md).
- **Refs:** `web/src/test/setup.ts`, `.claude/skills/react-testing/SKILL.md`.

## 7. No root `.gitignore`

- **Symptom:** The root `.env` (used by Docker Compose) would have been committed.
- **Cause:** Only `api/` and `web/` had their own `.gitignore`.
- **Fix:** Added a root `.gitignore` for `/.env`, `/.env.local`, `.DS_Store`, `.playwright-mcp/` and
  `.claude/settings.local.json`.
- **Prevention / lesson:** Run `git status --ignored` / a secrets check before the first commit
  (`pr-ready`, `git-workflow` skills).
- **Refs:** `.gitignore`.

## 8. Search heading capitalised the user's query

- **Symptom:** The search page showed "Results For “Jacket”" instead of the query as typed.
- **Cause:** `.page-title` had `text-transform: capitalize`, meant for category names.
- **Fix:** Capitalisation scoped to category headings only (now the `capitalize` class on the
  category page title); search headings keep the user's text.
- **Prevention / lesson:** Don't put content-specific transforms on shared component classes.
- **Refs:** `web/src/styles/global.css`, `web/src/pages/CategoryPage.tsx`, `web/src/pages/SearchPage.tsx`.

## 9. api container restart-looped on missing Admin controllers

- **Symptom:** The api container kept restarting.
- **Cause:** `routes/web.php` referenced Admin controllers that didn't exist yet, so
  `package:discover` failed in the entrypoint while the admin agent was still working.
- **Fix:** Resolved once the admin agent added the controllers. `routes/web.php` is now loaded only
  if the file exists.
- **Prevention / lesson:** With parallel agents, routes and the code they reference should land
  together.
- **Refs:** `api/bootstrap/app.php`, `api/routes/web.php`, `api/docker/entrypoint.sh`.

## 10. Fortify registered default auth/passkey routes

- **Symptom:** Default `/login`, `/register`, `/passkeys/*` and `/user/*` routes were registered.
- **Cause:** Fortify (and laravel/passkeys) register their routes by default until
  `config/fortify.php` disables them.
- **Fix:** `Fortify::ignoreRoutes()`, explicit admin routes, and a test asserting those URLs 404.
- **Prevention / lesson:** After installing an auth package, list the routes (`route:list`) and test
  that unwanted ones don't exist.
- **Refs:** `api/app/Providers/FortifyServiceProvider.php`, `api/tests/Feature/Admin/AdminAccessTest.php`.

## 11. Persisted `APP_KEY` not visible to `docker compose exec`

- **Symptom:** `docker compose exec api php artisan …` commands that encrypt/decrypt fail without a key.
- **Cause:** The entrypoint exports the persisted key (`storage/app/.app-key`) only to the server
  process; `exec` shells don't inherit it.
- **Fix (workaround):** `docker compose exec api sh -c 'APP_KEY="$(cat storage/app/.app-key)" php artisan …'`,
  or set `APP_KEY` in `.env`. Documented in README troubleshooting.
- **Prevention / lesson:** Env vars set by an entrypoint apply only to its own process tree.
- **Refs:** `api/docker/entrypoint.sh`, `README.md` (Troubleshooting).

## 12. Vite in Docker on macOS didn't see host file edits

- **Symptom:** Editing files on the host didn't trigger reloads in the web container.
- **Cause:** File-system events don't reliably cross the macOS bind mount into the container.
- **Fix:** `server.watch.usePolling` in the Vite config.
- **Prevention / lesson:** Enable polling for dev servers in Docker on macOS.
- **Refs:** `web/vite.config.ts`.

## 13. Admin `npm ci` wrote `node_modules` into the host's `api/`

- **Symptom:** An `api/node_modules` directory appeared on the host.
- **Cause:** `npm ci` in the api entrypoint wrote through the `api/` bind mount.
- **Fix:** Anonymous volume `/var/www/html/node_modules` in `docker-compose.yml`, plus
  `.dockerignore` entries.
- **Prevention / lesson:** Give every in-container `node_modules` its own volume when the source is
  bind-mounted.
- **Refs:** `docker-compose.yml`, `api/.dockerignore`.

## 14. Mobile header overlap at 375px

- **Symptom:** At 375px the wordmark overlapped a wrapped "Log in" link, and "Register" stayed visible.
- **Cause:** The rule hiding them lost the cascade to a later `.button { display: inline-flex }`.
  The AI agent had reported it fixed after only checking for horizontal overflow.
- **Fix:** Icon-only header at ≤ 480px in a block at the end of `global.css`; verified with element
  bounding boxes at 320, 375 and 414px.
- **Prevention / lesson:** Verify layout fixes by measuring the elements involved, not a proxy
  (overflow). Responsive overrides go last in the file. See AI_LOG.md ("Where the AI got something wrong").
- **Refs:** `web/src/styles/global.css` (small-phones block), [AI_LOG.md](AI_LOG.md).

## 15. Console error for guest `GET /api/v1/auth/me` 401

- **Symptom:** The browser console shows an error for `GET /api/v1/auth/me` when not signed in.
- **Cause:** Expected contract behaviour: guests get 401, and browsers log every 401 response.
- **Fix:** None (won't fix). Documented in README troubleshooting; the client treats 401 as "guest".
- **Prevention / lesson:** Document expected errors so they aren't chased as bugs.
- **Refs:** `README.md` (Troubleshooting), DECISIONS.md (session state).

## 16. Host disk nearly full; Docker disk went read-only

- **Symptom:** `docker compose up --build` failed with "read-only file system".
- **Cause:** The host disk was nearly full (727 MB free of 228 GB), so Docker's disk went read-only.
- **Fix:** Removed the leftover host `api/node_modules` (see #13), restarted Docker Desktop, ran
  `docker builder prune -af` and `docker image prune -af` (13 GB free afterwards); a clean rebuild
  then succeeded.
- **Prevention / lesson:** Keep at least 10 GB free; prune the build cache periodically.
- **Refs:** none.
