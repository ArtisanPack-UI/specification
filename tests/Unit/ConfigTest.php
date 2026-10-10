<?php

declare( strict_types=1 );

it( 'returns the expected top-level config keys', function (): void {
    $config = require __DIR__ . '/../../config/artisanpack/specification.php';

    expect( $config )->toBeArray()
        ->toHaveKeys( [ 'okf', 'mcp', 'agent_readiness_experimental' ] )
        ->and( $config['okf'] )->toHaveKeys( [ 'url', 'snapshot_path' ] )
        ->and( $config['mcp'] )->toHaveKeys( [ 'enabled', 'url' ] );
} );
