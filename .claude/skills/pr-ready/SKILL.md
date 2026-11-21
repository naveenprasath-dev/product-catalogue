---
name: pr-ready
description: Final review before committing or opening the PR - CLAUDE.md rule audit, secrets check, docs in sync (PLAN, DECISIONS, TECH_STACK, README, .env.example), fresh-clone Docker run, submission checklist. Use when the user asks to commit, push, open/update the PR, or "wrap up".
---

# Ready-to-ship review

1. **Run `/run-checks`.** Everything must pass.
2. **Rule audit** (search the diff for these):
   - no `fakestoreapi` outside `FakeStoreProductSource` and the snapshot/curl docs;
   - no `fetch(` or API URL outside `web/src/api/client.ts`;
   - no hex colours in components; no `any` in TS; `declare(strict_types=1);` in every PHP file;
   - controllers are thin; validation is in FormRequests; no raw upstream payloads in responses;
   - no raw/engine-specific SQL outside migrations.
3. **Secrets:** `.env` is not tracked; `.env.example` has only local placeholders; `git diff` has no
   keys or tokens.
4. **Docs in sync:** every env var is in `.env.example` and TECH_STACK.md §8; new assumptions are in
   DECISIONS.md; the endpoints and tests in PLAN.md match the code; README run steps work.
5. **Fresh run:** `docker compose down -v && docker compose up --build`. Then check that the import
   succeeded, the web app loads at :5173, and an enquiry reaches Mailpit at :8025. Run it once more
   with the network off to check the snapshot fallback.
6. **Git** (follow `/git-workflow`): `main` holds only the empty initial commit, never code; all work is on `feature/catalogue-poc` in small commits; the PR description has the summary,
   architecture, run/test steps, decisions, cuts, screenshots and time spent (PLAN.md §10).
   Ask the user before pushing or opening the PR.
