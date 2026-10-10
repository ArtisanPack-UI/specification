---
title: Syncing the Specification
---

# Syncing the Specification

A sync downloads the OKF bundle, validates it, writes the snapshot to disk, upserts topics into `specification_topics`, records the snapshot in `specification_snapshots`, and rebuilds the search index.

## Running a Sync

```bash
php artisan keystone:spec-sync
php artisan keystone:spec-sync --force
php artisan keystone:spec-sync --archive=/path/to/okf.tar.gz
```

Or from PHP:

```php
$result = specification()->sync();
$result = specification()->sync( force: true );
$result = specification()->sync( archivePath: storage_path( 'okf.tar.gz' ) );
```

## The Sync Result

`sync()` returns a `SyncResult`:

| Property | Description |
|----------|-------------|
| `version` | SHA-256 of the archive bytes, which is also the snapshot version |
| `changed` | `false` when the archive matched the current snapshot and nothing was applied |
| `created` | Slugs added |
| `updated` | Slugs whose content changed |
| `deleted` | Slugs no longer in the bundle |
| `unchanged` | Count of topics whose content did not change |
| `errors` | Topics that failed to parse, keyed by slug |

## Idempotency

Sync is safe to run repeatedly:

- An archive identical to the current snapshot is a no-op. The on-disk snapshot and the search index are still rewritten if they are stale, for example after an earlier sync failed partway.
- Topic rows are upserted by slug and only written when their content changes. Re-running never duplicates rows or touches `updated_at` on unchanged topics.
- A topic that fails to parse is reported in `errors` and its previously synced row is kept rather than deleted. A slug or title longer than 255 characters counts as a parse failure. An unknown `conformance` keyword is stored as `null`.
- An invalid archive throws before anything is written, so the existing snapshot, rows, and index stay intact.
- A cache lock stops scheduled and on-demand syncs from overlapping. A second sync while one is running throws `SyncFailedException`.

## What Changed

Each snapshot row records which slugs its sync created, updated, and deleted:

```php
use ArtisanPackUI\Specification\Models\Snapshot;

$changes = Snapshot::current()?->changes;
// [ 'created' => [...], 'updated' => [...], 'deleted' => [...] ]
```

`keystone:spec-diff` prints this record. A forced re-apply that changes nothing keeps the record from when the snapshot first landed.

## On-disk Layout

The snapshot directory (`storage/artisanpack/specification/snapshot` by default) contains:

- `okf.tar.gz`: the archive that was synced
- `manifest.json`: `version`, `okf_version`, `topic_count`, `written_at`
- `okf/**.md`: the validated Markdown files

The snapshot is written to a temporary sibling directory and swapped into place, so readers never see a half-written snapshot.

## Failures

The database is updated first, in a single transaction. The on-disk snapshot and the search index are written after it.

`sync()` throws `SyncFailedException` when the archive cannot be downloaded (including a non-`https` URL or an oversized body), a local archive is missing, another sync holds the lock, or the snapshot cannot be applied (for example a database error, which is wrapped with the original as `previous`). It throws `InvalidArchiveException` when the archive is malformed or contains no valid topics. Both extend `SpecificationException`.
