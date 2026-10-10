<?php

/**
 * Specification service provider.
 *
 * Bootstraps the Specification package by registering services, bindings,
 * migrations, the scheduled sync, and publishable configuration.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @author     Jacob Martella <me@jacobmartella.com>
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification;

use ArtisanPackUI\Specification\Attribution\LicenseGuard;
use ArtisanPackUI\Specification\Attribution\SpecTopicRenderer;
use ArtisanPackUI\Specification\Checks\CheckRegistry;
use ArtisanPackUI\Specification\Checks\CheckRunner;
use ArtisanPackUI\Specification\Console\Commands\SpecCheckCommand;
use ArtisanPackUI\Specification\Console\Commands\SpecDiffCommand;
use ArtisanPackUI\Specification\Console\Commands\SpecSyncCommand;
use ArtisanPackUI\Specification\Contracts\McpClient;
use ArtisanPackUI\Specification\Contracts\SearchIndex;
use ArtisanPackUI\Specification\Mcp\HttpMcpClient;
use ArtisanPackUI\Specification\Okf\OkfBundleLoader;
use ArtisanPackUI\Specification\Okf\OkfDownloader;
use ArtisanPackUI\Specification\Okf\SnapshotStore;
use ArtisanPackUI\Specification\Okf\Synchronizer;
use ArtisanPackUI\Specification\Okf\TarGzReader;
use ArtisanPackUI\Specification\Okf\TopicParser;
use ArtisanPackUI\Specification\Search\SqliteFtsIndex;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Specification package.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */
class SpecificationServiceProvider extends ServiceProvider
{
    /**
     * Name of the scheduled sync event.
     *
     * @var string
     */
    public const SCHEDULE_NAME = 'artisanpack-specification-sync';

    /**
     * Registers any application services.
     *
     * Recursively merges the package configuration (so partial nested
     * overrides keep the remaining defaults) and binds the package services
     * as singletons.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function register(): void
    {
        $this->replaceConfigRecursivelyFrom(
            __DIR__ . '/../config/artisanpack/specification.php',
            'artisanpack.specification',
        );

        $this->app->singleton( CheckRegistry::class );
        $this->app->singleton( CheckRunner::class );

        $this->app->singleton( LicenseGuard::class, fn ( Application $app ): LicenseGuard => new LicenseGuard(
            (array) $this->config( 'license.blocked_surfaces', [] ),
            (array) $this->config( 'license.prominent_surfaces', [] ),
        ) );
        $this->app->singleton( SpecTopicRenderer::class );

        $this->app->singleton( SearchIndex::class, fn (): SearchIndex => new SqliteFtsIndex(
            $this->storagePath( (string) $this->config( 'index.path' ) ),
        ) );

        $this->app->singleton( TarGzReader::class, fn (): TarGzReader => new TarGzReader(
            (int) $this->config( 'okf.max_extracted_bytes' ),
        ) );
        $this->app->singleton( TopicParser::class );
        $this->app->singleton( OkfBundleLoader::class );

        $this->app->singleton( OkfDownloader::class, fn ( Application $app ): OkfDownloader => new OkfDownloader(
            $app->make( HttpFactory::class ),
            (string) $this->config( 'okf.url' ),
            (int) $this->config( 'okf.timeout' ),
            (int) $this->config( 'okf.max_archive_bytes' ),
        ) );

        $this->app->singleton( SnapshotStore::class, fn ( Application $app ): SnapshotStore => new SnapshotStore(
            $app->make( Filesystem::class ),
            $this->storagePath( (string) $this->config( 'okf.snapshot_path' ) ),
        ) );

        $this->app->singleton( Synchronizer::class );

        $this->app->singleton( McpClient::class, fn ( Application $app ): McpClient => new HttpMcpClient(
            $app->make( HttpFactory::class ),
            (bool) $this->config( 'mcp.enabled', false ),
            (string) $this->config( 'mcp.url' ),
            (int) $this->config( 'mcp.timeout', 10 ),
        ) );

        $this->app->singleton( 'specification', fn ( Application $app ): Specification => $app->make( Specification::class ) );
        $this->app->singleton( Specification::class );
    }

    /**
     * Bootstraps any application services.
     *
     * Loads migrations, registers configured checks, the scheduled sync, and
     * the Artisan commands, and registers publishable config and migrations.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function boot(): void
    {
        $this->loadMigrationsFrom( __DIR__ . '/../database/migrations' );

        $this->callAfterResolving( CheckRegistry::class, function ( CheckRegistry $registry ): void {
            $registry->registerMany( (array) $this->config( 'checks', [] ) );
        } );

        if ( true === (bool) $this->config( 'sync.scheduled', true ) ) {
            $this->callAfterResolving( Schedule::class, function ( Schedule $schedule ): void {
                $schedule->call( fn () => $this->app->make( Synchronizer::class )->sync() )
                    ->name( self::SCHEDULE_NAME )
                    ->cron( (string) $this->config( 'sync.cron', '0 3 * * 0' ) )
                    ->withoutOverlapping();
            } );
        }

        if ( $this->app->runningInConsole() ) {
            $this->commands( [
                SpecSyncCommand::class,
                SpecDiffCommand::class,
                SpecCheckCommand::class,
            ] );
        }

        $this->publishes( [
            __DIR__ . '/../config/artisanpack/specification.php' => config_path( 'artisanpack/specification.php' ),
        ], 'specification-config' );

        $this->publishes( [
            __DIR__ . '/../database/migrations' => database_path( 'migrations' ),
        ], 'specification-migrations' );
    }

    /**
     * Gets a package config value.
     *
     * @since 1.0.0
     *
     * @param  string  $key      The key below `artisanpack.specification`.
     * @param  mixed   $default  The default.
     *
     * @return mixed
     */
    private function config( string $key, mixed $default = null ): mixed
    {
        return $this->app->make( 'config' )->get( 'artisanpack.specification.' . $key, $default );
    }

    /**
     * Resolves a configured path against storage_path() unless it is absolute.
     *
     * @since 1.0.0
     *
     * @param  string  $path  The configured path.
     *
     * @return string
     */
    private function storagePath( string $path ): string
    {
        if ( ':memory:' === $path || str_starts_with( $path, '/' ) || 1 === preg_match( '#^[A-Za-z]:[\\\\/]#', $path ) ) {
            return $path;
        }

        return storage_path( $path );
    }
}
