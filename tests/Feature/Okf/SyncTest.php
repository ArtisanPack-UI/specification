<?php

declare( strict_types=1 );

use ArtisanPackUI\Specification\Contracts\SearchIndex;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use ArtisanPackUI\Specification\Exceptions\InvalidArchiveException;
use ArtisanPackUI\Specification\Exceptions\SyncFailedException;
use ArtisanPackUI\Specification\Models\Snapshot;
use ArtisanPackUI\Specification\Models\Topic;
use ArtisanPackUI\Specification\Okf\SnapshotStore;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

const OKF_URL = 'https://specification.website/okf.tar.gz';

it( 'syncs topics, snapshot, and search index from a local archive', function (): void {
    $archive = okfArchive();
    $result  = specification()->sync( archivePath: writeOkfArchive( $archive ) );
    $version = hash( 'sha256', $archive );

    expect( $result->changed )->toBeTrue()
        ->and( $result->version )->toBe( $version )
        ->and( $result->created )->toBe( [ 'foundations/doctype', 'performance/vary', 'security/x-xss-protection' ] )
        ->and( $result->updated )->toBe( [] )
        ->and( $result->deleted )->toBe( [] );

    $topic = Topic::query()->where( 'slug', 'foundations/doctype' )->firstOrFail();

    expect( $topic->category )->toBe( 'foundations' )
        ->and( $topic->status )->toBe( SpecStatus::Required )
        ->and( $topic->conformance )->toBe( 'MUST' )
        ->and( $topic->summary )->toStartWith( 'Every HTML document' )
        ->and( $topic->body_md )->toStartWith( '# The HTML doctype' )
        ->and( $topic->sources_json[0]['author'] )->toBe( 'WHATWG' )
        ->and( $topic->snapshot_version )->toBe( $version )
        ->and( $topic->attribution )->toContain( 'CC BY 4.0' )
        ->and( $topic->attribution )->toContain( 'https://specification.website/spec/foundations/doctype/' )
        ->and( Topic::query()->where( 'slug', 'security/x-xss-protection' )->value( 'status' ) )->toBe( SpecStatus::Avoid );

    $snapshot = Snapshot::current();

    expect( $snapshot->version )->toBe( $version )
        ->and( $snapshot->okf_version )->toBe( '0.2' )
        ->and( $snapshot->topic_count )->toBe( 3 );

    $store = app( SnapshotStore::class );

    expect( $store->manifest() )->toMatchArray( [ 'version' => $version, 'okf_version' => '0.2', 'topic_count' => 3 ] )
        ->and( $store->path() . '/okf/foundations/doctype.md' )->toBeFile()
        ->and( $store->path() . '/okf/references/mdn-doctype.md' )->toBeFile()
        ->and( (string) file_get_contents( $store->path() . '/okf.tar.gz' ) )->toBe( $archive )
        ->and( app( SearchIndex::class )->version() )->toBe( $version );
} );

it( 'is idempotent when the archive has not changed', function (): void {
    $path = writeOkfArchive( okfArchive() );
    specification()->sync( archivePath: $path );

    Carbon::setTestNow( now()->addDay() );
    $before = Topic::query()->orderBy( 'slug' )->get()->map->getAttributes()->all();

    $result = specification()->sync( archivePath: $path );

    expect( $result->changed )->toBeFalse()
        ->and( $result->unchanged )->toBe( 3 )
        ->and( $result->created )->toBe( [] )
        ->and( Topic::query()->orderBy( 'slug' )->get()->map->getAttributes()->all() )->toBe( $before )
        ->and( Snapshot::query()->count() )->toBe( 1 );
} );

it( 'reapplies without changes when forced', function (): void {
    $path = writeOkfArchive( okfArchive() );
    specification()->sync( archivePath: $path );

    $result = specification()->sync( force: true, archivePath: $path );

    expect( $result->changed )->toBeTrue()
        ->and( $result->created )->toBe( [] )
        ->and( $result->updated )->toBe( [] )
        ->and( $result->deleted )->toBe( [] )
        ->and( $result->unchanged )->toBe( 3 )
        ->and( Topic::query()->count() )->toBe( 3 )
        ->and( Snapshot::query()->count() )->toBe( 1 );
} );

