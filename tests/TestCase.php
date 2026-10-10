<?php

declare( strict_types=1 );

namespace Tests;

use ArtisanPackUI\Specification\SpecificationServiceProvider;
use Illuminate\Filesystem\Filesystem;
use Orchestra\Testbench\TestCase as BaseTestCase;

/**
 * Base Test Case
 *
 * Provides base functionality for all package tests.
 *
 * @since   1.0.0
 */
abstract class TestCase extends BaseTestCase
{
    /**
     * Per-test directory for the vendored snapshot.
     */
    protected string $specificationStorage = '';

    /**
     * Setup the test environment.
     */
    protected function setUp(): void
    {
        parent::setUp();
    }

    /**
     * Clean up the test environment.
     */
    protected function tearDown(): void
    {
        if ( '' !== $this->specificationStorage ) {
            ( new Filesystem() )->deleteDirectory( $this->specificationStorage );
        }

        parent::tearDown();
    }

    /**
     * Gets package providers.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Foundation\Application  $app  The application instance.
     *
     * @return array<int, class-string> Array of service provider class names.
     */
    protected function getPackageProviders( $app ): array
    {
        return [
            SpecificationServiceProvider::class,
        ];
    }

    /**
     * Defines environment setup.
     *
     * @since 1.0.0
     *
     * @param  \Illuminate\Foundation\Application  $app  The application instance.
     */
    protected function defineEnvironment( $app ): void
    {
        // Setup app key for encryption
        $app['config']->set( 'app.key', 'base64:' . base64_encode( random_bytes( 32 ) ) );

        // Setup default database to use sqlite :memory:
        $app['config']->set( 'database.default', 'testbench' );
        $app['config']->set( 'database.connections.testbench', [
            'driver'                  => 'sqlite',
            'database'                => ':memory:',
            'prefix'                  => '',
            'foreign_key_constraints' => true,
        ] );

        // Keep the snapshot and search index out of the real storage directory.
        $this->specificationStorage = sys_get_temp_dir() . '/artisanpack-specification-tests-' . bin2hex( random_bytes( 6 ) );
        $app['config']->set( 'artisanpack.specification.okf.snapshot_path', $this->specificationStorage . '/snapshot' );
        $app['config']->set( 'artisanpack.specification.index.path', ':memory:' );
    }
}
