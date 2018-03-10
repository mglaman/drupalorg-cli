Drupal.org CLI
--------------
[![Latest Stable Version](https://poser.pugx.org/mglaman/drupalorg-cli/v/stable)](https://packagist.org/packages/mglaman/drupalorg-cli) [![Total Downloads](https://poser.pugx.org/mglaman/drupalorg-cli/downloads)](https://packagist.org/packages/mglaman/drupalorg-cli) [![Latest Unstable Version](https://poser.pugx.org/mglaman/drupalorg-cli/v/unstable)](https://packagist.org/packages/mglaman/drupalorg-cli) [![License](https://poser.pugx.org/mglaman/drupalorg-cli/license)](https://packagist.org/packages/mglaman/drupalorg-cli)

A command line tool for interfacing with Drupal.org. Uses the Drupal.org REST API.

## Commands

````
Available commands:
  help                         Displays help for a command
  list                         Lists commands
 cache
  cache:clear (cc)             Clears caches
 ci
 drupalci
  drupalci:list (ci:l)         Lists test results for an issue
  drupalci:watch (ci:w)        Watches a Drupal CI job
 issue
  issue:apply                  Applies the latest patch from an issue.
  issue:branch                 Creates a branch for the issue.
  issue:link                   Opens an issue
  issue:patch                  Opens project kanban
 maintainer
  maintainer:issues (mi)       Lists issues for a user, based on maintainer.
 project
  project:issues (pi)          Lists issues for a project.
  project:kanban               Opens project kanban
  project:link                 Opens project page
  project:release-notes (rn)   View release notes for a release
  project:releases             Lists available releases
 tci
 travisci
  travisci:list (tci:l)        Lists Travis Ci builds for a Drupal project
  travisci:watch (tci:w)       Watches a Travis CI job
````

## Getting Started

### Requirements

* [git](https://git-scm.com/)
* [composer](https://getcomposer.org/)

### Installation

After installing git and composer:

`composer global require mglaman/drupalorg-cli`

### Use

Since this tool uses git for a significant amount of its operations, we must first clone a project from drupal.org:

`git clone --branch 8.x-1.x https://git.drupal.org/project/[project name].git && cd [project name]` 

From within the directory of the project git repo we've cloned:

* `drupalorg issue:apply [issue number]` - Create a new branch for the given issue, apply the latest patch on the issue to the new branch, then commit the changes locally.
* `drupalorg issue:patch [issue number]` - Create a new patch for the given issue from the changes committed locally.
