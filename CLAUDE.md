# drupalorg-cli

PHP 8.1+ Symfony Console CLI that wraps Drupal.org's REST and JSON:API endpoints. Distributed as a phar.

## Key Commands

| Command | Aliases | Description |
|---|---|---|
| `cache:clear` | `cc` | Clears local API cache |
| `issue:apply` | | Applies latest patch from a Drupal.org issue |
| `issue:branch` | | Creates a local branch for an issue |
| `issue:patch` | | Generates a patch from committed changes |
| `issue:interdiff` | | Generates interdiff between two commits |
| `issue:link` | | Opens issue in browser |
| `issue:show` | | Displays issue details |
| `maintainer:issues` | `mi` | Lists issues for a user |
| `maintainer:release-notes` | `rn`, `mrn` | Generates release notes from git log |
| `project:issues` | `pi` | Lists issues for a project |
| `project:releases` | | Lists available releases |
| `project:release-notes` | `prn` | Displays release notes for a release |
| `project:link` | | Opens project page in browser |
| `project:kanban` | | Opens project kanban in browser |
| `travisci:list` | `tci:l` | Lists Travis CI results for an issue |
| `travisci:watch` | `tci:w` | Polls a Travis CI job until complete |

## Architecture

- `src/Api/Client.php` — Guzzle client with cache + retry middleware; `getGuzzleClient()` exposes it for async use
- `src/Api/DrupalOrg.php` — concurrent async requests (JSON:API contributors, issue details, change records) via `GuzzleHttp\Promise\Utils::settle()`
- `src/Api/CommitParser.php` — extracts usernames from classic `by user:` format and Git trailers (`Co-authored-by:` etc.), extracts NIDs from commit titles
- `src/Api/Request.php` / `Response.php` / `RawResponse.php` — request builder and JSON response wrappers
- `src/Cli/Command/Command.php` — base class; provides `$this->client` (Client), `$this->stdOut/stdErr/stdIn`, `runProcess()`

## Development Commands

```bash
vendor/bin/phpcs src          # PSR-2 code style (line length excluded)
vendor/bin/phpstan analyse src  # Static analysis, level 6
vendor/bin/phpunit            # Unit tests (tests/src/)
composer box-install && composer box-build  # Build phar
```

## Key Conventions

- PSR-2 code style (line length not enforced)
- PHPStan level 6 with strict + deprecation rules
- Never edit `composer.lock` directly — use `composer require` / `composer update`
- Use concurrent async Guzzle requests (`requestAsync` + `Utils::settle()`) when fetching multiple Drupal.org nodes

## Skills

- `/phpstan-fix` — Run PHPStan and fix all reported errors in `src/`
- `/pr-check` — Run the full local CI suite before opening a PR
