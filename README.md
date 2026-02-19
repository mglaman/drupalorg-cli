Drupal.org CLI
--------------
[![Latest Stable Version](https://poser.pugx.org/mglaman/drupalorg-cli/v/stable)](https://packagist.org/packages/mglaman/drupalorg-cli) [![Total Downloads](https://poser.pugx.org/mglaman/drupalorg-cli/downloads)](https://packagist.org/packages/mglaman/drupalorg-cli) [![Latest Unstable Version](https://poser.pugx.org/mglaman/drupalorg-cli/v/unstable)](https://packagist.org/packages/mglaman/drupalorg-cli) [![License](https://poser.pugx.org/mglaman/drupalorg-cli/license)](https://packagist.org/packages/mglaman/drupalorg-cli)

A command line tool for interfacing with Drupal.org. Uses the Drupal.org REST API.

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
source [...]/vendor/mglaman/drupalorg-cli/drupalorg-cli-completion.sh
```

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
  help                                 Displays help for a command
  list                                 Lists commands
 cache
  cache:clear (cc)                     Clears caches
 ci
 drupalci
  drupalci:list (ci:l)                 Lists test results for an issue
  drupalci:watch (ci:w)                Watches a Drupal CI job
 issue
  issue:apply                          Applies the latest patch from an issue.
  issue:branch                         Creates a branch for the issue.
  issue:interdiff                      Generate an interdiff for the issue from local changes.
  issue:link                           Opens an issue
  issue:patch                          Generate a patch for the issue from committed local changes.
 maintainer
  maintainer:issues (mi)               Lists issues for a user, based on maintainer.
  maintainer:release-notes (rn, mrn)   Generate release notes.
 project
  project:issues (pi)                  Lists issues for a project.
  project:kanban                       Opens project kanban
  project:link                         Opens project page
  project:release-notes (prn)          View release notes for a release
  project:releases                     Lists available releases
 tci
 travisci
  travisci:list (tci:l)                Lists Travis Ci builds for a Drupal project
  travisci:watch (tci:w)               Watches a Travis CI job
````

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
