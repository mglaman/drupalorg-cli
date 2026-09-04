# Drupal.org AI Contribution Policy — Agent Checklist

Drupal.org has a policy on the use of AI when contributing:
https://www.drupal.org/docs/develop/issues/issue-procedures-and-etiquette/policy-on-the-use-of-ai-when-contributing-to-drupal

The policy binds the human contributor, not the tool. When an agent drives
`drupalorg`, the user is the contributor and answers for every line pushed and
every word posted. Violations lead to temporary or permanent Drupal.org account
bans. Follow this checklist in every workflow that produces code or text for
Drupal.org.

---

## Before writing anything: read the thread

Dumping code into an issue without reading the discussion is the first pattern
the policy names as unacceptable.

```bash
drupalorg issue:show <nid> --with-comments --format=llm
drupalorg mr:list <nid> --state=all --format=llm
```

`--with-comments` only returns comments for classic Drupal.org issues. For GitLab
work items, read the discussion with `glab` or ask the user to summarize it:

```bash
GITLAB_HOST=git.drupalcode.org glab issue view <nid> --comments --repo project/<name>
```

From that output, report to the user before proposing a change:

- Previous attempts (patches, MRs, closed MRs) and why they stalled.
- Architectural conclusions reached in the comments. Do not reopen them.
- Who authored the existing MR. The `author` field in `mr:list` output tells you.

Do not propose a rewrite of a module or a change of direction based on your own
review. That requires the user to engage the maintainers in the issue first.

---

## While writing: keep the user able to explain every change

"The AI wrote it" is grounds for closing the contribution. The user must be able
to answer a reviewer's question about any line. Work so that stays true:

- **Minimal diff.** Change what the issue asks for. No drive-by refactors,
  renames, formatting sweeps, or "improvements" outside scope.
- **No unverified dependencies.** Do not add a Composer package, npm package, or
  core service unless you confirmed it exists and the user agreed to the
  dependency. Hallucinated packages are a supply-chain risk.
- **Security and licensing.** Flag anything that touches input handling, access
  checks, or output escaping so the user reviews it deliberately. Flag code you
  suspect reproduces another project verbatim. Everything must be GPL-compatible.
- **Explain as you go.** When presenting a change, state what it does and why in
  terms the user can repeat in the issue. If you cannot explain it, do not push it.

---

## Before pushing or uploading: verify

The policy names "posting an MR where automated checks fail and leaving it for
others to fix" as unacceptable. Verification is the user's job, so do it before
the user is asked to stand behind the work.

- Run the project's coding standards and tests locally when available
  (`vendor/bin/phpcs`, `vendor/bin/phpunit`, `vendor/bin/phpstan`).
- Push only when local checks pass. After pushing, poll the pipeline and fix
  failures before handing off:
  ```bash
  drupalorg mr:status <nid> <mr-iid> --format=llm
  drupalorg mr:logs <nid> <mr-iid>
  ```
- Never push to an MR the user did not author without the author's knowledge.
  If `mr:list` shows a different author, stop and ask the user to confirm they
  coordinated in the issue. The push must be disclosed in a comment.

---

## When handing off: disclose

Disclosure is mandatory whenever AI generated a significant portion of the
submission: entire functions, classes, architectural scaffolding, or extensive
documentation blocks. Reviewing the output thoroughly does not remove the
obligation. Single-line autocomplete and syntax fixes do not need disclosure.

Draft the disclosure line for the user and tell them where it goes:

1. If the project's issue or MR template has an AI disclosure section, use it.
2. Otherwise append a short, human-written statement to the end of the MR
   description, issue summary, or comment:

```
AI-Generated: Yes (Claude Code drafted the FooService::bar() implementation and its tests; I reviewed and ran them locally).
```

Name the tool and what it produced. Keep it to one sentence. `drupalorg` cannot
post comments or edit MR descriptions on Drupal.org, so hand the text to the user
and confirm they added it before marking the issue "Needs review".

---

## Issue summaries, comments, and reviews: the user's own words

Verbose AI prose is a burden on maintainers. Anything the user posts must be:

- **Written in their own words.** Give the user a draft to edit, never text to
  paste unchanged. Say so explicitly when you hand it over.
- **Concise.** Cut background the thread already contains. Lead with the
  decision or question.
- **Independently verified.** An AI summary of a thread posted to gain issue
  credit is a policy violation. Only propose summary updates that add technical
  insight the user checked.
- **Attached when long.** When the full generated output is genuinely useful,
  attach it as a file instead of pasting it into a comment.

---

## After handing off: stay responsive

A drive-by contribution that ignores follow-up feedback is grounds for a ban,
with or without AI. When you finish a work loop, remind the user that reviewers
may ask questions they must answer themselves, and offer to re-fetch the issue
later:

```bash
drupalorg issue:show <nid> --with-comments --format=llm --no-cache
```
