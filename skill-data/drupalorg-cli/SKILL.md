---
name: drupalorg-cli
description: >
  CLI for Drupal.org issue lifecycle management. Use when fetching issue details,
  generating patches/interdiffs, listing project or maintainer issues, looking up
  releases, or working with GitLab merge requests on issue forks. Also supports
  projects that have migrated to GitLab work items. Pass --format=llm to every
  read command for structured XML output optimised for agent consumption.
---

## Overview

`drupalorg-cli` (invoked as `drupalorg`) wraps Drupal.org's REST and GitLab REST
APIs. It covers the full contribution lifecycle: browsing issues, creating
branches, generating patches and interdiffs, applying patches, working with GitLab
issue forks and merge requests, and browsing releases.

Some Drupal.org projects have migrated their issue queues to GitLab work items
at `git.drupalcode.org`. These projects are detected automatically — `project:issues`
fetches from the GitLab API instead of Drupal.org for them.

## Contribution Policy

Drupal.org's [policy on the use of AI when contributing](https://www.drupal.org/docs/develop/issues/issue-procedures-and-etiquette/policy-on-the-use-of-ai-when-contributing-to-drupal)
applies to every change made through this CLI. The user is the contributor and
answers for everything pushed or posted. Non-negotiables for agents:

- **Read the thread first.** Run `issue:show <nid> --with-comments --format=llm`
  and `mr:list <nid> --state=all --format=llm` before proposing a change. Respect
  prior attempts and settled architectural decisions.
- **Minimal, explainable diffs.** No out-of-scope refactors, no unverified
  dependencies. The user must be able to explain every line to a reviewer.
- **Green before hand-off.** Run local checks, push, poll `mr:status`, fix
  `mr:logs` failures. Never leave a failing MR for others.
- **Never push to someone else's MR** without their knowledge and a disclosed
  comment. Check the `author` field in `mr:list` output.
- **Disclose.** When AI produced entire functions, classes, scaffolding, or long
  doc blocks, draft an `AI-Generated: Yes (...)` line for the MR description or
  comment and confirm the user added it.
- **The user's own words.** Summaries, comments, and reviews are drafts for the
  user to edit and shorten, never text to paste verbatim.
- **Stay responsive.** Remind the user that follow-up feedback needs a human
  answer. Drive-by contributions lead to account bans.

The full checklist is in `references/ai-contribution-policy.md`.

### Work item references

`issue:show`, `issue:get-fork`, and `mr:list` all accept a **WorkItemRef** in
place of a plain Drupal.org NID:

| Format | Example |
|--------|---------|
| D.o NID | `3586157` |
| Shorthand | `ai_context#3586157` |
| Explicit path | `project/ai_context#3586157` |
| Full URL | `https://git.drupalcode.org/project/ai_context/-/work_items/3586157` |

```bash
drupalorg <command> [arguments]
```

## Output Formats

Commands that fetch data accept `--format` / `-f`:

| Format | Description | Commands |
|--------|-------------|---------|
| `text` | Human-readable plain text (default) | All commands |
| `json` | Machine-readable JSON | Most commands |
| `md`   | Markdown suitable for display or copy-paste | `issue:show`, `issue:get-fork`, `mr:list`, `mr:status`, `mr:show`, `mr:files`, `mr:diff`, `project:issues`, `project:releases`, `project:release-notes`, `project:contains`, `maintainer:issues` |
| `llm`  | Structured XML optimised for agent consumption | `issue:show` (add `--with-comments` to include comment thread), `issue:get-fork`, `mr:list`, `mr:status`, `mr:show` (add `--with-comments` for review notes), `mr:files`, `mr:diff`, `project:issues`, `project:releases`, `project:release-notes`, `project:contains`, `maintainer:issues` |

**Agents should always pass `--format=llm`** to get rich, structured output
with clearly labelled fields, contributor lists, and change records.

## Command Reference

### Issue commands

`<nid>` accepts a D.o NID, shorthand (`ai_context#3586157`), or full GitLab work item URL.

