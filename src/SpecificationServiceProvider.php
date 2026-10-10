<?php

/**
 * Specification service provider.
 *
 * Bootstraps the Specification package by registering services, bindings,
 * and publishable configuration.
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

use Illuminate\Support\ServiceProvider;

/**
 * Service provider for the Specification package.
 *
 * Merges the package configuration under the `artisanpack.specification`
 * key, binds the Specification class as a singleton, and registers the
 * publishable config file.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */
class SpecificationServiceProvider extends ServiceProvider
{
    /**
     * Registers any application services.
     *
     * Recursively merges the package configuration (so partial nested
     * overrides keep the remaining defaults) and binds the Specification class
     * as a singleton in the container.
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

        $this->app->singleton( 'specification', function ( $app ) {
            return new Specification();
        } );
    }

    /**
     * Bootstraps any application services.
     *
     * Registers the package configuration for publishing under the
     * `specification-config` tag.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function boot(): void
    {
        $this->publishes( [
            __DIR__ . '/../config/artisanpack/specification.php' => config_path( 'artisanpack/specification.php' ),
        ], 'specification-config' );
    }
}
