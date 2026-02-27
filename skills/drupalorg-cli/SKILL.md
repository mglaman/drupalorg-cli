---
name: drupalorg-cli
description: >
  CLI for Drupal.org issue lifecycle management. Use when fetching issue details,
  generating patches/interdiffs, listing project or maintainer issues, looking up
  releases, or working with GitLab merge requests on issue forks. Pass --format=llm
  to every read command for structured XML output optimised for agent consumption.
---

## Overview

`drupalorg-cli` (invoked as `drupalorg`) wraps Drupal.org's REST and JSON:API
endpoints. It covers the full contribution lifecycle: browsing issues, creating
branches, generating patches and interdiffs, applying patches, working with GitLab
issue forks and merge requests, and browsing releases.

```bash
drupalorg <command> [arguments]
```

## Output Formats

Commands that fetch data accept `--format` / `-f`:

| Format | Description | Commands |
|--------|-------------|---------|
| `text` | Human-readable plain text (default) | All commands |
| `json` | Machine-readable JSON | Most commands |
| `md`   | Markdown suitable for display or copy-paste | `issue:show`, `issue:get-fork`, `mr:list`, `mr:status`, `project:*`, `maintainer:issues` |
| `llm`  | Structured XML optimised for agent consumption | `issue:show`, `issue:get-fork`, `mr:list`, `mr:status`, `project:*`, `maintainer:issues` |

**Agents should always pass `--format=llm`** to get rich, structured output
with clearly labelled fields, contributor lists, and change records.

## Command Reference

### Issue commands

```bash
# Fetch full details for an issue
drupalorg issue:show <nid> --format=llm

# Show the GitLab issue fork URLs and branches
# nid is optional; auto-detected from the branch name if omitted
drupalorg issue:get-fork [nid] --format=llm

# Add the GitLab issue fork as a git remote and fetch it
# nid is optional; auto-detected from the branch name if omitted
drupalorg issue:setup-remote [nid]

# Check out a branch from the GitLab issue fork
# branch is optional; prompts interactively when omitted
# Requires: issue:setup-remote must have been run first
drupalorg issue:checkout [nid] [branch]

# Create a local git branch named after the issue
drupalorg issue:branch <nid>

# Generate a patch from committed (but not yet pushed) changes
# nid is optional; auto-detected from the branch name if omitted
drupalorg issue:patch [nid]

# Generate an interdiff from committed local changes against the upstream branch
# nid is optional; auto-detected from the branch name if omitted
drupalorg issue:interdiff [nid]

# Download and apply the latest patch attached to an issue
drupalorg issue:apply <nid>

# Open the issue page in the default browser
drupalorg issue:link <nid>
```

### Merge Request commands

```bash
# List merge requests for a Drupal.org issue fork
# --state: opened (default), closed, merged, all
# nid is optional; auto-detected from the branch name if omitted
drupalorg mr:list [nid] [--state=opened] --format=llm

# Show the unified diff for a merge request
# Supports --format=text (default), json
drupalorg mr:diff <nid> <mr-iid>

# List changed files in a merge request
# Supports --format=text (default), json
drupalorg mr:files <nid> <mr-iid>

# Show the pipeline status for a merge request
drupalorg mr:status <nid> <mr-iid> --format=llm

# Show failed job traces from the latest pipeline for a merge request
# Supports --format=text (default), json
drupalorg mr:logs <nid> <mr-iid>
```

### Project commands

```bash
# List open issues for a project
# type: all (default) or rtbc; --core defaults to 8.x; --limit defaults to 10
drupalorg project:issues [project] [type] --format=llm

# List available releases for a project
drupalorg project:releases <project> --format=llm

# Display release notes for a specific release
drupalorg project:release-notes <project> <release> --format=llm

# Open the project page in the default browser
drupalorg project:link <project>

# Open the project kanban board in the default browser
drupalorg project:kanban <project>
```

### Maintainer commands

```bash
# List issues assigned to or filed by a maintainer
# type: all (default) or rtbc
drupalorg maintainer:issues <user> [type] --format=llm

# Generate release notes from git log for a maintainer's project
# ref1 = from-tag/SHA; ref2 defaults to HEAD
# --format accepts json|md|html (default: html); llm is not supported
drupalorg maintainer:release-notes <ref1> [ref2] [--format=json|md|html]
```

### Utility commands

```bash
# Install the drupalorg-cli agent skill into your project
drupalorg skill:install [--path=.claude/skills]

# Clear the local API cache
drupalorg cache:clear
```

## Error Handling

| Error | Cause | Recovery |
|-------|-------|----------|
| `Node not found` | Invalid or private issue NID | Verify the NID on drupal.org |
| `No patch found on issue` | Issue has no file attachments | Check `issue:show` to confirm files exist |
| `No branch configured` | `issue:patch` run outside a git repo or without a tracking branch | Run `issue:branch <nid>` first |
| `Remote … does not exist` | `issue:checkout` run before `issue:setup-remote` | Run `issue:setup-remote <nid>` first |
| `429 / 503` | Drupal.org rate limit or maintenance | The client retries automatically; wait and retry if it persists |

## References

Detailed workflow guides are in the `references/` directory alongside this file:

- `references/work-on-issue.md` — End-to-end GitLab MR workflow ("Work on this issue")
- `references/patch-contribution.md` — Classic patch-based contribution workflow
- `references/gitlab-mr-contribution.md` — GitLab MR contribution workflow reference
