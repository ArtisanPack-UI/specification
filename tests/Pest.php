<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\TarBuilder;

/*
|--------------------------------------------------------------------------
| Test Case
|--------------------------------------------------------------------------
|
| The closure you provide to your test functions is always bound to a specific PHPUnit test
| case class. By default, that class is "PHPUnit\Framework\TestCase". Of course, you may
| need to change it using the "pest()" function to bind a different classes or traits.
|
*/

pest()->extend( Tests\TestCase::class )
    ->use( RefreshDatabase::class )
    ->in( 'Feature' );

/*
|--------------------------------------------------------------------------
| Functions
|--------------------------------------------------------------------------
*/

/**
 * Gets the fixture OKF bundle files keyed by archive path (`okf/...`).
 *
 * @return array<string, string>
 */
function okfFixtureFiles(): array
{
    $root  = __DIR__ . '/Fixtures/okf';
    $files = [];

    $iterator = new RecursiveIteratorIterator( new RecursiveDirectoryIterator( $root, FilesystemIterator::SKIP_DOTS ) );

    foreach ( $iterator as $file ) {
        $relative                    = str_replace( DIRECTORY_SEPARATOR, '/', substr( $file->getPathname(), strlen( $root ) + 1 ) );
        $files[ 'okf/' . $relative ] = (string) file_get_contents( $file->getPathname() );
    }

    ksort( $files );

    return $files;
}

/**
 * Builds a fixture OKF archive, optionally overriding or removing files.
 *
 * @param  array<string, string>  $overrides  Files to add or replace.
 * @param  array<int, string>     $remove     Paths to remove.
 */
function okfArchive( array $overrides = [], array $remove = [] ): string
{
    $files = array_merge( okfFixtureFiles(), $overrides );

    foreach ( $remove as $path ) {
        unset( $files[ $path ] );
    }

    return TarBuilder::fromFiles( $files );
}

/**
 * Writes archive bytes to a temporary file and returns its path.
 */
function writeOkfArchive( string $archive ): string
{
    $path = tempnam( sys_get_temp_dir(), 'okf' ) . '.tar.gz';
    file_put_contents( $path, $archive );

    return $path;
}
