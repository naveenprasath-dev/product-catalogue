---
name: git-workflow
description: Git and GitHub workflow for this repo - branch rules (main holds no code), staging safely, small commits grouped by area, commit message format, pushing, and opening/updating the PR with gh. Use whenever the user asks to commit, branch, push, open or update a PR, or check git state. Run pr-ready first before any push or PR.
---

# Git workflow

Remote: `origin` → GitHub `product-catalogue` (**public** — anything pushed is visible and cached
immediately, even if deleted later).

## Branch rules
- `main` holds **only the empty initial commit**. Never commit code, docs or config to `main`, never
  merge into it locally, never push to it beyond that first commit.
- All work happens on `feature/catalogue-poc` and reaches `main` only through the PR, which stays
  **open** for review (PLAN.md §10). Check with `git branch --show-current` before every commit.
- Never `push --force`, `reset --hard`, `rebase` published commits, or delete branches without the
  user asking. No interactive flags (`-i`).

## Before committing
1. `git status --short` and `git diff --stat`. Read what you are about to commit.
2. **Never stage blindly** (`git add -A` / `git add .`). Stage by path, per logical group.
3. Must never be committed: `.env` (root, `api/.env`, `web/.env`), `api/vendor/`, `node_modules/`,
   `api/storage/**` runtime files, `.playwright-mcp/`, `.DS_Store`, persisted APP_KEY files, logs.
   Verify with `git check-ignore -v <path>`; fix `.gitignore` rather than unstaging by hand each time.
4. Secrets scan of the staged diff:
   `git diff --cached | grep -nEi '(api[_-]?key|secret|token|password|BEGIN .*PRIVATE)'` — every hit must
   be a local-only placeholder from `.env.example` (e.g. `DB_PASSWORD=catalogue`, default admin creds)
   or test data. If unsure, stop and ask the user.
5. Checks pass (`/run-checks`). Don't commit a red state unless the user asks for a WIP commit.

## Commit grouping (small, reviewable, each one coherent)
Suggested order for the first push of the branch:
1. Planning + AI workflow: `CLAUDE.md`, `PLAN.md`, `DECISIONS.md`, `TECH_STACK.md`, `AI_LOG.md`,
   `ADMIN_PLAN.md`, `.claude/`, `.gitignore`
2. Docker + config: `docker-compose.yml`, `docker/`, `.env.example`
3. API scaffold (framework skeleton, Dockerfile, entrypoint)
4. API catalogue: sources, importer, command, snapshot JSON + their tests
5. API endpoints, service, resources, mail + tests
6. Web app: client, pages, components, styles + tests
7. Auth (api + web) · 8. Admin panel · 9. README + screenshots
Use `git add -p` only when one file genuinely mixes two groups.

## Commit message format
```
<area>: <imperative summary, ≤ 72 chars>

Why this change, and anything non-obvious (wrap at 72).

Co-Authored-By: <attribution line from the current system reminder, if any>
```
Areas: `api`, `web`, `admin`, `auth`, `docker`, `docs`, `skills`, `chore`. Example:
`api: import catalogue from Fake Store with JSON snapshot fallback`.
Pass multi-line messages with a heredoc (`git commit -F - <<'EOF' … EOF`).

## Pushing and the PR (ask the user first — it is public)
```bash
git push -u origin main                     # only once: the empty initial commit
git push -u origin feature/catalogue-poc
gh pr create --base main --head feature/catalogue-poc --title "Product catalogue PoC" --body-file <file>
gh pr view --web                            # only if the user wants it opened
```
PR body (PLAN.md §10): summary · architecture + swap points · run/test steps · key decisions and
assumptions · cuts and next steps · screenshots (`docs/screenshots/`) · time spent split by phase ·
end with the PR attribution line from the current system reminder, if any.
Later updates: commit + `git push`; edit the description with `gh pr edit --body-file <file>`.
Never merge the PR.

## Done when
`git status` is clean (or only intentionally untracked files), `git log --oneline main..HEAD` reads as
a sensible story, and — if pushed — `gh pr view` shows the PR open against `main`.
