---
name: drupalorg-cli
description: >
  CLI for Drupal.org issue lifecycle management. Use when fetching issue details,
  generating patches/interdiffs, listing project or maintainer issues, looking up
  releases, or working with GitLab merge requests on issue forks. Also supports
  projects that have migrated to GitLab work items. Pass --format=llm to every
  read command for structured XML output optimised for agent consumption.
allowed-tools: Bash(drupalorg:*), Bash(drupalorg skill:*)
hidden: true
---

# drupalorg-cli

CLI for Drupal.org issue lifecycle management. Covers the full contribution
workflow: browsing issues, creating branches, generating patches and interdiffs,
applying patches, and working with GitLab issue forks and merge requests.

## Start here

This file is a discovery stub, not the usage guide. Before running any
`drupalorg` command, load the actual content from the CLI:

```bash
drupalorg skill:get drupalorg-cli         # workflows, commands, output formats
drupalorg skill:get drupalorg-cli --full  # include workflow reference guides
```

The CLI serves skill content that always matches the installed version,
so instructions never go stale.

## Specialized skills

```bash
drupalorg skill:get drupalorg-work-on-issue         # end-to-end GitLab MR contribution workflow
drupalorg skill:get drupalorg-issue-search          # search issues across API, scrape, and web
drupalorg skill:get drupalorg-issue-summary-update  # analyse and update issue summaries
```
