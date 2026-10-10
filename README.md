# ArtisanPack UI — Specification

Ingests [The Website Specification](https://specification.website)'s Open Knowledge Format (OKF) bundle into a local index and exposes the `SpecCheck` registry other ArtisanPack UI packages build checks on.

This package owns OKF ingestion, the local search index, the `SpecCheck` contract + registry, and result aggregation. It never implements domain checks itself — those live in their owner packages (`seo`, `security`, `accessibility`, `performance`, `privacy`, `cms-framework`, …).

> **Status:** scaffold. Features land in follow-up issues on the [v1.0 milestone](https://github.com/ArtisanPack-UI/specification/milestone/1).

## Requirements

- PHP 8.3+
- Laravel 13

## Installation

```bash
composer require artisanpack-ui/specification
```

Publish the config file (optional):

```bash
php artisan vendor:publish --tag=specification-config
```

The config is merged under the `artisanpack.specification` key.

## Attribution

Specification content is © The Website Specification contributors and licensed under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/). This package's code is MIT licensed.

## Contributing

As an open-source project, this package is open to contributions from anyone. Please [read through the contributing guidelines](CONTRIBUTING.md) to learn more about how you can contribute to this project.
