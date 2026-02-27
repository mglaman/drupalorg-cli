# GitLab MR Contribution Workflow

Reference guide for contributing to Drupal.org issues via GitLab merge requests.

---

## Initial Setup

### 1. Discover the fork

```bash
drupalorg issue:get-fork <nid> --format=llm
```

Outputs:
- `remote_name` — git remote alias (e.g. `issue/3001234`)
- `ssh_url` / `https_url` — fork clone URLs
- `gitlab_project_path` — full GitLab path
- `branches` — existing branches on the fork

If `branches` is empty, no one has pushed to the fork yet. You may need to
create the fork and push an initial branch via GitLab's web UI first.

### 2. Add the fork as a git remote

```bash
drupalorg issue:setup-remote <nid>
```

This is idempotent: if the remote already exists it skips the `git remote add`
step and always runs `git fetch` to update remote refs.

### 3. Check out an issue branch

```bash
# In interactive mode: presents a choice of available branches
drupalorg issue:checkout <nid>

# In non-interactive / agent mode: specify the branch explicitly
drupalorg issue:checkout <nid> <branch-name>
```

Branch names follow the convention `<nid>-<short-slug>`, e.g. `3001234-fix-cache`.

---

## Making Changes

```bash
# Edit files, then stage and commit
git add -p
git commit -m "Issue #<nid> by <username>: <short description>"

# Push to the fork (tracking is set up automatically by issue:checkout)
git push
```

Pushing to a tracking branch automatically updates the existing MR on GitLab.
No separate MR update step is needed.

---

## Monitoring the MR

### List MRs for the issue

```bash
# Default: opened MRs only
drupalorg mr:list <nid> --format=llm

# Include other states
drupalorg mr:list <nid> --state=merged --format=llm
drupalorg mr:list <nid> --state=all --format=llm
```

`--format=llm` output includes IID, title, source branch, state, mergeability,
author, and last-updated timestamp for each MR.

### Review MR content

```bash
# Files changed in the MR
drupalorg mr:files <nid> <mr-iid>

# Full unified diff
drupalorg mr:diff <nid> <mr-iid>
```

`mr:diff` and `mr:files` support `--format=text` (default) and `--format=json`.

### Check pipeline status

```bash
drupalorg mr:status <nid> <mr-iid> --format=llm
```

`--format=llm` output includes: MR IID, pipeline status, pipeline ID, and URL.

### Debug pipeline failures

```bash
drupalorg mr:logs <nid> <mr-iid>
```

Prints the trace excerpt for each failed job in the latest pipeline. Use
`--format=json` to get the raw structured data.

---

## Typical Iteration Cycle

```
issue:get-fork  → issue:setup-remote  → issue:checkout
  ↓
mr:list → mr:diff / mr:files → mr:status → mr:logs
  ↓
  edit → git commit → git push
  ↓
mr:status (wait for pipeline) → mr:logs (if failed)
  ↓ (repeat until green)
```

---

## Format Support Summary

| Command | text | json | md | llm |
|---------|------|------|----|-----|
| `issue:get-fork` | ✓ | ✓ | ✓ | ✓ |
| `issue:setup-remote` | ✓ | — | — | — |
| `issue:checkout` | ✓ | — | — | — |
| `mr:list` | ✓ | ✓ | ✓ | ✓ |
| `mr:diff` | ✓ | ✓ | — | — |
| `mr:files` | ✓ | ✓ | — | — |
| `mr:status` | ✓ | ✓ | ✓ | ✓ |
| `mr:logs` | ✓ | ✓ | — | — |
