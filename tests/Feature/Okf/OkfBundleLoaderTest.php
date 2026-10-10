<?php

declare( strict_types=1 );

use ArtisanPackUI\Specification\Exceptions\InvalidArchiveException;
use ArtisanPackUI\Specification\Okf\OkfBundleLoader;
use ArtisanPackUI\Specification\Okf\TarGzReader;
use ArtisanPackUI\Specification\Okf\TopicParser;

function bundleLoader(): OkfBundleLoader
{
    return new OkfBundleLoader( new TarGzReader( 10 * 1024 * 1024 ), new TopicParser() );
}

it( 'loads the fixture bundle', function (): void {
    $archive = okfArchive();
    $bundle  = bundleLoader()->load( $archive );

    expect( array_keys( $bundle->topics ) )->toBe( [ 'foundations/doctype', 'performance/vary', 'security/x-xss-protection' ] )
        ->and( $bundle->version )->toBe( hash( 'sha256', $archive ) )
        ->and( $bundle->okfVersion )->toBe( '0.2' )
        ->and( $bundle->errors )->toBe( [] )
        ->and( $bundle->files )->toHaveKeys( [ 'okf/index.md', 'okf/log.md', 'okf/foundations/index.md', 'okf/references/mdn-doctype.md' ] );
} );

it( 'drops files outside the allow-list', function ( string $path ): void {
    $bundle = bundleLoader()->load( okfArchive( [ $path => okfFixtureFiles()['okf/foundations/doctype.md'] ] ) );

    expect( $bundle->files )->not->toHaveKey( $path )
        ->and( $bundle->topics )->toHaveCount( 3 );
} )->with( [
    'parent traversal'  => 'okf/../evil.md',
    'nested traversal'  => 'okf/foundations/../../evil.md',
    'absolute path'     => '/etc/evil.md',
    'outside okf'       => 'other/foundations/evil.md',
    'too deep'          => 'okf/foundations/deep/evil.md',
    'not markdown'      => 'okf/foundations/evil.php',
    'uppercase'         => 'okf/Foundations/Evil.md',
] );

it( 'records topics that fail to parse without failing the bundle', function (): void {
    $bundle = bundleLoader()->load( okfArchive( [ 'okf/seo/broken.md' => "---\ntype: Check\ntitle: Broken\n---\nBody" ] ) );

    expect( $bundle->topics )->toHaveCount( 3 )
        ->and( $bundle->errors )->toHaveKey( 'seo/broken' );
} );

it( 'rejects bundles with no valid topics', function (): void {
    bundleLoader()->load( okfArchive( remove: [ 'okf/foundations/doctype.md', 'okf/performance/vary.md', 'okf/security/x-xss-protection.md' ] ) );
} )->throws( InvalidArchiveException::class, 'no valid topics' );
