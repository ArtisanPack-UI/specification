---
title: Configuration
---

# Configuration

The configuration lives at `config/artisanpack/specification.php` once published, and is read from the `artisanpack.specification` key. Defaults are merged recursively, so a published file only needs the keys you override.

## OKF Source

```php
'okf' => [
    'url'                 => env( 'SPECIFICATION_OKF_URL', 'https://specification.website/okf.tar.gz' ),
    'snapshot_path'       => env( 'SPECIFICATION_SNAPSHOT_PATH', 'artisanpack/specification/snapshot' ),
    'timeout'             => (int) env( 'SPECIFICATION_OKF_TIMEOUT', 30 ),
    'max_archive_bytes'   => 10 * 1024 * 1024,
    'max_extracted_bytes' => 50 * 1024 * 1024,
],
```

| Key | Description |
|-----|-------------|
| `url` | The OKF archive URL. Must use `https`. |
| `snapshot_path` | Where the vendored snapshot is written. Relative paths resolve against `storage_path()`. |
| `timeout` | Download timeout in seconds. |
| `max_archive_bytes` | Downloads larger than this are aborted mid-transfer. |
| `max_extracted_bytes` | Guards against decompression bombs. |

## Scheduled Sync

```php
'sync' => [
    'scheduled' => (bool) env( 'SPECIFICATION_SYNC_SCHEDULED', true ),
    'cron'      => env( 'SPECIFICATION_SYNC_CRON', '0 3 * * 0' ),
],
```

The scheduled event is named `artisanpack-specification-sync` and runs `withoutOverlapping()`.

## Search Index

```php
'index' => [
    'path' => env( 'SPECIFICATION_INDEX_PATH', 'artisanpack/specification/index.sqlite' ),
],
```

The SQLite FTS5 index file. Relative paths resolve against `storage_path()`. `:memory:` is accepted, which is useful in tests.

## SpecCheck Registrations

```php
'checks' => [
    // \ArtisanPackUI\Specification\Checks\Foundations\DoctypeCheck::class,
],
```

Application-level checks. Owner packages normally register their checks from their own service providers instead; see [Authoring SpecChecks](Advanced-Authoring-Checks). The reference `DoctypeCheck` is opt-in.

## License Guard

```php
'license' => [
    'blocked_surfaces'   => [ 'marketing' ],
    'prominent_surfaces' => [ 'paid' ],
],
```

Values are `RenderSurface` enum values (`admin`, `editor`, `agent`, `public`, `paid`, `marketing`). Topic text can never be rendered on a blocked surface. On prominent surfaces the attribution is placed before the body. See [Attribution Requirements](Advanced-Attribution-Requirements).

## MCP Client

```php
'mcp' => [
    'enabled' => (bool) env( 'SPECIFICATION_MCP_ENABLED', false ),
    'url'     => env( 'SPECIFICATION_MCP_URL', 'https://mcp.specification.website/mcp' ),
    'timeout' => (int) env( 'SPECIFICATION_MCP_TIMEOUT', 10 ),
],
```

The MCP client is disabled by default, and the package then runs snapshot-only. Set `SPECIFICATION_MCP_ENABLED=true` to let `keystone:spec-diff` ask the upstream server what changed since your last sync. See [MCP Client](Usage-Mcp-Client).

## Experimental Agent Readiness Topics

```php
'agent_readiness_experimental' => (bool) env( 'SPECIFICATION_AGENT_READINESS_EXPERIMENTAL', false ),
```

Upstream Agent Readiness topics such as WebMCP, DNS-AID, and `ai-catalog` are still changing. Owner packages should keep their checks for these topics off unless this flag is on, and should check it through `specification()->agentReadinessExperimentalEnabled()` rather than reading the config directly:

```php
if ( specification()->agentReadinessExperimentalEnabled() ) {
    $registry->register( WebMcpCheck::class );
}
```

## Environment Variables

| Variable | Default |
|----------|---------|
| `SPECIFICATION_OKF_URL` | `https://specification.website/okf.tar.gz` |
| `SPECIFICATION_SNAPSHOT_PATH` | `artisanpack/specification/snapshot` |
| `SPECIFICATION_OKF_TIMEOUT` | `30` |
| `SPECIFICATION_SYNC_SCHEDULED` | `true` |
| `SPECIFICATION_SYNC_CRON` | `0 3 * * 0` |
| `SPECIFICATION_INDEX_PATH` | `artisanpack/specification/index.sqlite` |
| `SPECIFICATION_MCP_ENABLED` | `false` |
| `SPECIFICATION_MCP_URL` | `https://mcp.specification.website/mcp` |
| `SPECIFICATION_MCP_TIMEOUT` | `10` |
| `SPECIFICATION_AGENT_READINESS_EXPERIMENTAL` | `false` |
