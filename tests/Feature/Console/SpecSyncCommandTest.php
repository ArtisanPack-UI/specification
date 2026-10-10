<?php

declare( strict_types=1 );

use ArtisanPackUI\Specification\Models\Snapshot;
use ArtisanPackUI\Specification\Models\Topic;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

it( 'syncs from a local archive and reports the counts', function (): void {
    $archive = okfArchive();

    $this->artisan( 'keystone:spec-sync', [ '--archive' => writeOkfArchive( $archive ) ] )
        ->expectsOutputToContain( 'Synced specification snapshot [' . substr( hash( 'sha256', $archive ), 0, 12 ) . ']' )
        ->expectsOutputToContain( 'Created' )
        ->assertSuccessful();

    expect( Topic::query()->count() )->toBe( 3 );
} );

it( 'is idempotent when run repeatedly', function (): void {
    $path = writeOkfArchive( okfArchive() );

    $this->artisan( 'keystone:spec-sync', [ '--archive' => $path ] )->assertSuccessful();

    $topics   = Topic::query()->orderBy( 'slug' )->get()->map->getAttributes()->all();
    $snapshot = Snapshot::current()->getAttributes();

    Carbon::setTestNow( now()->addWeek() );

    $this->artisan( 'keystone:spec-sync', [ '--archive' => $path ] )
        ->expectsOutputToContain( 'already up to date (3 topics)' )
        ->assertSuccessful();

    expect( Topic::query()->orderBy( 'slug' )->get()->map->getAttributes()->all() )->toBe( $topics )
        ->and( Snapshot::query()->count() )->toBe( 1 )
        ->and( Snapshot::current()->getAttributes() )->toBe( $snapshot );
} );

it( 'reapplies an unchanged archive with --force without duplicating or rewriting rows', function (): void {
    $path = writeOkfArchive( okfArchive() );

    $this->artisan( 'keystone:spec-sync', [ '--archive' => $path ] )->assertSuccessful();

    $topics = Topic::query()->orderBy( 'slug' )->get()->map->getAttributes()->all();

    Carbon::setTestNow( now()->addWeek() );

    $this->artisan( 'keystone:spec-sync', [ '--archive' => $path, '--force' => true ] )
        ->expectsOutputToContain( 'Synced specification snapshot' )
        ->assertSuccessful();

    expect( Topic::query()->orderBy( 'slug' )->get()->map->getAttributes()->all() )->toBe( $topics )
        ->and( Topic::query()->count() )->toBe( 3 )
        ->and( Snapshot::query()->count() )->toBe( 1 );
} );

it( 'warns about topics that failed to parse', function (): void {
    $this->artisan( 'keystone:spec-sync', [ '--archive' => writeOkfArchive( okfArchive( [
        'okf/performance/vary.md' => "---\ntype: Check\ntitle: Vary\n---\nBroken",
    ] ) ) ] )
        ->expectsOutputToContain( 'Skipped [performance/vary]' )
        ->assertSuccessful();
} );

it( 'downloads the archive when no local archive is given', function (): void {
    Http::fake( [ 'https://specification.website/okf.tar.gz' => Http::response( okfArchive() ) ] );

    $this->artisan( 'keystone:spec-sync' )->assertSuccessful();

    expect( Topic::query()->count() )->toBe( 3 );
} );

it( 'fails cleanly when the sync fails', function (): void {
    $this->artisan( 'keystone:spec-sync', [ '--archive' => '/nonexistent/okf.tar.gz' ] )
        ->expectsOutputToContain( 'does not exist or is not readable' )
        ->assertFailed();

    $this->artisan( 'keystone:spec-sync', [ '--archive' => writeOkfArchive( 'garbage' ) ] )->assertFailed();

    expect( Topic::query()->count() )->toBe( 0 );
} );

it( 'neutralizes archive-derived text in parse warnings', function (): void {
    Illuminate\Support\Facades\Artisan::call( 'keystone:spec-sync', [ '--archive' => writeOkfArchive( okfArchive( [
        'okf/performance/vary.md' => "---\ntype: Check\ntitle: \"Vary\"\nrequirement: \"\e[31m<error>bad</error>\"\ncategory: performance\ndescription: x\nresource: https://specification.website/spec/performance/vary/\n---\nBody",
    ] ) ) ] );

    expect( Illuminate\Support\Facades\Artisan::output() )->toContain( 'Skipped [performance/vary]' )
        ->and( Illuminate\Support\Facades\Artisan::output() )->not->toContain( "\e" );
} );
