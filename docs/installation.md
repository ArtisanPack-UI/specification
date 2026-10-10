---
title: Installation
---

# Installation

This guide covers installing ArtisanPack UI Specification.

## Install via Composer

```bash
composer require artisanpack-ui/specification
```

The service provider and the `Specification` facade are auto-discovered.

## Run Migrations

```bash
php artisan migrate
```

This creates the following tables:

- `specification_topics`: one row per topic in the synced snapshot
- `specification_snapshots`: one row per distinct OKF archive that has been synced, including what that sync changed

To customize the migrations, publish them first:

```bash
php artisan vendor:publish --tag=specification-migrations
```

## Publish the Configuration (Optional)

```bash
php artisan vendor:publish --tag=specification-config
```

This publishes `config/artisanpack/specification.php`. The config is merged recursively under the `artisanpack.specification` key, so you only need to keep the keys you change. See [Configuration](Installation-Configuration).

## Run the First Sync

```bash
php artisan keystone:spec-sync
```

## Scheduling

The package registers a weekly sync (`0 3 * * 0`) with the Laravel scheduler. Make sure your server runs the scheduler:

```bash
* * * * * cd /path-to-your-project && php artisan schedule:run >> /dev/null 2>&1
```

Set `SPECIFICATION_SYNC_SCHEDULED=false` to turn off the scheduled sync.

## Next Steps

- [Requirements](Installation-Requirements)
- [Configuration](Installation-Configuration)
- [Quick Start Guide](Getting-Started)
