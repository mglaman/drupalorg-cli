# Work on This Issue — Agentic Workflow

This guide describes the end-to-end workflow for an agent to pick up a Drupal.org
issue, set up the environment, and contribute via GitLab merge request.

---

## Step 1: Verify the Issue Fork

Fetch the fork details to confirm a GitLab fork exists and discover available branches.

```bash
drupalorg issue:get-fork <nid> --format=llm
```

The `--format=llm` output includes:
- `remote_name` — the git remote alias to use (format `<projectMachineName>-<nid>`, e.g. `drupal-3574743`)
- `ssh_url` / `https_url` — clone URLs for the fork
- `gitlab_project_path` — the GitLab namespace/path for the fork (e.g. `issue/drupal-3574743`)
- `branches` — branches that exist on the fork (empty if fork does not exist yet)

**If no branches appear**, the contributor has not pushed to their fork yet.
Check `issue:show <nid> --format=llm` to read the issue summary and decide
whether to wait or open a fresh MR from the main project.

---

## Step 2: Verify Your Working Directory

Confirm your current directory matches the project for this issue.

```bash
drupalorg issue:show <nid> --format=llm
```

The `<project>` field in the LLM output contains the project machine name
(e.g. `drupal`, `commerce`, `views`). Your `git remote get-url origin` should
resolve to a repository under that project name on git.drupal.org or GitLab.

If you are in the wrong directory, `cd` to the correct project root before
proceeding.

---

## Step 3: Set Up the Remote and Check Out the Branch

```bash
# Add the fork as a git remote and fetch its branches
drupalorg issue:setup-remote <nid>

# Check out an issue branch from the fork
# Pass the branch name explicitly in non-interactive (agent) contexts
drupalorg issue:checkout <nid> <branch>
```

`issue:setup-remote` is idempotent — it skips adding the remote if it already
exists and always fetches the latest refs.

`issue:checkout` checks out the branch with tracking so that `git push` and
`git pull` target the fork automatically.

To discover available branch names without checking one out:

```bash
drupalorg issue:get-fork <nid> --format=llm
# Read the <branches> element for the list
```

---

## Step 4: The Work Loop

Once on the issue branch, iterate through read → edit → commit → push cycles.

### Inspect the current MR state

```bash
# List open MRs for the issue
drupalorg mr:list <nid> --format=llm

# Review what files have changed in a specific MR
drupalorg mr:files <nid> <mr-iid>

# Read the full diff
drupalorg mr:diff <nid> <mr-iid>

# Check pipeline status
drupalorg mr:status <nid> <mr-iid> --format=llm

# Read failed pipeline job logs
drupalorg mr:logs <nid> <mr-iid>
```

### Make changes and push

```bash
# Edit files as needed, then:
git add -p
git commit -m "Issue #<nid> by <username>: <short description>"
git push
```

Because `issue:checkout` sets up tracking, `git push` targets the fork branch
directly, which updates the existing MR automatically.

### Check pipeline after push

```bash
# Poll status after pushing (wait for GitLab CI to start the pipeline)
drupalorg mr:status <nid> <mr-iid> --format=llm

# If the pipeline fails, read the logs
drupalorg mr:logs <nid> <mr-iid>
```

Repeat the loop until the pipeline is green.

---

## Quick Reference: Command Sequence

```
issue:get-fork <nid> --format=llm   # confirm fork & branches
issue:show <nid> --format=llm       # verify project & context
issue:setup-remote <nid>            # add/refresh git remote
issue:checkout <nid> <branch>       # switch to issue branch

mr:list <nid> --format=llm          # list open MRs
mr:files <nid> <mr-iid>             # see changed files
mr:diff <nid> <mr-iid>              # read full diff
  → edit, commit, push
mr:status <nid> <mr-iid> --format=llm  # check pipeline
mr:logs <nid> <mr-iid>              # debug failures
```
