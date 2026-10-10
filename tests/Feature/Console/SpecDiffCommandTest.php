<?php

declare( strict_types=1 );

use ArtisanPackUI\Specification\Checks\Foundations\DoctypeCheck;
use ArtisanPackUI\Specification\Contracts\McpClient;
use ArtisanPackUI\Specification\Enums\ChangeType;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use ArtisanPackUI\Specification\Exceptions\McpRequestException;
use ArtisanPackUI\Specification\Mcp\McpChange;
use ArtisanPackUI\Specification\Mcp\McpChangeSet;
use ArtisanPackUI\Specification\Mcp\McpTopic;
use ArtisanPackUI\Specification\Models\Snapshot;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;
use Tests\Support\FakeMcpClient;

/**
 * Syncs the fixture bundle, then a changed bundle, so the current snapshot records a diff.
 */
function syncChangedBundle(): void
{
    specification()->sync( archivePath: writeOkfArchive( okfArchive() ) );

    $files = okfFixtureFiles();

    specification()->sync( archivePath: writeOkfArchive( okfArchive(
        [
            'okf/performance/vary.md' => str_replace( 'requirement: recommended', 'requirement: optional', $files['okf/performance/vary.md'] ),
            'okf/seo/robots-txt.md'   => str_replace( [ 'foundations/doctype', 'The HTML doctype' ], [ 'seo/robots-txt', 'robots.txt' ], $files['okf/foundations/doctype.md'] ),
        ],
        [ 'okf/security/x-xss-protection.md' ],
    ) ) );
}

/**
 * Builds a change set with one status change to the doctype topic and one removal.
 */
function upstreamChanges(): McpChangeSet
{
    $doctype = new McpTopic( 'foundations/doctype', 'foundations', SpecStatus::Required, 'The HTML doctype', '', 'https://specification.website/spec/foundations/doctype/' );

    return new McpChangeSet( '2026-10-01', '2026-10-05', [
        new McpChange( CarbonImmutable::parse( '2026-10-05' ), ChangeType::Status, 'Doctype promoted', 'Now required.', [ $doctype ] ),
        new McpChange( CarbonImmutable::parse( '2026-10-02' ), ChangeType::Removed, 'Old page removed', 'Gone.', [], [ 'old-page' ] ),
    ] );
}

it( 'asks for a sync when no snapshot exists', function (): void {
    $this->artisan( 'keystone:spec-diff' )
        ->expectsOutputToContain( 'No specification snapshot has been synced yet' )
        ->expectsOutputToContain( 'The MCP client is disabled' )
        ->assertSuccessful();
} );

it( 'shows what the last sync changed, snapshot-only when MCP is disabled', function (): void {
    syncChangedBundle();

    $this->artisan( 'keystone:spec-diff' )
        ->expectsOutputToContain( 'Last sync:' )
        ->expectsOutputToContain( 'seo/robots-txt' )
        ->expectsOutputToContain( 'performance/vary' )
        ->expectsOutputToContain( 'security/x-xss-protection' )
        ->expectsOutputToContain( 'only the snapshot diff is shown' )
        ->assertSuccessful();

    expect( Snapshot::current()->changes )->toBe( [
        'created' => [ 'seo/robots-txt' ],
        'updated' => [ 'performance/vary' ],
        'deleted' => [ 'security/x-xss-protection' ],
    ] );
} );

it( 'keeps the recorded diff when an unchanged archive is force re-applied', function (): void {
    syncChangedBundle();
    $changes = Snapshot::current()->changes;

    $this->artisan( 'keystone:spec-sync', [ '--force' => true, '--archive' => app( ArtisanPackUI\Specification\Okf\SnapshotStore::class )->path() . '/okf.tar.gz' ] )->assertSuccessful();

    expect( Snapshot::current()->changes )->toBe( $changes );
} );

it( 'reports when the last sync changed nothing', function (): void {
    specification()->sync( archivePath: writeOkfArchive( okfArchive() ) );
    Snapshot::current()->update( [ 'changes' => [ 'created' => [], 'updated' => [], 'deleted' => [] ] ] );

    $this->artisan( 'keystone:spec-diff' )
        ->expectsOutputToContain( 'The last sync did not change any topics' )
        ->assertSuccessful();
} );

