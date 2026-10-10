---
title: Troubleshooting
---

# Troubleshooting

## Syncing

### "The OKF URL must use https"

`artisanpack.specification.okf.url` (or `SPECIFICATION_OKF_URL`) must be an `https://` URL. To sync from a local file, use `php artisan keystone:spec-sync --archive=/path/to/okf.tar.gz`.

### "The OKF archive exceeds the … byte limit"

The download was larger than `okf.max_archive_bytes` (10 MB by default). Raise the limit only if the upstream bundle has genuinely grown.

### "A specification sync is already running"

Another sync holds the cache lock. Wait for it to finish. If a crashed process left the lock behind, it expires after 10 minutes, or you can clear it:

```php
cache()->lock( \ArtisanPackUI\Specification\Okf\Synchronizer::LOCK_NAME )->forceRelease();
```

### "The OKF archive contains no valid topics"

The archive downloaded but none of its topics parsed. The existing snapshot and rows are left untouched. Check the archive URL, and run `php artisan keystone:spec-sync --archive=` against a known good archive.

## Search

### SearchIndexUnavailableException

The SQLite index file could not be opened, your SQLite build lacks FTS5, or (with the message "being rebuilt by another process") another request held the index rebuild lock for longer than 10 seconds. That last case is transient, so retry the search. Check that `ext-pdo_sqlite` is installed, that the directory for `artisanpack.specification.index.path` is writable, and that `SELECT sqlite_compileoption_used('ENABLE_FTS5')` returns `1`.

### Search returns nothing after a sync

`search()` rebuilds a stale or empty index from the topic rows before it searches, so this usually fixes itself on the next search. If it doesn't, check that the database has topics (`Topic::query()->count()`) and run `php artisan keystone:spec-sync`. Re-running it rebuilds a stale index even when the archive hasn't changed.

### Search fails with "being rebuilt by another process"

Another request held the index rebuild lock for more than 10 seconds. It's transient, so retry the search. See [Deployment & Operations](Advanced-Deployment#multiple-servers-and-ephemeral-filesystems).

### Search is empty on some servers only

The index is a file on each server. `search()` rebuilds a stale one from the database, so this fixes itself unless the index path is on a network filesystem. Keep it on local disk. See [Deployment & Operations](Advanced-Deployment#multiple-servers-and-ephemeral-filesystems).

## Checks

### "Check [...] has an invalid slug"

Slugs must look like `category/topic`, made of lower-case letters, digits, and single hyphens. A check with an invalid slug, or one the container can't build, is reported to your exception handler and set aside, and the other checks keep running. `specification()->checks()->invalid()` lists set-aside checks, and `keystone:spec-check` prints them and exits with `1`.

### A check always shows as "errored"

The check either threw an exception (the message includes it, and it is reported to your exception handler) or returned a result whose slug or status doesn't match what the check declares. Build results with `SpecResult::passed( $this, $context )` and the other factories so they always match.

### keystone:spec-check reports "No specification checks are registered"

No owner package has registered checks yet, or your `--category`/`--slug` filter matched none. To try the reference check, add `DoctypeCheck::class` to `artisanpack.specification.checks`.

### keystone:spec-check fails even though most checks pass

The command exits with `1` when any Required check fails, any Avoid practice is present, any check errors, or a registered check could not be used. Failed Recommended and Optional checks don't fail it.

## MCP

### "The specification.website MCP client is disabled"

Set `SPECIFICATION_MCP_ENABLED=true`. In code, check `specification()->mcp()->isEnabled()` before calling the client.

### keystone:spec-diff fails with an MCP error

The snapshot diff is still printed. Then check network access to `https://mcp.specification.website/mcp`, and increase `SPECIFICATION_MCP_TIMEOUT` if requests time out.
