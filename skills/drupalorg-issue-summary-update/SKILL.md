---
name: drupalorg-issue-summary-update
description: >
  Fetches a Drupal.org issue with all its comments and analyses whether the
  "Proposed resolution" in the issue summary matches the current discussion
  consensus. Drafts an updated summary for the user to paste back.
---

# /drupalorg-issue-summary-update

**Purpose:** Ensure a Drupal.org issue summary's "Proposed resolution" reflects
the latest discussion in the comments.

**Usage:** `/drupalorg-issue-summary-update <nid>`

---

## Instructions

### Step 1: Fetch issue with comments

```bash
drupalorg issue:show <nid> --with-comments --format=llm
```

Report to the user:
- Issue title, status, project
- The current "Proposed resolution" section (extracted from the body)
- A concise summary of what the comments discuss, highlighting the latest direction

> **Note:** Comments from the automated "System Message" user (bot posts about
> MRs being opened/closed) are automatically excluded from the output.

**[PAUSE]** Present your analysis:
- What the proposed resolution currently says
- Where comments agree, diverge, or add new direction
- Which parts of the summary may be out of date

Ask: "Would you like me to draft an updated issue summary?"

---

### Step 2: Draft updated summary

If the user agrees, draft an updated issue summary that:
- Preserves the standard Drupal.org section headings:
  - Problem/Motivation
  - Proposed resolution
  - Remaining tasks
  - User interface changes (if applicable)
  - API changes (if applicable)
  - Data model changes (if applicable)
- Updates "Proposed resolution" to reflect the discussion consensus
- Updates "Remaining tasks" to match what is still outstanding
- Keeps "Problem/Motivation" unchanged unless comments clarify the problem itself

Present the full updated summary text to the user.

**[PAUSE]** Ask: "Does this look correct? Should I adjust anything before you
paste it into the issue?"

---

### Step 3: Guide the user to apply the update

Once the summary is approved, instruct the user:

1. Open the issue: `drupalorg issue:link <nid>`
2. Click "Edit" on the issue node
3. Replace the "Summary" (body) field with the updated text
4. Save the issue

Note: drupalorg-cli is read-only and cannot write to Drupal.org directly.

---

## Notes

- Always use `--with-comments` on `issue:show` to capture the full context.
- Focus on the *latest* comments — earlier comments may reflect resolved debates.
- If `comment_count` is high (>30), note this and ask the user which comment
  range is most relevant before fetching (to avoid noise).
