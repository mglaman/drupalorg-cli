---
name: drupalorg-issue-search
description: >
  Search for Drupal.org issues by keyword. Combines CLI API search, Drupal.org
  issue queue scraping, and web search, then deduplicates and presents a unified
  summary.
---

## Usage

```
/drupalorg-issue-search <query> [--project=<project>] [--status=all] [--skip=web_search,api_search,drupalorg_scrape]
```

## Instructions

1. **Parse inputs**: Extract the search `query` and optional flags:
   - `--project`: project machine name
   - `--status`: issue status filter (default: `all`)
   - `--skip`: comma-separated list of channels to skip. Valid values: `api_search`, `drupalorg_scrape`, `web_search`. For example `--skip=web_search` skips the web search, `--skip=api_search,web_search` runs only the Drupal.org scrape.

2. **Detect project**: If `--project` is not provided, try to infer the project machine name from the current git remote:
   ```bash
   git config --get remote.origin.url
   ```
   Extract the project name from the URL (pattern: `*/project-name.git`). If detection fails, proceed without a project filter.

3. **Run enabled searches in parallel** (skip any channel listed in `--skip`):

   a. **API search** (channel: `api_search`) — run the CLI command:
   ```bash
   php drupalorg issue:search <query> --status=<status> --format=json
   ```
   If a project is known, include it as the first argument:
   ```bash
   php drupalorg issue:search <project> <query> --status=<status> --format=json
   ```

   b. **Drupal.org issue queue scrape** (channel: `drupalorg_scrape`) — if a project is known, fetch the project's issue search page directly using `WebFetch`:
   ```
   URL: https://www.drupal.org/project/issues/<project>?text=<query words joined by +>&status=All
   Prompt: Extract all issue NIDs (numeric IDs from URLs like /node/XXXX or /issues/XXXX), titles, and statuses from this page. Return as a compact list.
   ```
   Replace spaces in the query with `+` for the URL parameter. This channel searches issue titles and bodies server-side, so it can find older and closed issues that the API search misses.
   If no project is known, skip this channel.

   c. **Web search** (channel: `web_search`) — search the web:
   - If project is known: `<query> site:https://www.drupal.org/project/issues/<project>`
   - If project is unknown: `<query> site:https://www.drupal.org/project/issues/`

4. **Extract NIDs**: Parse NIDs from all active sources:
   - API search: from the JSON response
   - Drupal.org scrape: from the extracted issue list
   - Web search: from URLs matching patterns `/issues/{nid}` or `/node/{nid}` where `{nid}` is a numeric ID

5. **Deduplicate**: Collect all unique NIDs from all sources.

6. **Enrich results without details**: For any NIDs found via web search (but NOT in the API or scrape results which already have titles), fetch details:
   ```bash
   drupalorg issue:show <nid> --format=llm
   ```

7. **Present results**: Output a combined summary table with columns:
   - NID
   - Title
   - Status
   - Link (`https://www.drupal.org/node/{nid}`)

   Group results by source if helpful (API results first, then scrape results, then web-only results).
