# drupalorg-cli

PHP 8.2+ Symfony Console CLI that wraps Drupal.org's REST and JSON:API endpoints. Distributed as a phar.

## Goal

This CLI reads from Drupal.org and automates local merge request workflows. Its design also enables agentic development. Prioritize **Agentic Primitives**: granular, composable commands that can be chained rather than monolithic commands that do everything.

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

## Architecture

### Layers

Every feature follows a strict three-layer structure:

1. **`Action` class** (`src/Api/Action/`) — standalone `readonly` class containing all business logic, API calls, and Git orchestration. Never put this logic in a `Command`.
2. **`Result` DTO** (`src/Api/Result/`) — typed, serializable value object returned by an `Action`. Must implement `ResultInterface`.
3. **`Command` class** (`src/Cli/Command/`) — thin wrapper that invokes an `Action`, then delegates rendering to a formatter based on `--format`.

When refactoring or adding a command, deliver all three pieces.

### Key Files

- `src/Api/Client.php` — Guzzle client with retry middleware (429/503); `getGuzzleClient()` exposes it for async use
- `src/Api/DrupalOrg.php` — concurrent async requests (JSON:API contributors, issue details, change records) via `GuzzleHttp\Promise\Utils::settle()`
- `src/Api/CommitParser.php` — extracts usernames from classic `by user:` format and Git trailers (`Co-authored-by:` etc.), extracts NIDs from commit titles
- `src/Api/Request.php` / `Response.php` / `RawResponse.php` — request builder and JSON response wrappers
- `src/Cli/Command/Command.php` — base class; provides `$this->client` (Client), `$this->stdOut/stdErr/stdIn`, `runProcess()`
- `src/Cli/Formatter/` — output formatters for `--format=json|md|llm`; `AbstractFormatter` centralises result-type dispatch; `JsonFormatter` implements `FormatterInterface` directly (works for any result via `json_encode`)

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
- Use PHP 8.1+ features: constructor promotion, `readonly` classes, union types, strict typing (`declare(strict_types=1)`)
- Use concurrent async Guzzle requests (`requestAsync` + `Utils::settle()`) when fetching multiple Drupal.org nodes
- When adding a new `ResultInterface` implementation, register it in `AbstractFormatter::format()` and add the corresponding abstract method; every concrete formatter (`MarkdownFormatter`, `LlmFormatter`) is then forced to implement it at compile time
- For `--format=llm` output, wrap logical sections in XML-style delimiters (e.g., `<issue>...</issue>`, `<context>...</context>`) to aid LLM consumption

## Skills

- `/phpstan-fix` — Run PHPStan and fix all reported errors in `src/`
- `/pr-check` — Run the full local CI suite before opening a PR