```bash
# Fetch full details for an issue (D.o or GitLab work item)
drupalorg issue:show <nid> --format=llm

# Fetch issue details including all comments (skips system-generated messages)
# Works for both D.o issues and GitLab work items
drupalorg issue:show <nid> --with-comments --format=llm

# GitLab work items only: also keep drupalbot replies to slash commands.
# Off by default because the work item's labels and assignees already reflect them.
drupalorg issue:show <ref> --with-comments --include-bot-comments --format=llm

# Show the GitLab issue fork URLs and branches
# nid is optional; auto-detected from the branch name if omitted
# <exists>false</exists> means nobody has clicked "Create issue fork" yet;
# an empty <branches> list with <exists>true</exists> means the fork has no pushes
drupalorg issue:get-fork [nid] --format=llm

# Add the GitLab issue fork as a git remote and fetch it
# nid is optional; auto-detected from the branch name if omitted
# Fails without adding a remote when the fork does not exist yet
drupalorg issue:setup-remote [nid]

# Check out a branch from the GitLab issue fork
# branch is optional; prompts interactively when omitted
# Requires: issue:setup-remote must have been run first
drupalorg issue:checkout [nid] [branch]

# Create a local git branch named after the issue
# Accepts a D.o NID, shorthand ref, or work item URL. For work items the base
# branch comes from the version label (v2.0.x-dev) or the project default branch
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

The first argument can be a Drupal.org issue NID, a `project-path!iid` ref
(e.g. `project/drupal!708`), or a full GitLab MR URL. When using a ref that
includes the MR IID (`!iid`), the second `<mr-iid>` argument is not needed.

> **zsh users:** Escape `!` or quote the argument to prevent history expansion:
> `project/drupal\!708` or `'project/drupal!708'`

```bash
# List merge requests for a Drupal.org issue fork
# --state: opened (default), closed, merged, all
# nid is optional; auto-detected from the branch name if omitted
# Only MRs opened from the issue fork are returned; empty means no fork or no MRs
drupalorg mr:list [nid] [--state=opened] --format=llm
# List every MR on a project (not scoped to an issue)
drupalorg mr:list project/drupal --format=llm

# Show the unified diff for a merge request
# Supports --format=text (default), json, md, llm
drupalorg mr:diff <nid> <mr-iid> --format=llm
drupalorg mr:diff 'project/drupal!708' --format=llm

# List changed files in a merge request
# Supports --format=text (default), json, md, llm
drupalorg mr:files <nid> <mr-iid> --format=llm
drupalorg mr:files 'project/drupal!708' --format=llm

# Show the pipeline status for a merge request
drupalorg mr:status <nid> <mr-iid> --format=llm
drupalorg mr:status 'project/drupal!708' --format=llm

# Show whether a merge request is ready: draft, detailed_merge_status,
# has_conflicts, blocking_discussions_resolved, head pipeline, and the work
# items it closes (an "Issue #N:" title does not close N; closes_issues shows
# what will). has_conflicts is "unknown" until GitLab checks mergeability;
# run again shortly. Supports --format=text (default), json, md, llm
drupalorg mr:show 'project/drupal!708' --format=llm
# --with-comments adds non-system notes, with file and line for inline notes.
# drupalbot notes are skipped unless --include-bot-comments. Comments need a
# GitLab token; without one, the MR state still prints with a comments_error.
drupalorg mr:show <nid> <mr-iid> --with-comments --format=llm
# A #note_<id> link implies --with-comments and marks that note with target="true".
# If the note is not on the MR, <target_note_id missing="true"> says so
drupalorg mr:show 'https://git.drupalcode.org/project/drupal/-/merge_requests/708#note_3625786' --format=llm

# Show failed job traces from the latest pipeline for a merge request
# Supports --format=text (default), json
drupalorg mr:logs <nid> <mr-iid>
drupalorg mr:logs 'project/drupal!708'
```

### Slash commands (GitLab work items only)

These commands post Drupal.org bot quick-actions as comments on a GitLab work item.
They only work for projects whose issue queue lives at `git.drupalcode.org` (the bot
is not present on classic Drupal.org issue queues).

The `<ref>` argument is a WorkItemRef — bare NID, `project_name#nid`, or full URL.

Each command also accepts `--format=text|json|md|llm`. Posting is asynchronous: the
bot processes the comment after it lands, so re-fetch with `--no-cache` to confirm.

```bash
# Create a fork (and a default-branch issue branch) for an issue without a fork
drupalorg issue:fork <ref>

# Grant the current user push access to the existing fork
drupalorg issue:get-access <ref>

# Assign one or more users (default: me)
drupalorg issue:assign <ref> [user...]
drupalorg issue:unassign <ref> [user...]
drupalorg issue:reassign <ref> <user> [user...]

# Manage labels (e.g. state::needsReview, state::rtbc, state::needsWork)
drupalorg issue:label <ref> <label> [label...]
drupalorg issue:unlabel <ref> <label> [label...]
drupalorg issue:relabel <ref> <label> [label...]
```

