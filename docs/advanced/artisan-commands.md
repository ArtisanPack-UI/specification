---
title: Artisan Commands
---

# Artisan Commands

## keystone:spec-sync

Syncs the OKF bundle into the snapshot, topics table, and search index.

```bash
php artisan keystone:spec-sync
php artisan keystone:spec-sync --force
php artisan keystone:spec-sync --archive=/path/to/okf.tar.gz
```

| Option | Description |
|--------|-------------|
| `--force` | Re-apply the archive even if it matches the current snapshot |
| `--archive=` | Sync from a local `okf.tar.gz` instead of downloading it |

The command prints created, updated, deleted, and unchanged counts, and warns about any topic that failed to parse. If the archive matches the current snapshot it reports that it is already up to date and changes nothing. It is idempotent and safe on cron, and an overlapping run fails instead of running twice.

**Exit codes:** `0` on success (including "already up to date"); `1` when the download, archive, or lock fails.

## keystone:spec-diff

Shows what changed since the last sync.

```bash
php artisan keystone:spec-diff
php artisan keystone:spec-diff --since=2026-09-01
```

| Option | Description |
|--------|-------------|
| `--since=` | Ask the MCP server for changes since this date (`YYYY-MM-DD`) instead of the last sync date |

The output has two parts:

1. **Snapshot diff.** The slugs that the sync producing the current snapshot added, updated, and removed.
2. **Upstream changes.** Only when the [MCP client](Usage-Mcp-Client) is enabled: the `get_changes` entries since the last sync (or `--since`). These are changes not yet in your local snapshot. Run `keystone:spec-sync` to pull them.

Topics enforced by a registered check are marked `(checked)`, and the command summarises how many upstream changes affect registered checks.

**Exit codes:** `0` on success; `1` when the MCP request fails; `2` for an invalid `--since`.

## keystone:spec-check

Runs the registered checks against a URL.

```bash
php artisan keystone:spec-check
php artisan keystone:spec-check --category=foundations
php artisan keystone:spec-check --slug=foundations/doctype --slug=security/hsts
php artisan keystone:spec-check --url=https://staging.example.com --timeout=30
```

| Option | Description |
|--------|-------------|
| `--category=` | Only run checks in this category |
| `--slug=*` | Only run checks for these topic slugs (repeatable) |
| `--url=` | The URL to check. Defaults to `app.url` |
| `--timeout=15` | Seconds to wait when fetching the URL |

Site checks run against a Site context. When any Page or Server check is selected, the URL is fetched once: its HTML and headers feed the Page context, and its headers feed the Server context. Both carry the response status in `data.status`. Section and Block checks need editor context, so they are not run; the command says how many it left out.

The command prints a results table and a four-tier summary (Required, Recommended, and Optional met, plus Avoid present).

**Exit codes:** `0` when no blocking result was found; `1` when any **Required** check failed, any **Avoid** practice is present, any check **errored**, any registered check **could not be used** (it is listed, and the usable checks still run), or the URL could not be fetched; `2` for an invalid URL or category. Failed Recommended and Optional checks do not fail the command, so it can gate a CI pipeline.

## Output Safety

Topic titles, slugs, and error messages can come from the upstream archive or the MCP server. All three commands strip control characters and escape console style tags in that text before printing it, so upstream content cannot inject terminal escape sequences or hide output.
