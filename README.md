Drupal.org CLI
--------------
[![Latest Stable Version](https://poser.pugx.org/mglaman/drupalorg-cli/v/stable)](https://packagist.org/packages/mglaman/drupalorg-cli) [![Total Downloads](https://poser.pugx.org/mglaman/drupalorg-cli/downloads)](https://packagist.org/packages/mglaman/drupalorg-cli) [![Latest Unstable Version](https://poser.pugx.org/mglaman/drupalorg-cli/v/unstable)](https://packagist.org/packages/mglaman/drupalorg-cli) [![License](https://poser.pugx.org/mglaman/drupalorg-cli/license)](https://packagist.org/packages/mglaman/drupalorg-cli)

A command line tool for interfacing with Drupal.org and GitLab (git.drupalcode.org). Uses the Drupal.org REST API and GitLab REST API.

## Requirements

* PHP 8.1 or higher, with cURL support
* [Git](https://git-scm.com/) - Currently required to apply and create patches. Very useful for contributing patches back to an issue.

## Installation

### Installing manually

1. Download the `drupalorg.phar` file from the [latest release](https://github.com/mglaman/drupalorg-cli/releases/latest).

   ```bash
   curl -OL https://github.com/mglaman/drupalorg-cli/releases/latest/download/drupalorg.phar
   ```

2. Rename the file to `drupalorg`, ensure it is executable, and move it into a directory in your PATH (use `echo $PATH` to see your options).

   ```bash
   chmod +x drupalorg.phar
   mv drupalorg.phar /usr/local/bin/drupalorg
   ```

3. Run `drupalorg` and verify you can see the list of available commands.

### Installing via Composer (deprecated)

Use the following command to install the command line tool via Composer:

`composer global require mglaman/drupalorg-cli`

### Installing (Bash) completion

`drupalorg` comes with completion support for all commands, excluding options.

To activate it, either source the completion file or add it to the system-wide completion directory, normally `/etc/bash_completion.d/`.

In your `.bashrc` (or `.profile`) add

```
source [...]/vendor/mglaman/drupalorg-cli/drupalorg-cli-completion.bash
```

### Installing (Zsh) completion

`drupalorg` comes with namespace-aware completion out of the box. If [`jq`](https://jqlang.org/) is installed, the Zsh completion script upgrades itself to use `drupalorg list --format=json` once per shell session and can complete:

* commands and namespace-prefixed commands
* documented long and short options (i.e. flags)
* command aliases such as `is` and `pi`
* positional argument placeholders such as `<nid>`

In those placeholders, angle brackets mean the argument is required, and square brackets mean it is optional. For example, `<nid>` is required and `[nid]` is optional.

Without `jq`, the script falls back to the original command and namespace completion behavior.

Copy the Zsh completion file to `~/.zsh/completions/_drupalorg`:

```sh
mkdir -p ~/.zsh/completions
curl -L https://raw.githubusercontent.com/mglaman/drupalorg-cli/refs/heads/main/drupalorg-cli-completion.zsh -o ~/.zsh/completions/_drupalorg
```

In your `~/.zshrc` add (if not already present):

```sh
fpath=(~/.zsh/completions $fpath)
autoload -Uz compinit
compinit
```

Restart your shell or run `source ~/.zshrc`.

## Updating

Automatic updating is not yet supported. You will need to manually download new releases.

## Usage

Use the 'list' command to see available commands.

```
drupalorg list
```

## Commands

````
Available commands:
  help                      Display help for a command
  list                      List commands
 issue
  issue:apply               Applies the latest patch from an issue.
  issue:branch              Creates a branch for the issue.
  issue:checkout            Check out a branch from the GitLab issue fork.
  issue:get-fork            Show the GitLab issue fork URLs and branches.
  issue:interdiff           Generate an interdiff for the issue from committed local changes.
  issue:link                Opens an issue
  issue:patch               Generate a patch for the issue from committed local changes.
  issue:search        [is]  Searches issues for a project by title keyword.
  issue:setup-remote        Add the GitLab issue fork as a git remote and fetch it.
  issue:show                Show a given issue information.
 maintainer
  maintainer:issues   [mi]  Lists issues for a user, based on maintainer.
  maintainer:release-notes  [rn|mrn] Generate release notes.
 mcp
  mcp:config                Output the Claude Desktop MCP configuration snippet.
  mcp:serve                 Start a Model Context Protocol server over stdio.
 mr
  mr:diff                   Show the unified diff for a merge request.
  mr:files                  List changed files in a merge request.
  mr:list            [mrl]  List merge requests for a Drupal.org issue fork or project.
  mr:logs                   Show failed job traces from the latest pipeline for a merge request.
  mr:status                 Show the pipeline status for a merge request.
 project
  project:issues      [pi]  Lists issues for a project.
  project:kanban            Opens project kanban
  project:link              Opens project page
  project:release-notes [prn] View release notes for a release
  project:releases          Lists available releases
 skill
  skill:install             Installs all drupalorg-cli agent skills into .claude/skills/ in the current directory.
````

## GitLab work items

Some Drupal.org projects have migrated their issue queues to GitLab work items at `git.drupalcode.org`. These projects are detected automatically via `field_project_has_issue_queue` on the project node.

**`project:issues`** fetches from the GitLab API instead of Drupal.org for these projects.

The following commands accept a GitLab work item URL in place of a Drupal.org issue NID:

```bash
drupalorg issue:show https://git.drupalcode.org/project/ai_context/-/work_items/3586157
drupalorg issue:get-fork https://git.drupalcode.org/project/ai_context/-/work_items/3586157
drupalorg mr:list https://git.drupalcode.org/project/ai_context/-/work_items/3586157
```

MR URLs also work directly:

```bash
drupalorg mr:list https://git.drupalcode.org/project/ai_context/-/merge_requests/131
```

## Getting Started

### Working with project issues

If you want to use this to generate patches that you can contribute back to a Drupal project, it's best to work within a cloned repo of that project. To get instructions for cloning a project's repo, visit the "Version Control" tab on the project page.

From within the directory of the project we're working on:

* `drupalorg issue:apply [issue number]` - Create a new branch for the given issue, apply the latest patch on the issue to the new branch, then commit the changes locally.
* `drupalorg issue:patch [issue number]` - Create a new patch for the given issue from the changes committed locally.

## Contributing

### Installing and running from source

1. Clone the repository
2. In the drupalorg-cli directory, run `composer install`
3. Run the script with `./drupalorg`
