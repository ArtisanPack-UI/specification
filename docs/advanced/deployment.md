---
title: Deployment & Operations
---

# Deployment & Operations

How the package behaves in production: what lives where, what the scheduler does, and how it copes with multiple servers, ephemeral filesystems, and offline environments.

## What Lives Where

| Data | Location | Shared between servers? |
|------|----------|-------------------------|
| Topic rows (`specification_topics`) | Your default database connection | Yes |
| Snapshot rows (`specification_snapshots`) | Your default database connection | Yes |
| Search index | SQLite file at `artisanpack.specification.index.path` (default `storage/artisanpack/specification/index.sqlite`) | No, one per server |
| Vendored snapshot | Directory at `artisanpack.specification.okf.snapshot_path` (default `storage/artisanpack/specification/snapshot`) | No, one per server |

The database is the source of truth. A sync updates it first, in a single transaction, and then writes the on-disk snapshot and rebuilds the index. Nothing reads the on-disk snapshot at request time. It is an offline copy of the archive that was applied.

## The Scheduled Sync

The service provider registers a sync with the Laravel scheduler, named `artisanpack-specification-sync` and running `withoutOverlapping()`. It runs weekly by default (`0 3 * * 0`). Change the schedule with `SPECIFICATION_SYNC_CRON`, or turn it off with `SPECIFICATION_SYNC_SCHEDULED=false` and run `keystone:spec-sync` yourself.

Syncs are idempotent. An unchanged archive is a no-op, apart from repairing a stale on-disk snapshot or index. A cache lock (`artisanpack-specification-sync`) also refuses a second sync while one is running. Locks only coordinate processes that share a cache store, so use a shared store such as Redis, Memcached, or the database store when more than one server can run the scheduler.

## Multiple Servers and Ephemeral Filesystems

The scheduled sync runs on one server, but the search index is a file on each server. `search()` handles this itself:

1. It compares the local index's version with the current snapshot version from the database.
2. If they differ (another server ran the sync, the file is new after a deploy, or the disk was wiped), it rebuilds the index from the topic rows before searching.
3. It holds the `artisanpack-specification-index-rebuild` cache lock while rebuilding. A request that finds another rebuild in progress waits up to 10 seconds (`Specification::INDEX_LOCK_WAIT_SECONDS`) and then re-checks. If the wait runs out it throws `SearchIndexUnavailableException` rather than searching an index it hasn't confirmed is current. This is transient, so retry the search.

Rebuilding the full specification takes milliseconds, so the first search on a fresh server is barely slower than the rest.

Keep the index on each server's **local disk**. It uses SQLite's WAL mode, so searches keep reading while a rebuild writes, and WAL isn't safe on network filesystems such as NFS or EFS. Don't point `SPECIFICATION_INDEX_PATH` at shared storage.

## Offline and Air-gapped Environments

The scheduled sync downloads `https://specification.website/okf.tar.gz`. Where the server can't reach it, download the archive elsewhere and sync from the file:

```bash
php artisan keystone:spec-sync --archive=/path/to/okf.tar.gz
```

Set `SPECIFICATION_SYNC_SCHEDULED=false` so the scheduler doesn't try to download. Checks, search, and rendering then work entirely from the local data. The MCP client is off by default and never needed.

## Upstream Limits and Safeguards

- Downloads must use `https`, follow at most three `https` redirects, and are aborted mid-transfer once they exceed `okf.max_archive_bytes` (10 MB by default).
- The archive is decompressed in memory up to `okf.max_extracted_bytes` (50 MB by default). Only Markdown files whose paths match a strict allow-list are kept.
- A topic that fails validation (for example a slug or title longer than 255 characters) is reported in the sync result's `errors`, and its previously synced row is kept.

## Monitoring

- `keystone:spec-sync` exits with `1` when the download, archive, lock, or apply step fails, so a failed scheduled run shows up in your scheduler's failure handling.
- `keystone:spec-diff` shows what the last sync changed and, with the MCP client enabled, what has changed upstream since.
- `keystone:spec-check` can gate a CI pipeline. It exits with `1` when a Required check fails, an Avoid practice is present, a check errors, or a registered check could not be used.

See [Artisan Commands](Advanced-Artisan-Commands) and [Troubleshooting](Troubleshooting).
