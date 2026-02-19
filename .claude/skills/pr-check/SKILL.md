---
name: pr-check
description: Run the full local CI suite before opening a PR
disable-model-invocation: true
---

Run the following checks in order, stopping and reporting on the first failure:

1. `composer validate`
2. `vendor/bin/phpcs src`
3. `vendor/bin/phpstan analyse src`
4. `vendor/bin/phpunit`

Report a summary of all steps (pass/fail). If everything passes, confirm it is
safe to push and open a PR.