it( 'creates, updates, and deletes topics as the bundle changes', function (): void {
    specification()->sync( archivePath: writeOkfArchive( okfArchive() ) );

    $files   = okfFixtureFiles();
    $changed = okfArchive(
        [
            'okf/performance/vary.md' => str_replace( 'requirement: recommended', 'requirement: optional', $files['okf/performance/vary.md'] ),
            'okf/seo/robots-txt.md'   => str_replace( [ 'foundations/doctype', 'The HTML doctype' ], [ 'seo/robots-txt', 'robots.txt' ], $files['okf/foundations/doctype.md'] ),
        ],
        [ 'okf/security/x-xss-protection.md' ],
    );

    $result = specification()->sync( archivePath: writeOkfArchive( $changed ) );

    expect( $result->created )->toBe( [ 'seo/robots-txt' ] )
        ->and( $result->updated )->toBe( [ 'performance/vary' ] )
        ->and( $result->deleted )->toBe( [ 'security/x-xss-protection' ] )
        ->and( $result->unchanged )->toBe( 1 )
        ->and( Topic::query()->pluck( 'slug' )->sort()->values()->all() )->toBe( [ 'foundations/doctype', 'performance/vary', 'seo/robots-txt' ] )
        ->and( Topic::query()->where( 'slug', 'performance/vary' )->first()->status )->toBe( SpecStatus::Optional )
        ->and( Topic::query()->distinct()->pluck( 'snapshot_version' )->all() )->toBe( [ hash( 'sha256', $changed ) ] )
        ->and( Snapshot::query()->count() )->toBe( 2 );
} );

it( 'keeps a previously synced topic that fails to parse', function (): void {
    specification()->sync( archivePath: writeOkfArchive( okfArchive() ) );

    $result = specification()->sync( archivePath: writeOkfArchive( okfArchive( [
        'okf/performance/vary.md' => "---\ntype: Check\ntitle: Vary\n---\nBroken",
    ] ) ) );

    expect( $result->errors )->toHaveKey( 'performance/vary' )
        ->and( $result->deleted )->toBe( [] )
        ->and( Topic::query()->where( 'slug', 'performance/vary' )->exists() )->toBeTrue();
} );

it( 'leaves existing data untouched when the archive is invalid', function (): void {
    $archive = okfArchive();
    specification()->sync( archivePath: writeOkfArchive( $archive ) );

    expect( fn () => specification()->sync( archivePath: writeOkfArchive( 'garbage' ) ) )->toThrow( InvalidArchiveException::class );

    expect( Topic::query()->count() )->toBe( 3 )
        ->and( Snapshot::current()->version )->toBe( hash( 'sha256', $archive ) )
        ->and( app( SnapshotStore::class )->manifest()['version'] )->toBe( hash( 'sha256', $archive ) );
} );

it( 'rebuilds a stale search index even when the archive is unchanged', function (): void {
    $path = writeOkfArchive( okfArchive() );
    specification()->sync( archivePath: $path );

    app( SearchIndex::class )->rebuild( [], 'stale' );

    specification()->sync( archivePath: $path );

    expect( app( SearchIndex::class )->version() )->toBe( Snapshot::current()->version )
        ->and( specification()->search( 'quirks' ) )->toHaveCount( 1 );
} );

it( 'downloads the archive over https', function (): void {
    Http::fake( [ OKF_URL => Http::response( okfArchive(), 200, [ 'Content-Type' => 'application/gzip' ] ) ] );

    $result = specification()->sync();

    expect( $result->created )->toHaveCount( 3 );

    Http::assertSent( fn ( Request $request ): bool => OKF_URL === $request->url() );
} );

it( 'fails when the download is not successful', function (): void {
    Http::fake( [ OKF_URL => Http::response( 'nope', 503 ) ] );

    specification()->sync();
} )->throws( SyncFailedException::class, 'HTTP 503' );

it( 'refuses a non-https archive URL', function (): void {
    config()->set( 'artisanpack.specification.okf.url', 'http://specification.website/okf.tar.gz' );
    app()->forgetInstance( ArtisanPackUI\Specification\Okf\OkfDownloader::class );
    app()->forgetInstance( ArtisanPackUI\Specification\Okf\Synchronizer::class );
    app()->forgetInstance( 'specification' );
    app()->forgetInstance( ArtisanPackUI\Specification\Specification::class );
    Http::fake();

    expect( fn () => specification()->sync() )->toThrow( SyncFailedException::class, 'must use https' );

    Http::assertNothingSent();
} );

