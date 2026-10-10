<?php

declare( strict_types=1 );

it( 'returns the expected top-level config keys', function (): void {
    $config = require __DIR__ . '/../../config/artisanpack/specification.php';

    expect( $config )->toBeArray()
        ->toHaveKeys( [ 'okf', 'sync', 'index', 'checks', 'license', 'render', 'mcp', 'agent_readiness_experimental' ] )
        ->and( $config['render']['heading_offset'] )->toBe( 1 )
        ->and( $config['okf'] )->toHaveKeys( [ 'url', 'snapshot_path', 'timeout', 'max_archive_bytes', 'max_extracted_bytes' ] )
        ->and( $config['okf']['snapshot_path'] )->toBe( 'artisanpack/specification/snapshot' )
        ->and( $config['index']['path'] )->toBe( 'artisanpack/specification/index.sqlite' )
        ->and( $config['mcp'] )->toHaveKeys( [ 'enabled', 'url', 'timeout' ] )
        ->and( $config['mcp']['enabled'] )->toBeFalse();
} );
