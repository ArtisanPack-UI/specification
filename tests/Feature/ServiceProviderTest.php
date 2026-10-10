<?php

declare( strict_types=1 );

use ArtisanPackUI\Specification\Facades\Specification as SpecificationFacade;
use ArtisanPackUI\Specification\Specification;
use ArtisanPackUI\Specification\SpecificationServiceProvider;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Schema;
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
        ->and( config( 'artisanpack.specification.okf.max_archive_bytes' ) )->toBe( 10 * 1024 * 1024 )
        ->and( config( 'artisanpack.specification.sync.cron' ) )->toBe( '0 3 * * 0' )
        ->and( config( 'artisanpack.specification.license.blocked_surfaces' ) )->toBe( [ 'marketing' ] )
        ->and( config( 'artisanpack.specification.checks' ) )->toBe( [] )
        ->and( config( 'artisanpack.specification.mcp.enabled' ) )->toBeFalse()
        ->and( config( 'artisanpack.specification.mcp.url' ) )->toBe( 'https://mcp.specification.website/mcp' )
        ->and( config( 'artisanpack.specification.mcp.timeout' ) )->toBe( 10 )
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

it( 'publishes the migrations under the specification-migrations tag', function (): void {
    $paths = ServiceProvider::pathsToPublish( SpecificationServiceProvider::class, 'specification-migrations' );

    expect( $paths )->toHaveCount( 1 )
        ->and( array_key_first( $paths ) )->toBeDirectory()
        ->and( reset( $paths ) )->toBe( database_path( 'migrations' ) );
} );

it( 'registers the weekly sync with the scheduler', function (): void {
    $events = collect( app( Schedule::class )->events() )
        ->filter( fn ( $event ): bool => SpecificationServiceProvider::SCHEDULE_NAME === $event->description );

    expect( $events )->toHaveCount( 1 )
        ->and( $events->first()->expression )->toBe( '0 3 * * 0' );
} );

it( 'creates the topics and snapshots tables', function (): void {
    expect( Schema::hasTable( 'specification_topics' ) )->toBeTrue()
        ->and( Schema::hasTable( 'specification_snapshots' ) )->toBeTrue()
        ->and( Schema::hasColumn( 'specification_snapshots', 'changes' ) )->toBeTrue();
} );

it( 'registers the keystone artisan commands', function (): void {
    expect( array_keys( Illuminate\Support\Facades\Artisan::all() ) )->toContain( 'keystone:spec-sync', 'keystone:spec-diff', 'keystone:spec-check' );
} );

it( 'publishes the config file under the specification-config tag', function (): void {
    $paths = ServiceProvider::pathsToPublish( SpecificationServiceProvider::class, 'specification-config' );

    expect( $paths )->toHaveCount( 1 )
        ->and( array_key_first( $paths ) )->toEndWith( 'config/artisanpack/specification.php' )
        ->and( array_key_first( $paths ) )->toBeFile()
        ->and( reset( $paths ) )->toBe( config_path( 'artisanpack/specification.php' ) );
} );
