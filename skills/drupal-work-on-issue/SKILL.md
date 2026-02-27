# /drupal-work-on-issue

**Purpose:** Agentic workflow for contributing to a Drupal.org issue via GitLab MR.

**Usage:** `/drupal-work-on-issue <nid>`

---

## Instructions

When the user invokes `/drupal-work-on-issue <nid>`, execute the following workflow. Pause at
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

**[PAUSE]** Ask the user:
1. "Does your current working directory contain the `<project>` repository?" (show `git remote get-url origin` to verify)
2. "Which branch should I check out?" (list branches from the fork output; if none exist, note that and ask how to proceed)

---

### Step 2: Set up remote and check out the branch

Once the user confirms the directory and branch:

```bash
drupalorg issue:setup-remote <nid>
drupalorg issue:checkout <nid> <branch>
```

Report the branch that is now active.

---

### Step 3: Inspect the current MR state

```bash
drupalorg mr:list <nid> --format=llm
```

For the relevant MR (confirm with user if multiple exist):

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

Iterate until the pipeline is green or the user asks to stop:

1. Make the requested code changes.
2. Commit:
   ```bash
   git add -p
   git commit -m "Issue #<nid> by <username>: <short description>"
   ```
3. Push: `git push`
4. Poll pipeline:
   ```bash
   drupalorg mr:status <nid> <mr-iid> --format=llm
   ```
5. If failing, fetch logs and fix:
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
