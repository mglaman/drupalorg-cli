<?php

namespace mglaman\DrupalOrgCli\Command\Skill;

use mglaman\DrupalOrgCli\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Install extends Command
{

    private const SKILL_CONTENT = <<<'SKILL'
---
name: drupalorg-cli
description: >
  CLI for Drupal.org issue lifecycle management. Use when fetching issue details,
  generating patches/interdiffs, listing project or maintainer issues, or looking
  up releases. Pass --format=llm to every read command for structured XML output
  optimised for agent consumption.
---

## Overview

`drupalorg-cli` (invoked as `drupalorg`) wraps Drupal.org's REST and JSON:API
endpoints. It covers the full contribution lifecycle: browsing issues, creating
branches, generating patches and interdiffs, applying patches, and browsing
releases.

```bash
drupalorg <command> [arguments] [--format=text|json|md|llm]
```

## Output Formats

Every read command accepts `--format` / `-f`:

| Format | Description |
|--------|-------------|
| `text` | Human-readable plain text (default) |
| `json` | Machine-readable JSON |
| `md`   | Markdown suitable for display or copy-paste |
| `llm`  | Structured XML optimised for agent consumption |

**Agents should always pass `--format=llm`** to get rich, structured output
with clearly labelled fields, contributor lists, and change records.

## Command Reference

### Issue commands

```bash
# Fetch full details for an issue
drupalorg issue:show <nid> --format=llm

# Create a local git branch named after the issue
drupalorg issue:branch <nid>

# Generate a patch from committed (but not yet pushed) changes
drupalorg issue:patch

# Generate an interdiff between two commits
drupalorg issue:interdiff <from-commit> <to-commit>

# Download and apply the latest patch attached to an issue
drupalorg issue:apply <nid>

# Open the issue page in the default browser
drupalorg issue:link <nid>
```

### Project commands

```bash
# List open issues for a project
drupalorg project:issues <project> --format=llm

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
drupalorg maintainer:issues <username> --format=llm

# Generate release notes from git log for a maintainer's project
drupalorg maintainer:release-notes
```

### Utility commands

```bash
# Clear the local API response cache
drupalorg cache:clear

# Install the drupalorg-cli agent skill into your project
drupalorg skill:install [--path=.claude/skills]
```

## Workflow Playbooks

### Investigate a bug report

```bash
# 1. Fetch the full issue (use llm format for richest context)
drupalorg issue:show 3001234 --format=llm

# 2. Read the XML output — it contains title, status, priority,
#    contributors, change records, and the issue summary.

# 3. Apply the latest patch to test it locally
drupalorg issue:apply 3001234
```

### Prepare a patch contribution

```bash
# 1. Create a local branch named after the issue
drupalorg issue:branch 3001234

# 2. Make your code changes and commit them
git add -p
git commit -m "Issue #3001234 by username: Fix the thing"

# 3. Generate the patch file (uses git diff against the upstream branch)
drupalorg issue:patch

# 4. The patch file is written to the current directory.
#    Upload it to the issue on drupal.org.
```

### Generate an interdiff for a re-roll

```bash
# After committing an updated patch on top of the previous one:
drupalorg issue:interdiff <previous-commit-sha> <new-commit-sha>
```

### Browse project releases

```bash
# List all releases to find version strings
drupalorg project:releases drupal --format=llm

# Read the release notes for a specific release
drupalorg project:release-notes drupal 10.3.6 --format=llm
```

## Error Handling

| Error | Cause | Recovery |
|-------|-------|----------|
| `Node not found` | Invalid or private issue NID | Verify the NID on drupal.org |
| `No patch found on issue` | Issue has no file attachments | Check `issue:show` to confirm files exist |
| `No branch configured` | `issue:patch` run outside a git repo or without a tracking branch | Run `issue:branch <nid>` first |
| `429 / 503` | Drupal.org rate limit or maintenance | The client retries automatically; wait and retry if it persists |
| `Cache stale` | Old API response cached locally | Run `drupalorg cache:clear` and retry |
SKILL;

    protected function configure(): void
    {
        $this
            ->setName('skill:install')
            ->addOption(
                'path',
                null,
                InputOption::VALUE_OPTIONAL,
                'Destination directory for the skill file.',
                '.claude/skills'
            )
            ->setDescription('Installs the drupalorg-cli agent skill into your project.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $basePath = (string) $this->stdIn->getOption('path');
        $dir = rtrim($basePath, '/') . '/drupalorg-cli';
        $fullPath = $dir . '/SKILL.md';

        if (!is_dir($dir) && !mkdir($dir, 0755, true) && !is_dir($dir)) {
            $this->stdErr->writeln(sprintf('<error>Failed to create directory: %s</error>', $dir));
            return 1;
        }

        if (file_put_contents($fullPath, self::SKILL_CONTENT) === false) {
            $this->stdErr->writeln(sprintf('<error>Failed to write skill file: %s</error>', $fullPath));
            return 1;
        }

        $this->stdOut->writeln(sprintf('Skill installed to %s', $fullPath));
        return 0;
    }
}
