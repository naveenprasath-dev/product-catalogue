---
name: dev-issues
description: "Record a development problem in ISSUES.md (symptom, cause, fix, prevention) and keep the summary table in sync. Use whenever a build, test, environment, tooling or UI problem is hit or fixed, or the user says 'log this issue'."
---

# Log a development issue in ISSUES.md

ISSUES.md is the running log of technical and environment problems. AI_LOG.md is for AI-usage
moments and decisions; DECISIONS.md is for assumptions and design choices. Don't mix them.

## When to log
- As soon as a problem costs time or surprises us (build, Docker, tests, tooling, UI, git, data).
  Log it while it is open; don't wait for the fix.
- When it is fixed or worked around, update the entry's **Fix** and the status in the summary table.
- When the user says "log this issue".

## Numbering
- Next number = highest existing `#` + 1. Never renumber or delete old entries; a mistaken entry
  gets status `won't fix` with a one-line note.

## Vocabulary
- **Area:** `env/docker`, `api`, `web`, `admin`, `tests`, `tooling`, `ui`, `git` (combine with `/`
  when two apply, e.g. `api/docker`, `admin/security`).
- **Severity:** `blocker` (work stops) · `major` (wrong behaviour, data or security risk, or a lot of
  time lost) · `minor` (annoyance, quick fix).
- **Status:** `fixed` · `workaround` · `open` · `won't fix`.

## Steps
1. Add a row to the **Summary** table: `| # | YYYY-MM-DD | Title | Area | Severity | Status |`
   (use today's real date; short title).
2. Add a section at the end of the file using the template below, same number and title.
3. Keep the table row and the section in sync (title, status) whenever either changes.

## Entry template
```markdown
## <n>. <Short title>

- **Symptom:** What we saw (error message, wrong behaviour), not the guess about why.
- **Cause:** The confirmed root cause. If not yet known, say "Unknown (investigating)".
- **Fix:** What changed (or "Workaround: …" / "None (won't fix): …").
- **Prevention / lesson:** What stops it happening again (a check, a test, a config, a habit).
- **Refs:** Files (repo-relative paths), commit hashes if known, related docs/skills.
```

## Rules
- Factual and concise, first person plural or neutral. No blame.
- Don't invent details (numbers, messages, commits). Read the referenced files to get paths right;
  leave out what you don't know.
- No secrets, tokens, keys, `.env` values or personal data (names, emails, machine paths under a
  home directory). Redact error output if it contains any.
- Link the files and commits involved.
- If the AI caused or caught the problem, also add a short item to AI_LOG.md ("Where the AI got
  something wrong") via the `ai-log` skill and cross-link: "See AI_LOG.md" in **Prevention / lesson**
  or **Refs**.
- If the fix changed a skill, a rule or a decision, update that file too (skill, CLAUDE.md,
  DECISIONS.md) and reference it.

## Done when
- The summary table has the row, the section exists with all five fields, numbers match, and no
  secrets or personal data are in the entry.
