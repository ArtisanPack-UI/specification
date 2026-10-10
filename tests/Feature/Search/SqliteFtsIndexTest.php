<?php

declare( strict_types=1 );

use ArtisanPackUI\Specification\Contracts\SearchIndex;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use ArtisanPackUI\Specification\Exceptions\SearchIndexUnavailableException;
use ArtisanPackUI\Specification\Search\SearchHit;
use ArtisanPackUI\Specification\Search\SqliteFtsIndex;

beforeEach( function (): void {
    specification()->sync( archivePath: writeOkfArchive( okfArchive() ) );
} );

it( 'finds topics by body text with stemming', function (): void {
    $hits = specification()->search( 'quirk' );

    expect( $hits )->toHaveCount( 1 )
        ->and( $hits[0] )->toBeInstanceOf( SearchHit::class )
        ->and( $hits[0]->slug )->toBe( 'foundations/doctype' )
        ->and( $hits[0]->status )->toBe( SpecStatus::Required )
        ->and( $hits[0]->resourceUrl )->toBe( 'https://specification.website/spec/foundations/doctype/' )
        ->and( $hits[0]->snippet )->toContain( '[[quirks]]' );
} );

it( 'ranks title matches above body matches', function (): void {
    $hits = specification()->search( 'header' );

    expect( array_map( fn ( SearchHit $hit ): string => $hit->slug, $hits ) )->toBe( [ 'performance/vary', 'security/x-xss-protection' ] );
} );

it( 'matches word prefixes and requires every word', function (): void {
    expect( specification()->search( 'doct' ) )->toHaveCount( 1 )
        ->and( specification()->search( 'doctype vary' ) )->toBe( [] );
} );

it( 'filters by category and status', function (): void {
    expect( specification()->search( 'header', category: 'security' ) )->toHaveCount( 1 )
        ->and( specification()->search( 'header', status: SpecStatus::Recommended )[0]->slug )->toBe( 'performance/vary' )
        ->and( specification()->search( 'header', category: 'foundations' ) )->toBe( [] );
} );

it( 'clamps the limit', function (): void {
    expect( specification()->search( 'header', limit: 1 ) )->toHaveCount( 1 )
        ->and( specification()->search( 'header', limit: 0 ) )->toHaveCount( 1 );
} );

it( 'returns nothing for queries without words', function ( string $query ): void {
    expect( specification()->search( $query ) )->toBe( [] );
} )->with( [ '', '   ', '"', '*', '()' ] );

it( 'treats FTS5 syntax in queries as literal text', function ( string $query ): void {
    expect( fn () => specification()->search( $query ) )->not->toThrow( Throwable::class );
} )->with( [
    'column filter' => 'title:doctype',
    'boolean ops'   => 'doctype OR NOT vary',
    'near'          => 'NEAR(doctype quirks, 2)',
    'quotes'        => '"doctype',
    'caret'         => '^doctype',
    'sql'           => "doctype'; DROP TABLE topics; --",
] );

it( 'builds quoted prefix MATCH expressions', function (): void {
    $index = new SqliteFtsIndex( ':memory:' );

    expect( $index->matchExpression( 'Title: "Doctype" OR quirks' ) )->toBe( '"title"* "doctype"* "or"* "quirks"*' )
        ->and( $index->matchExpression( 'café naïve' ) )->toBe( '"café"* "naïve"*' )
        ->and( $index->matchExpression( '' ) )->toBe( '' );
} );

it( 'reports the indexed version and survives rebuilds', function (): void {
    $index = app( SearchIndex::class );
    $index->rebuild( [], 'empty' );

    expect( $index->version() )->toBe( 'empty' )
        ->and( $index->search( 'doctype' ) )->toBe( [] );
} );

it( 'persists to a file on disk', function (): void {
    $path  = $this->specificationStorage . '/index/index.sqlite';
    $index = new SqliteFtsIndex( $path );
    $index->rebuild( ArtisanPackUI\Specification\Models\Topic::query()->get(), 'v1' );

    expect( $path )->toBeFile()
        ->and( ( new SqliteFtsIndex( $path ) )->search( 'quirks' ) )->toHaveCount( 1 );
} );

it( 'throws a package exception when the index cannot be opened', function (): void {
    ( new SqliteFtsIndex( '/dev/null/not-a-dir/index.sqlite' ) )->version();
} )->throws( SearchIndexUnavailableException::class );

it( 'uses WAL so searches can read while another connection rebuilds', function (): void {
    $path   = $this->specificationStorage . '/index/wal.sqlite';
    $writer = new SqliteFtsIndex( $path );
    $writer->rebuild( ArtisanPackUI\Specification\Models\Topic::query()->get(), 'v1' );

    $raw = new PDO( 'sqlite:' . $path );

    expect( $raw->query( 'PRAGMA journal_mode' )->fetchColumn() )->toBe( 'wal' );

    $raw->exec( 'BEGIN IMMEDIATE' );
    $raw->exec( "INSERT INTO meta (key, value) VALUES ('probe', 'x')" );

    expect( ( new SqliteFtsIndex( $path ) )->version() )->toBe( 'v1' );

    $raw->exec( 'ROLLBACK' );
} );
