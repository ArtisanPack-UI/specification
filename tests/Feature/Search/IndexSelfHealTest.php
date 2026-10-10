<?php

declare( strict_types=1 );

use ArtisanPackUI\Specification\Contracts\SearchIndex;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use ArtisanPackUI\Specification\Models\Snapshot;
use ArtisanPackUI\Specification\Search\SqliteFtsIndex;
use ArtisanPackUI\Specification\Specification;

/**
 * Swaps in a fresh index, as on a server that did not run the sync.
 */
function useFreshIndex( SearchIndex $index ): SearchIndex
{
    app()->instance( SearchIndex::class, $index );
    app()->forgetInstance( Specification::class );
    app()->forgetInstance( 'specification' );

    return $index;
}

it( 'rebuilds an empty index from the topic rows before searching', function (): void {
    specification()->sync( archivePath: writeOkfArchive( okfArchive() ) );

    $index = useFreshIndex( new SqliteFtsIndex( ':memory:' ) );

    expect( $index->version() )->toBeNull()
        ->and( specification()->search( 'quirks' ) )->toHaveCount( 1 )
        ->and( $index->version() )->toBe( Snapshot::current()->version );
} );

it( 'does not rebuild an index that is already current', function (): void {
    specification()->sync( archivePath: writeOkfArchive( okfArchive() ) );

    $index = useFreshIndex( new class ( Snapshot::current()->version ) implements SearchIndex {
        public int $rebuilds = 0;

        public int $versionReads = 0;

        public function __construct( private ?string $indexed )
        {
        }

        public function rebuild( iterable $topics, string $version ): void
        {
            $this->rebuilds++;
            $this->indexed = $version;
        }

        public function search( string $query, int $limit = 10, ?string $category = null, ?SpecStatus $status = null ): array
        {
            return [];
        }

        public function version(): ?string
        {
            $this->versionReads++;

            return $this->indexed;
        }
    } );

    specification()->search( 'quirks' );
    specification()->search( 'doctype' );

    expect( $index->rebuilds )->toBe( 0 )
        ->and( $index->versionReads )->toBe( 1 );
} );

it( 'searches without a rebuild before any sync has run', function (): void {
    $index = useFreshIndex( new SqliteFtsIndex( ':memory:' ) );

    expect( specification()->search( 'quirks' ) )->toBe( [] )
        ->and( $index->version() )->toBeNull();
} );

it( 'waits for a rebuild running elsewhere, then rebuilds if still stale', function (): void {
    specification()->sync( archivePath: writeOkfArchive( okfArchive() ) );
    $index = useFreshIndex( new SqliteFtsIndex( ':memory:' ) );

    $held = Illuminate\Support\Facades\Cache::lock( Specification::INDEX_LOCK_NAME, 60 );
    $held->get();

    Illuminate\Support\Sleep::fake( syncWithCarbon: true );
    Illuminate\Support\Sleep::whenFakingSleep( fn () => $held->release() );

    expect( specification()->search( 'quirks' ) )->toHaveCount( 1 )
        ->and( $index->version() )->toBe( Snapshot::current()->version );
} );

it( 'fails instead of searching an unverified index when the rebuild lock is held too long', function (): void {
    specification()->sync( archivePath: writeOkfArchive( okfArchive() ) );
    $index = useFreshIndex( new SqliteFtsIndex( ':memory:' ) );

    Illuminate\Support\Facades\Cache::lock( Specification::INDEX_LOCK_NAME, 60 )->get();
    Illuminate\Support\Sleep::fake( syncWithCarbon: true );

    expect( fn () => specification()->search( 'quirks' ) )
        ->toThrow( ArtisanPackUI\Specification\Exceptions\SearchIndexUnavailableException::class, 'being rebuilt' )
        ->and( $index->version() )->toBeNull();
} );
