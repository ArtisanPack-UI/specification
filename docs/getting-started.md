---
title: Quick Start Guide
---

# Quick Start Guide

Get the specification synced and your first check running in a few minutes.

## Step 1: Install the Package

```bash
composer require artisanpack-ui/specification
```

## Step 2: Run Migrations

```bash
php artisan migrate
```

This creates the `specification_topics` and `specification_snapshots` tables.

## Step 3: Sync the Specification

```bash
php artisan keystone:spec-sync
```

The command downloads the OKF bundle, writes the snapshot to `storage/artisanpack/specification/snapshot`, upserts every topic, and builds the search index. Running it again with an unchanged archive does nothing, so it is safe on cron. The package also schedules it weekly. See [Syncing the Specification](Usage-Syncing).

## Step 4: Look Up a Topic

```php
$topic = specification()->topic( 'foundations/doctype' );

echo $topic->title;           // "The HTML doctype"
echo $topic->status->label(); // "Required"

// Topic text is CC BY 4.0. Render it through the renderer so attribution is always attached.
echo specification()->render( $topic )->toHtml();
```

## Step 5: Run a Check

Enable the in-package reference check in `config/artisanpack/specification.php`:

```php
'checks' => [
    \ArtisanPackUI\Specification\Checks\Foundations\DoctypeCheck::class,
],
```

Then run it against your site:

```bash
php artisan keystone:spec-check --category=foundations --url=https://example.com
```

## Next Steps

- [Running Checks](Usage-Running-Checks): run checks from PHP and read the results.
- [Authoring SpecChecks](Advanced-Authoring-Checks): write and register checks in an owner package.
- [Attribution Requirements](Advanced-Attribution-Requirements): what CC BY 4.0 requires when you display topic text.
