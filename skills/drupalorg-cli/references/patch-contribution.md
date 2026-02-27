# Patch-Based Contribution Workflow

Classic patch contribution flow for Drupal.org issues that still use file
attachments (as opposed to GitLab merge requests).

---

## Prepare a Patch

```bash
# 1. Fetch the issue to understand scope and current state
drupalorg issue:show <nid> --format=llm

# 2. Create a local branch named after the issue
drupalorg issue:branch <nid>
#    Creates: <nid>-short-issue-title (based on issue title)

# 3. Make your code changes, then stage and commit
git add -p
git commit -m "Issue #<nid> by <username>: <short description>"

# 4. Generate the patch (diffs against the upstream tracking branch)
drupalorg issue:patch [nid]
#    Writes: <nid>-short-title.patch in the current directory

# 5. Upload the patch file to the issue on drupal.org
```

`issue:patch` auto-detects the NID from the branch name when run without an
argument, so omitting `[nid]` is the typical usage.

---

## Generate an Interdiff for a Re-Roll

When you revise a patch after reviewer feedback, upload an interdiff alongside
the new patch so reviewers can see what changed between versions.

```bash
# 1. Commit your updated changes on the issue branch
git add -p
git commit -m "Issue #<nid> by <username>: Address review feedback"

# 2. Generate the interdiff (compares the two latest commits on the branch)
drupalorg issue:interdiff [nid]
#    Writes: <nid>-N-Mof N.diff in the current directory

# 3. Generate the updated patch
drupalorg issue:patch [nid]

# 4. Upload both files to the issue
```

---

## Test a Patch from an Issue

```bash
# Download and apply the latest patch attached to the issue
drupalorg issue:apply <nid>
#    Fetches the most recent .patch file and applies it via `git apply`

# Run the project's test suite to verify
```

---

## Investigate a Bug Before Patching

```bash
# 1. Fetch the full issue (richest context for agents)
drupalorg issue:show <nid> --format=llm
#    Output includes: title, status, priority, contributors,
#    change records, issue summary, and file attachments.

# 2. Apply the latest patch to test the reported behaviour locally
drupalorg issue:apply <nid>
```

---

## Error Reference

| Error | Cause | Recovery |
|-------|-------|----------|
| `No patch found on issue` | Issue has no file attachments | Confirm files exist via `issue:show --format=llm` |
| `No branch configured` | Run outside a git repo or tracking branch missing | Run `issue:branch <nid>` first |
| `patch does not apply` | Patch is stale against current codebase | Re-roll: update your branch to current HEAD, re-apply manually, then `issue:patch` |
