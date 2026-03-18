---
name: drupalorg-work-on-issue
description: >
  Agentic workflow for contributing to a Drupal.org issue via GitLab MR. Orchestrates
  fork verification, directory alignment, remote setup, branch checkout, and the
  fix/push/pipeline loop.
---

# /drupalorg-work-on-issue

**Purpose:** Agentic workflow for contributing to a Drupal.org issue via GitLab MR.

**Usage:** `/drupalorg-work-on-issue <nid>`

---

## Instructions

When the user invokes `/drupalorg-work-on-issue <nid>`, execute the following workflow. Pause at
each checkpoint marked **[PAUSE]** — present findings and wait for the user to confirm
before proceeding.

---

### Step 1: Fetch issue and fork details

Run both commands to gather context:

```bash
drupalorg issue:show <nid> --format=llm
drupalorg issue:get-fork <nid> --format=llm
```

Report to the user:
- Issue title, status, project machine name
- Whether a fork exists and which branches are available

**Directory detection:** Before prompting the user, read `CLAUDE.md` in the current directory.
If it documents the path to the `<project>` module or repository, `cd` there automatically
and skip the directory prompt. Only fall back to running `git remote get-url origin` and
asking the user if `CLAUDE.md` provides no guidance.

**Branch selection:** Count branches from the `issue:get-fork` output that match `<nid>-*`:
- **Exactly one match** → select it automatically; no prompt needed.
- **Multiple matches** → list them and ask the user which to check out.
- **No matches** → note that no branches exist yet and ask the user how to proceed
  (e.g. create a new branch from the upstream project default branch).

**[PAUSE]** Only pause here if the working directory could not be determined automatically
OR if multiple branches exist. Present your findings and wait for confirmation before
proceeding.

---

### Step 2: Set up remote and check out the branch

> **Important:** All `git` and `drupalorg` commands from this point forward must be run from
> the project module directory (not the Drupal site root). Ensure you have `cd`'d into the
> correct directory before executing any command below.

Once the directory and branch are confirmed:

```bash
drupalorg issue:setup-remote <nid>
drupalorg issue:checkout <nid> <branch>
```

**SSH remote URL check:** `issue:setup-remote` sets the remote URL to HTTPS
(`https://git.drupal.org/issue/<project>-<nid>.git`). Contributors using SSH authentication
must switch to the SSH equivalent before pushing. After the remote is set:

```bash
git remote get-url drupalorg
```

- If the URL starts with `https://`, warn the user and offer to convert it:
  ```bash
  git remote set-url drupalorg git@git.drupal.org:issue/<project>-<nid>.git
  ```
  where `<project>` and `<nid>` match the values from the HTTPS URL.
- If it already starts with `git@`, no action needed.

Report the branch that is now active.

---

### Step 3: Inspect the current MR state

> **Important:** Run all commands from the project module directory.

**Diff the branch first** to understand what has already been changed vs. what is still
missing. Determine the upstream default branch from the fork data (e.g. `main`, `10.3.x`),
then run:

```bash
git diff origin/<default-branch>...HEAD
```

Read this diff carefully before analysing the MR — it is the authoritative record of what
the branch already contains. Do not assume a file is unchanged without checking the diff.

```bash
drupalorg mr:list <nid> --format=llm
```

**If `mr:list` returns no MRs:**
- Report "No MR exists yet for this issue."
- Skip the MR inspection commands below.
- Proceed directly to the work loop (Step 4).
- After the first `git push`, capture the GitLab MR-creation URL printed in the push
  output and surface it to the user. Then re-run `drupalorg mr:list <nid> --format=llm`
  to pick up the newly created MR IID before polling pipeline status.

**If one or more MRs exist**, for the relevant MR (confirm with user if multiple exist):

```bash
drupalorg mr:files <nid> <mr-iid>
drupalorg mr:diff <nid> <mr-iid>
drupalorg mr:status <nid> <mr-iid> --format=llm
```

Summarise:
- What the MR changes (files and diff summary)
- Current pipeline status (passing / failing / pending)
- If the pipeline is failing, fetch logs: `drupalorg mr:logs <nid> <mr-iid>`

**[PAUSE]** Present your analysis of the MR and the pipeline results, then ask:
"What would you like me to work on?"

---

### Step 4: Work loop

> **Important:** Run all `git` and `drupalorg` commands from the project module directory.

Iterate until the pipeline is green or the user asks to stop:

1. Make the requested code changes.
2. If `vendor/bin/phpcs` is available, run it on the module directory and fix any violations
   before proceeding:
   ```bash
   vendor/bin/phpcs <module-path>
   ```
   Do **not** stage or commit files while PHPCS reports errors. Skip this step if PHPCS is
   not installed.
3. Before committing, inspect the project's commit style:
   ```bash
   git log --oneline -5
   ```
   Match the observed style (e.g. conventional commits, `Issue #<nid> by <username>:`, etc.)
   rather than defaulting to any fixed template.
4. Stage only the files you actually modified:
   ```bash
   git add <specific-changed-files>
   ```
5. Commit using the inferred message style:
   ```bash
   git commit -m "<message matching project style>"
   ```
6. Push: `git push`
7. Poll pipeline:
   ```bash
   drupalorg mr:status <nid> <mr-iid> --format=llm
   ```
8. If failing, fetch logs and fix:
   ```bash
   drupalorg mr:logs <nid> <mr-iid>
   ```

**[PAUSE]** After each push, report the pipeline outcome and ask whether to continue
or stop.

---

## Notes

- `issue:setup-remote` is idempotent — safe to re-run.
- `--format=llm` output is optimised for parsing; always use it when reading structured data.
- If the fork has no branches, the contributor has not pushed yet — discuss with the user
  before creating a new MR from the upstream project.
