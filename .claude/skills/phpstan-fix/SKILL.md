---
name: phpstan-fix
description: Run PHPStan and fix all reported errors in src/
---

Run `vendor/bin/phpstan analyse src --error-format=raw`, then fix every reported
error. Prefer adding proper type annotations and null checks over suppressor
comments (`@phpstan-ignore`). Do not change business logic — only satisfy the
type checker. Re-run until the output is clean.