it( 'lists upstream changes since the last sync and flags checked topics', function (): void {
    Carbon::setTestNow( '2026-10-01 09:30:00' );
    specification()->sync( archivePath: writeOkfArchive( okfArchive() ) );
    specification()->registerCheck( DoctypeCheck::class );

    $mcp = new FakeMcpClient( changes: upstreamChanges() );
    app()->instance( McpClient::class, $mcp );

    $this->artisan( 'keystone:spec-diff' )
        ->expectsOutputToContain( '2 upstream changes since 2026-10-01' )
        ->expectsOutputToContain( 'Doctype promoted' )
        ->expectsOutputToContain( 'foundations/doctype (checked)' )
        ->expectsOutputToContain( 'old-page' )
        ->expectsOutputToContain( '1 change affects a registered check: foundations/doctype' )
        ->expectsOutputToContain( 'Run keystone:spec-sync' )
        ->assertSuccessful();

    expect( $mcp->calls )->toBe( [ [ 'getChanges', [ 'since' => '2026-10-01', 'type' => null, 'limit' => null ] ] ] );
} );

it( 'uses --since instead of the last sync date', function (): void {
    $mcp = new FakeMcpClient( changes: new McpChangeSet( '2026-09-15', '2026-09-01' ) );
    app()->instance( McpClient::class, $mcp );

    $this->artisan( 'keystone:spec-diff', [ '--since' => '2026-09-15' ] )
        ->expectsOutputToContain( 'No upstream changes since 2026-09-15.' )
        ->assertSuccessful();

    expect( $mcp->calls[0][1]['since'] )->toBe( '2026-09-15' );
} );

it( 'asks for recent changes when there is no snapshot or --since', function (): void {
    $mcp = new FakeMcpClient( changes: new McpChangeSet( null, '2026-10-05', upstreamChanges()->changes ) );
    app()->instance( McpClient::class, $mcp );

    $this->artisan( 'keystone:spec-diff' )
        ->expectsOutputToContain( '2 recent upstream changes' )
        ->assertSuccessful();

    expect( $mcp->calls[0][1]['since'] )->toBeNull();
} );

it( 'rejects an invalid --since date', function ( string $since ): void {
    $this->artisan( 'keystone:spec-diff', [ '--since' => $since ] )
        ->expectsOutputToContain( 'YYYY-MM-DD' )
        ->assertExitCode( 2 );
} )->with( [ 'words' => [ 'last week' ], 'wrong format' => [ '10/01/2026' ], 'impossible' => [ '2026-13-45' ] ] );

it( 'fails when the MCP request fails', function (): void {
    app()->instance( McpClient::class, new FakeMcpClient( failure: new McpRequestException( 'The MCP tool [get_changes] failed with HTTP 503.' ) ) );

    $this->artisan( 'keystone:spec-diff' )
        ->expectsOutputToContain( 'failed with HTTP 503' )
        ->assertFailed();
} );

it( 'neutralizes terminal escapes and style tags in upstream text', function (): void {
    $hostile = new McpTopic( "foundations/x\e[2Jy", 'foundations', SpecStatus::Required, 'Title', '', 'https://specification.website/spec/foundations/x/' );

    app()->instance( McpClient::class, new FakeMcpClient( changes: new McpChangeSet( '2026-10-01', '2026-10-05', [
        new McpChange( CarbonImmutable::parse( '2026-10-05' ), ChangeType::Changed, "Evil \e]0;pwned\x07<fg=black;bg=black>hidden</>", '', [ $hostile ], [ "gone\r" ] ),
    ] ) ) );

    $this->artisan( 'keystone:spec-diff', [ '--since' => '2026-10-01' ] )
        ->expectsOutputToContain( 'Evil ]0;pwned<fg=black;bg=black>hidden</>' )
        ->doesntExpectOutputToContain( "\e" )
        ->doesntExpectOutputToContain( "\x07" )
        ->assertSuccessful();
} );