Post a plain comment on a work item. Pass exactly one of `--message` or `--file`.
Use `--file` for multi-paragraph Markdown; `--file=-` reads stdin. Prints the note URL.

```bash
drupalorg issue:comment <ref> --message="Thanks for retesting."
drupalorg issue:comment <ref> --file=note.md
cat note.md | drupalorg issue:comment <ref> --file=-
```

Link two work items, in the same project or across projects. `--type` is `relates_to`
(default), `blocks`, or `is_blocked_by`. GitLab requires membership in both projects;
on a 403 the command prints `issue:comment` commands to post a note on each side instead.

```bash
drupalorg issue:relate <ref> <target-ref>
drupalorg issue:relate <ref> <target-ref> --type=is_blocked_by
# Linking an already-linked pair succeeds and reports already_linked.
```

Authentication requires a GitLab token. The CLI reads `DRUPALORG_GITLAB_TOKEN` and
falls back to `glab config get token --host git.drupalcode.org` when that env var is
unset.

### Project commands

```bash
# List open issues for a project
# For projects using GitLab work items, fetches from GitLab API automatically
# type: all (default), rtbc, or review; --core defaults to 8.x; --limit defaults to 10
# --category filters by issue type: bug, task, feature, support, plan (omit for all categories)
# Note: type/core/category filters only apply to D.o issue queue projects
drupalorg project:issues [project] [type] [--category=bug|task|feature|support|plan] --format=llm

# Search issues for a project by title keyword
# project is optional; auto-detected from git remote if omitted
# --status: all (default), open, closed, rtbc, review; --limit defaults to 20
drupalorg project:search [project] <query> [--status=all] [--limit=20] --format=llm

# List available releases for a project
drupalorg project:releases <project> --format=llm

# Display release notes for a specific release
drupalorg project:release-notes <project> <release> --format=llm

# List the branches and tags that contain a commit, without a local clone
# Accepts a full or short SHA; project is auto-detected from git remote if omitted
# Tags are ordered oldest first, so the first tag is the release that shipped it
drupalorg project:contains [project] <sha> --format=llm

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
# Install the drupalorg-cli agent skill into .claude/skills/drupalorg-cli/
drupalorg skill:install

# List the skills bundled with the installed CLI (name + description)
drupalorg skill:get --format=llm

# Output a skill's content; --full appends its reference files
drupalorg skill:get <name> [--full]
```

## Cache Bypass

Drupal.org uses HTTP caching (CDN/Varnish). If you need fresh data — e.g. after a
new comment was posted — pass `--no-cache` to any command:

```bash
drupalorg issue:show <nid> --with-comments --format=llm --no-cache
drupalorg mr:list [nid] --format=llm --no-cache
```

`--no-cache` sends `Cache-Control: no-cache, no-store, must-revalidate` and
`Pragma: no-cache` headers so the upstream CDN returns a fresh response.

## Error Handling

| Error | Cause | Recovery |
|-------|-------|----------|
| `Node not found` | Invalid or private issue NID, or a GitLab work item NID passed to a D.o-only command | Use a WorkItemRef instead: `ai_context#3586157` |
| `Issue … moved to a GitLab work item` | The D.o issue migrated to GitLab and the command has no work item support | Pass the ref the message names, e.g. `restrict_route_by_ip#3617735`, to a command that supports work items |
| `404 Project Not Found` (GitLab) | D.o issue NID used with a GitLab work item project — D.o node has no `field_project` | Pass the full work item URL or shorthand ref |
| `No patch found on issue` | Issue has no file attachments | Check `issue:show` to confirm files exist |
| `No branch configured` | `issue:patch` run outside a git repo or without a tracking branch | Run `issue:branch <nid>` first |
| `Remote … does not exist` | `issue:checkout` run before `issue:setup-remote` | Run `issue:setup-remote <nid>` first |
| `No issue fork for … yet` | `issue:setup-remote` or `issue:checkout` run before the fork was created on GitLab | Create the fork from the issue page (or `issue:fork <ref>` on work item projects), then retry |
| `429 / 503` | Drupal.org rate limit or maintenance | The client retries automatically; wait and retry if it persists |

## References

Detailed workflow guides are in the `references/` directory alongside this file:

- `references/ai-contribution-policy.md` — Drupal.org AI contribution policy checklist (read the thread, verify, disclose)
- `references/work-on-issue.md` — End-to-end GitLab MR workflow ("Work on this issue")
- `references/patch-contribution.md` — Classic patch-based contribution workflow
- `references/gitlab-mr-contribution.md` — GitLab MR contribution workflow reference
