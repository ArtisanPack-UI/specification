<?php

/**
 * Specification package configuration.
 *
 * Merged under the `artisanpack.specification` key. Publish with
 * `php artisan vendor:publish --tag=specification-config`.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

return [

    /*
    |--------------------------------------------------------------------------
    | Open Knowledge Format (OKF) Source
    |--------------------------------------------------------------------------
    |
    | The upstream OKF bundle published by specification.website, and where
    | the vendored snapshot is extracted on disk (relative to storage_path()
    | unless absolute). The snapshot keeps checks functional offline.
    |
    | The size limits guard against oversized or decompression-bomb archives.
    |
    */

    'okf' => [
        'url'                 => env( 'SPECIFICATION_OKF_URL', 'https://specification.website/okf.tar.gz' ),
        'snapshot_path'       => env( 'SPECIFICATION_SNAPSHOT_PATH', 'artisanpack/specification/snapshot' ),
        'timeout'             => (int) env( 'SPECIFICATION_OKF_TIMEOUT', 30 ),
        'max_archive_bytes'   => 10 * 1024 * 1024,
        'max_extracted_bytes' => 50 * 1024 * 1024,
    ],

    /*
    |--------------------------------------------------------------------------
    | Scheduled Sync
    |--------------------------------------------------------------------------
    |
    | When enabled, the package registers a weekly sync with the Laravel
    | scheduler. On-demand syncs are always available via specification()->sync().
    |
    */

    'sync' => [
        'scheduled' => (bool) env( 'SPECIFICATION_SYNC_SCHEDULED', true ),
        'cron'      => env( 'SPECIFICATION_SYNC_CRON', '0 3 * * 0' ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Local Search Index
    |--------------------------------------------------------------------------
    |
    | The embedded SQLite FTS5 index file (relative to storage_path() unless
    | absolute). It is independent of the application's database connection.
    |
    */

    'index' => [
        'path' => env( 'SPECIFICATION_INDEX_PATH', 'artisanpack/specification/index.sqlite' ),
    ],

    /*
    |--------------------------------------------------------------------------
    | SpecCheck Registrations
    |--------------------------------------------------------------------------
    |
    | Application-level SpecCheck classes. Packages register their checks from
    | their own service providers via the CheckRegistry, or by tagging them
    | with `artisanpack.specification.checks`.
    |
    | The in-package reference check is opt-in; enable it with
    | \ArtisanPackUI\Specification\Checks\Foundations\DoctypeCheck::class.
    |
    */

    'checks' => [],

    /*
    |--------------------------------------------------------------------------
    | License Guard (CC BY 4.0)
    |--------------------------------------------------------------------------
    |
    | Topic text is CC BY 4.0. It may never be rendered on a blocked surface,
    | and on prominent surfaces the attribution is placed before the body
    | rather than after it. Values are RenderSurface enum values.
    |
    */

    'license' => [
        'blocked_surfaces'   => [ 'marketing' ],
        'prominent_surfaces' => [ 'paid' ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Topic Rendering
    |--------------------------------------------------------------------------
    |
    | Every topic body starts with a `#` title. In HTML output its headings
    | are demoted by this many levels (0–5) so a rendered topic does not add
    | another <h1> to your page. Set it to 0 to keep the original levels.
    |
    */

    'render' => [
        'heading_offset' => (int) env( 'SPECIFICATION_RENDER_HEADING_OFFSET', 1 ),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Client
    |--------------------------------------------------------------------------
    |
    | Optional secondary source for freshness (`keystone:spec-diff` uses its
    | `get_changes` tool). Disabled by default, in which case the package runs
    | snapshot-only and the OKF snapshot stays authoritative.
    |
    */

    'mcp' => [
        'enabled' => (bool) env( 'SPECIFICATION_MCP_ENABLED', false ),
        'url'     => env( 'SPECIFICATION_MCP_URL', 'https://mcp.specification.website/mcp' ),
        'timeout' => (int) env( 'SPECIFICATION_MCP_TIMEOUT', 10 ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Experimental Agent Readiness Topics
    |--------------------------------------------------------------------------
    |
    | Emerging Agent Readiness topics (WebMCP, DNS-AID, ai-catalog, etc.) are
    | still moving targets upstream and stay off unless explicitly enabled.
    | Owner packages read this through
    | specification()->agentReadinessExperimentalEnabled().
    |
    */

    'agent_readiness_experimental' => (bool) env( 'SPECIFICATION_AGENT_READINESS_EXPERIMENTAL', false ),

];