it( 'refuses archives larger than the configured limit', function (): void {
    config()->set( 'artisanpack.specification.okf.max_archive_bytes', 100 );
    app()->forgetInstance( ArtisanPackUI\Specification\Okf\OkfDownloader::class );
    app()->forgetInstance( ArtisanPackUI\Specification\Okf\Synchronizer::class );
    app()->forgetInstance( 'specification' );
    app()->forgetInstance( ArtisanPackUI\Specification\Specification::class );
    Http::fake( [ OKF_URL => Http::response( okfArchive() ) ] );

    specification()->sync();
} )->throws( SyncFailedException::class, 'exceeds the 100 byte limit' );

it( 'fails clearly when a local archive is missing', function (): void {
    specification()->sync( archivePath: '/nonexistent/okf.tar.gz' );
} )->throws( SyncFailedException::class, 'does not exist' );

it( 'refuses to start while another sync holds the lock', function (): void {
    $lock = cache()->lock( ArtisanPackUI\Specification\Okf\Synchronizer::LOCK_NAME, 60 );
    $lock->get();

    try {
        expect( fn () => specification()->sync( archivePath: writeOkfArchive( okfArchive() ) ) )
            ->toThrow( SyncFailedException::class, 'already running' );

        expect( Topic::query()->count() )->toBe( 0 );
    } finally {
        $lock->release();
    }

    expect( specification()->sync( archivePath: writeOkfArchive( okfArchive() ) )->created )->toHaveCount( 3 );
} );

it( 'releases the lock when a sync fails', function (): void {
    expect( fn () => specification()->sync( archivePath: writeOkfArchive( 'garbage' ) ) )->toThrow( InvalidArchiveException::class );

    expect( specification()->sync( archivePath: writeOkfArchive( okfArchive() ) )->changed )->toBeTrue();
} );

it( 'wraps transport errors in a sync exception', function (): void {
    Http::fake( fn () => throw new Illuminate\Http\Client\ConnectionException( 'timed out' ) );

    specification()->sync();
} )->throws( SyncFailedException::class, 'timed out' );

it( 'wraps database failures and leaves the snapshot on disk untouched', function (): void {
    $archive = okfArchive();
    specification()->sync( archivePath: writeOkfArchive( $archive ) );

    Illuminate\Support\Facades\Schema::drop( 'specification_snapshots' );

    $changed = okfArchive( [ 'okf/foundations/doctype.md' => str_replace( 'quirks mode', 'quirks rendering', okfFixtureFiles()['okf/foundations/doctype.md'] ) ] );

    expect( fn () => specification()->sync( archivePath: writeOkfArchive( $changed ) ) )
        ->toThrow( SyncFailedException::class, 'Could not apply the specification snapshot' );

    expect( app( SnapshotStore::class )->manifest()['version'] )->toBe( hash( 'sha256', $archive ) )
        ->and( Topic::query()->where( 'slug', 'foundations/doctype' )->value( 'body_md' ) )->toContain( 'quirks mode' );
} );

it( 'rewrites a stale on-disk snapshot even when the archive is unchanged', function (): void {
    $path = writeOkfArchive( okfArchive() );
    specification()->sync( archivePath: $path );

    ( new Illuminate\Filesystem\Filesystem() )->deleteDirectory( app( SnapshotStore::class )->path() );

    $result = specification()->sync( archivePath: $path );

    expect( $result->changed )->toBeFalse()
        ->and( app( SnapshotStore::class )->manifest()['version'] )->toBe( Snapshot::current()->version );
} );

it( 'records an oversized topic as a per-topic error', function (): void {
    $long = okfFixtureFiles()['okf/foundations/doctype.md'];
    $long = preg_replace( '/^title: .*$/m', 'title: "' . str_repeat( 'x', 300 ) . '"', $long );

    $result = specification()->sync( archivePath: writeOkfArchive( okfArchive( [ 'okf/foundations/doctype.md' => $long ] ) ) );

    expect( $result->errors )->toHaveKey( 'foundations/doctype' )
        ->and( $result->errors['foundations/doctype'] )->toContain( 'longer than 255' )
        ->and( Topic::query()->count() )->toBe( 2 );
} );
