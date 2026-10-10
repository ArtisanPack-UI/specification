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
    | the vendored snapshot is extracted on disk (relative to storage_path()).
    | The snapshot keeps checks functional offline.
    |
    */

    'okf' => [
        'url'           => env( 'SPECIFICATION_OKF_URL', 'https://specification.website/okf.tar.gz' ),
        'snapshot_path' => env( 'SPECIFICATION_SNAPSHOT_PATH', 'artisanpack/specification/snapshot' ),
    ],

    /*
    |--------------------------------------------------------------------------
    | MCP Client
    |--------------------------------------------------------------------------
    |
    | Optional secondary source for freshness. Disabled by default, in which
    | case the package runs snapshot-only.
    |
    */

    'mcp' => [
        'enabled' => (bool) env( 'SPECIFICATION_MCP_ENABLED', false ),
        'url'     => env( 'SPECIFICATION_MCP_URL', 'https://mcp.specification.website/mcp' ),
    ],

    /*
    |--------------------------------------------------------------------------
    | Experimental Agent Readiness Topics
    |--------------------------------------------------------------------------
    |
    | Emerging Agent Readiness topics (WebMCP, DNS-AID, ai-catalog, etc.) are
    | still moving targets upstream and stay off unless explicitly enabled.
    |
    */

    'agent_readiness_experimental' => (bool) env( 'SPECIFICATION_AGENT_READINESS_EXPERIMENTAL', false ),

];
