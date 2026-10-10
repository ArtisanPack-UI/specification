# ArtisanPack UI — Specification

Ingests [The Website Specification](https://specification.website)'s Open Knowledge Format (OKF) bundle into a local index and exposes the `SpecCheck` registry other ArtisanPack UI packages build checks on.

This package owns OKF ingestion, the local search index, the `SpecCheck` contract and registry, result aggregation, and CC BY 4.0 attribution. It never implements domain checks itself. Those live in their owner packages (`seo`, `security`, `accessibility`, `performance`, `privacy`, `cms-framework`, …). A single reference check for `foundations/doctype` ships in this package as the pattern to copy.

## Requirements

- PHP 8.3+
- Laravel 13
- `ext-pdo_sqlite` (with FTS5) and `ext-zlib`

## Installation

```bash
composer require artisanpack-ui/specification
php artisan migrate
php artisan keystone:spec-sync
```

Publish the config file (optional):

```bash
php artisan vendor:publish --tag=specification-config
```

The config is merged under the `artisanpack.specification` key. The package registers a weekly sync with the Laravel scheduler.

## Usage

```php
// Sync the OKF snapshot (idempotent and safe on cron).
specification()->sync();

// Search the local index and look up topics.
$hits  = specification()->search( 'content security policy' );
$topic = specification()->topic( 'foundations/doctype' );

// Render topic text. Attribution is always attached.
echo specification()->render( $topic )->toHtml();

// Run the registered checks and build a four-tier report.
use ArtisanPackUI\Specification\Checks\SpecContext;

$results = specification()->run( SpecContext::page( 'https://example.com', $html, $headers ) );
$report  = specification()->report( $results );
```

## Artisan Commands

| Command | Purpose |
|---------|---------|
| `keystone:spec-sync [--force] [--archive=]` | Sync the OKF bundle into the snapshot, topics table, and search index |
| `keystone:spec-diff [--since=]` | Show what the last sync changed and, with the MCP client enabled, what has changed upstream since |
| `keystone:spec-check [--category=] [--slug=*] [--url=]` | Run the registered checks against a URL. Fails if a Required check fails, an Avoid practice is present, or a check errors |

## Authoring Checks (for Owner Packages)

Implement `ArtisanPackUI\Specification\Contracts\SpecCheck` and register it from your service provider:

```php
use ArtisanPackUI\Specification\Checks\CheckRegistry;

public function boot(): void
{
    $this->callAfterResolving( CheckRegistry::class, function ( CheckRegistry $registry ): void {
        $registry->registerMany( [ XmlSitemapCheck::class ] );
    } );
}
```

See `src/Checks/Foundations/DoctypeCheck.php` and the [SpecCheck authoring guide](docs/advanced/authoring-checks.md).

### Checks in This Package

| Slug | Status | Scope | Autofix | Registered by default |
|------|--------|-------|---------|-----------------------|
| `foundations/doctype` | Required | Page | Yes, when the context provides an `html_writer` | No. Add `DoctypeCheck::class` to `artisanpack.specification.checks` |

## MCP Client

An optional client for the [specification.website MCP server](https://mcp.specification.website/mcp) (`list_topics`, `get_topic`, `get_changes`, `search`) checks how fresh your snapshot is. It is disabled by default, and the package then runs snapshot-only. Enable it with `SPECIFICATION_MCP_ENABLED=true`. See [MCP Client](docs/usage/mcp-client.md).

## Attribution

Specification content is © The Website Specification contributors and licensed under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/). This package's code is MIT licensed.

Anywhere you display topic text, you must credit the topic title and The Website Specification, link to the topic, and name and link the license. The package enforces this at the renderer boundary: render topic bodies through `specification()->render()`, never by echoing `body_md`. Marketing surfaces are blocked, and paid surfaces put the attribution first. See [Attribution Requirements](docs/advanced/attribution-requirements.md).

## Documentation

The full documentation is in [`docs/`](docs/home.md).

## Contributing

As an open-source project, this package is open to contributions from anyone. Please [read through the contributing guidelines](CONTRIBUTING.md) to learn more about how you can contribute to this project.
