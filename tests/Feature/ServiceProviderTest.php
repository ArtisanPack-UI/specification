<?php

declare( strict_types=1 );

use ArtisanPackUI\Specification\Facades\Specification as SpecificationFacade;
use ArtisanPackUI\Specification\Specification;
use ArtisanPackUI\Specification\SpecificationServiceProvider;
use Illuminate\Support\ServiceProvider;

it( 'registers the specification service provider', function (): void {
    expect( app()->getProviders( SpecificationServiceProvider::class ) )->not->toBeEmpty();
} );

it( 'binds the specification class as a singleton', function (): void {
    $first  = app( 'specification' );
    $second = app( 'specification' );

    expect( $first )->toBeInstanceOf( Specification::class )
        ->and( $first )->toBe( $second );
} );

it( 'resolves the same instance through the helper and the facade', function (): void {
    expect( specification() )->toBe( app( 'specification' ) )
        ->and( SpecificationFacade::getFacadeRoot() )->toBe( app( 'specification' ) );
} );

it( 'merges the package config under the artisanpack.specification key', function (): void {
    expect( config( 'artisanpack.specification.okf.url' ) )->toBe( 'https://specification.website/okf.tar.gz' )
        ->and( config( 'artisanpack.specification.okf.snapshot_path' ) )->toBe( 'artisanpack/specification/snapshot' )
        ->and( config( 'artisanpack.specification.mcp.enabled' ) )->toBeFalse()
        ->and( config( 'artisanpack.specification.mcp.url' ) )->toBe( 'https://mcp.specification.website/mcp' )
        ->and( config( 'artisanpack.specification.agent_readiness_experimental' ) )->toBeFalse();
} );

it( 'keeps application overrides when merging the package config', function (): void {
    config()->set( 'artisanpack.specification.mcp.enabled', true );

    ( new SpecificationServiceProvider( app() ) )->register();

    expect( config( 'artisanpack.specification.mcp.enabled' ) )->toBeTrue();
} );

it( 'keeps nested defaults when an application overrides a single nested key', function (): void {
    config()->set( 'artisanpack.specification', [
        'okf' => [ 'snapshot_path' => 'custom/snapshot' ],
    ] );

    ( new SpecificationServiceProvider( app() ) )->register();

    expect( config( 'artisanpack.specification.okf.snapshot_path' ) )->toBe( 'custom/snapshot' )
        ->and( config( 'artisanpack.specification.okf.url' ) )->toBe( 'https://specification.website/okf.tar.gz' )
        ->and( config( 'artisanpack.specification.mcp.url' ) )->toBe( 'https://mcp.specification.website/mcp' );
} );

it( 'publishes the config file under the specification-config tag', function (): void {
    $paths = ServiceProvider::pathsToPublish( SpecificationServiceProvider::class, 'specification-config' );

    expect( $paths )->toHaveCount( 1 )
        ->and( array_key_first( $paths ) )->toEndWith( 'config/artisanpack/specification.php' )
        ->and( array_key_first( $paths ) )->toBeFile()
        ->and( reset( $paths ) )->toBe( config_path( 'artisanpack/specification.php' ) );
} );
