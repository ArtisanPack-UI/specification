<?php

declare( strict_types=1 );

use ArtisanPackUI\Specification\Exceptions\InvalidArchiveException;
use ArtisanPackUI\Specification\Okf\TarGzReader;
use Tests\Support\TarBuilder;

function tarReader( int $maxBytes = 1024 * 1024, int $maxEntries = 100 ): TarGzReader
{
    return new TarGzReader( $maxBytes, $maxEntries );
}

it( 'reads regular files keyed by path', function (): void {
    $files = tarReader()->read( TarBuilder::fromFiles( [
        'okf/index.md'             => '# Index',
        'okf/foundations/title.md' => str_repeat( 'x', 700 ),
    ] ) );

    expect( $files )->toBe( [
        'okf/index.md'             => '# Index',
        'okf/foundations/title.md' => str_repeat( 'x', 700 ),
    ] );
} );

it( 'reads archives produced by PharData', function (): void {
    $base = sys_get_temp_dir() . '/okf-phar-' . bin2hex( random_bytes( 4 ) );
    $tar  = new PharData( $base . '.tar' );
    $tar->addFromString( 'okf/index.md', '# Index' );
    $tar->addFromString( 'okf/seo/robots-txt.md', 'robots' );
    $tar->compress( Phar::GZ );

    $files = tarReader()->read( (string) file_get_contents( $base . '.tar.gz' ) );

    @unlink( $base . '.tar' );
    @unlink( $base . '.tar.gz' );

    expect( $files )->toMatchArray( [
        'okf/index.md'          => '# Index',
        'okf/seo/robots-txt.md' => 'robots',
    ] );
} );

it( 'never returns symlinks, hard links, or directories', function (): void {
    $files = tarReader()->read( TarBuilder::gzip( [
        TarBuilder::entry( 'okf/', '', '5' ),
        TarBuilder::entry( 'okf/evil.md', '', '2', '/etc/passwd' ),
        TarBuilder::entry( 'okf/hard.md', '', '1', 'okf/index.md' ),
        TarBuilder::entry( 'okf/index.md', '# Index' ),
    ] ) );

    expect( $files )->toBe( [ 'okf/index.md' => '# Index' ] );
} );

it( 'applies pax and GNU long-name paths to the next entry only', function (): void {
    $long = 'okf/' . str_repeat( 'a', 120 ) . '.md';

    $files = tarReader()->read( TarBuilder::gzip( [
        TarBuilder::paxPath( 'okf/pax/path.md' ),
        TarBuilder::entry( 'ignored-pax.md', 'pax' ),
        TarBuilder::entry( '././@LongLink', $long . "\0", 'L' ),
        TarBuilder::entry( 'ignored-long.md', 'long' ),
        TarBuilder::entry( 'okf/plain.md', 'plain' ),
    ] ) );

    expect( $files )->toBe( [
        'okf/pax/path.md' => 'pax',
        $long             => 'long',
        'okf/plain.md'    => 'plain',
    ] );
} );

it( 'rejects data that is not gzip', function (): void {
    tarReader()->read( 'not an archive' );
} )->throws( InvalidArchiveException::class, 'not valid gzip' );

it( 'rejects archives that decompress beyond the limit', function (): void {
    tarReader( 4096 )->read( TarBuilder::fromFiles( [ 'okf/big.md' => str_repeat( 'a', 100_000 ) ] ) );
} )->throws( InvalidArchiveException::class, 'exceeds 4096 bytes' );

it( 'rejects headers with a bad checksum', function (): void {
    $entry = TarBuilder::entry( 'okf/index.md', '# Index' );
    $entry = substr_replace( $entry, 'X', 0, 1 );

    tarReader()->read( TarBuilder::gzip( [ $entry ] ) );
} )->throws( InvalidArchiveException::class, 'corrupt header' );

it( 'rejects truncated archives', function (): void {
    $entry = TarBuilder::entry( 'okf/index.md', str_repeat( 'a', 2000 ) );

    tarReader()->read( (string) gzencode( substr( $entry, 0, 1024 ) ) );
} )->throws( InvalidArchiveException::class, 'truncated' );

it( 'rejects archives with too many files', function (): void {
    tarReader( maxEntries: 2 )->read( TarBuilder::fromFiles( [
        'okf/a.md' => 'a',
        'okf/b.md' => 'b',
        'okf/c.md' => 'c',
    ] ) );
} )->throws( InvalidArchiveException::class, 'more than 2 files' );
