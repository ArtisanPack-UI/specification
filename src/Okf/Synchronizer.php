<?php

/**
 * OKF synchronizer.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Okf;

use ArtisanPackUI\Specification\Attribution\Attribution;
use ArtisanPackUI\Specification\Contracts\SearchIndex;
use ArtisanPackUI\Specification\Exceptions\SyncFailedException;
use ArtisanPackUI\Specification\Models\Snapshot;
use ArtisanPackUI\Specification\Models\Topic;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Filesystem\Filesystem;

/**
 * Syncs the OKF bundle into the snapshot directory, the database, and the
 * local search index.
 *
 * Sync is idempotent: the snapshot version is the archive's SHA-256, and an
 * archive identical to the current snapshot is a no-op (apart from rebuilding
 * the search index if it is stale). Topic rows are upserted by slug and only
 * rewritten when their content changes; topics no longer in the bundle are
 * deleted, except ones that failed to parse this time, which are kept.
 *
 * @since 1.0.0
 */
final class Synchronizer
{
    /**
     * Cache lock that keeps scheduled and on-demand syncs from overlapping.
     *
     * @var string
     */
    public const LOCK_NAME = 'artisanpack-specification-sync';

    /**
     * Seconds before an abandoned sync lock expires.
     *
     * @var int
     */
    private const LOCK_SECONDS = 600;

    /**
     * Topic columns compared to decide whether a row changed.
     *
     * @var array<int, string>
     */
    private const CONTENT_COLUMNS = [
        'category',
        'status',
        'conformance',
        'title',
        'summary',
        'body_md',
        'sources_json',
        'resource_url',
        'attribution',
        'source_updated_at',
    ];

    /**
     * Creates a new synchronizer.
     *
     * @since 1.0.0
     *
     * @param  OkfDownloader    $downloader  Downloads the archive.
     * @param  OkfBundleLoader  $loader      Validates and parses the archive.
     * @param  SnapshotStore    $store       Vendors the snapshot to disk.
     * @param  SearchIndex      $index       The local search index.
     * @param  Filesystem       $files       The filesystem.
     * @param  CacheRepository  $cache       Provides the sync lock.
     */
    public function __construct(
        private readonly OkfDownloader $downloader,
        private readonly OkfBundleLoader $loader,
        private readonly SnapshotStore $store,
        private readonly SearchIndex $index,
        private readonly Filesystem $files,
        private readonly CacheRepository $cache,
    ) {
    }

    /**
     * Syncs the specification.
     *
     * @since 1.0.0
     *
     * @param  bool         $force        Re-apply even if the archive matches the current snapshot.
     * @param  string|null  $archivePath  Sync from a local archive instead of downloading.
     *
     * @throws SyncFailedException When the archive cannot be fetched or applied, or a sync is already running.
     *
     * @return SyncResult
     */
    public function sync( bool $force = false, ?string $archivePath = null ): SyncResult
    {
        $store = $this->cache->getStore();

        if ( ! $store instanceof LockProvider ) {
            return $this->perform( $force, $archivePath );
        }

        $lock = $store->lock( self::LOCK_NAME, self::LOCK_SECONDS );

        if ( ! $lock->get() ) {
            throw new SyncFailedException( __( 'A specification sync is already running.' ) );
        }

        try {
            return $this->perform( $force, $archivePath );
        } finally {
            $lock->release();
        }
    }

    /**
     * Performs the sync while holding the lock.
     *
     * @since 1.0.0
     *
     * @param  bool         $force        Re-apply even if the archive matches the current snapshot.
     * @param  string|null  $archivePath  Sync from a local archive instead of downloading.
     *
     * @return SyncResult
     */
    private function perform( bool $force, ?string $archivePath ): SyncResult
    {
        $archive = null === $archivePath ? $this->downloader->download() : $this->readLocal( $archivePath );
        $version = hash( 'sha256', $archive );
        $current = Snapshot::current();

        if ( ! $force && $current?->version === $version ) {
            if ( $this->index->version() !== $version ) {
                $this->index->rebuild( Topic::query()->orderBy( 'slug' )->get(), $version );
            }

            return new SyncResult( $version, false, unchanged: $current->topic_count );
        }

        $bundle = $this->loader->load( $archive );

        $this->store->write( $bundle );

        $result = ( new Topic() )->getConnection()->transaction( fn (): SyncResult => $this->apply( $bundle ) );

        $this->index->rebuild( Topic::query()->orderBy( 'slug' )->get(), $version );

        return $result;
    }

    /**
     * Applies a bundle to the database.
     *
     * @since 1.0.0
     *
     * @param  OkfBundle  $bundle  The bundle.
     *
     * @return SyncResult
     */
    private function apply( OkfBundle $bundle ): SyncResult
    {
        $existing  = Topic::query()->get()->keyBy( 'slug' );
        $created   = [];
        $updated   = [];
        $unchanged = 0;

        foreach ( $bundle->topics as $slug => $topic ) {
            $attributes = $this->attributes( $topic, $bundle->version );

            /** @var Topic|null $model */
            $model = $existing->get( $slug );

            if ( null === $model ) {
                Topic::query()->create( $attributes );
                $created[] = $slug;

                continue;
            }

            $model->fill( $attributes );

            if ( $model->isDirty( self::CONTENT_COLUMNS ) ) {
                $updated[] = $slug;
            } else {
                $unchanged++;
            }

            $model->save();
        }

        $keep    = array_merge( array_keys( $bundle->topics ), array_keys( $bundle->errors ) );
        $deleted = $existing->keys()->diff( $keep )->sort()->values()->all();

        if ( [] !== $deleted ) {
            Topic::query()->whereIn( 'slug', $deleted )->delete();
        }

        Snapshot::query()->updateOrCreate( [ 'version' => $bundle->version ], [
            'okf_version' => $bundle->okfVersion,
            'topic_count' => count( $bundle->topics ),
            'synced_at'   => now(),
        ] );

        return new SyncResult( $bundle->version, true, $created, $updated, $deleted, $unchanged, $bundle->errors );
    }

    /**
     * Maps a parsed topic to model attributes.
     *
     * @since 1.0.0
     *
     * @param  OkfTopic  $topic    The topic.
     * @param  string    $version  The snapshot version.
     *
     * @return array<string, mixed>
     */
    private function attributes( OkfTopic $topic, string $version ): array
    {
        return [
            'slug'              => $topic->slug,
            'category'          => $topic->category,
            'status'            => $topic->status,
            'conformance'       => $topic->conformance,
            'title'             => $topic->title,
            'summary'           => $topic->summary,
            'body_md'           => $topic->bodyMarkdown,
            'sources_json'      => $topic->sources,
            'resource_url'      => $topic->resourceUrl,
            'snapshot_version'  => $version,
            'attribution'       => ( new Attribution( $topic->title, $topic->resourceUrl ) )->canonical(),
            'source_updated_at' => $topic->sourceUpdatedAt,
        ];
    }

    /**
     * Reads a local archive file.
     *
     * @since 1.0.0
     *
     * @param  string  $path  The archive path.
     *
     * @throws SyncFailedException When the file cannot be read.
     *
     * @return string
     */
    private function readLocal( string $path ): string
    {
        if ( ! $this->files->isFile( $path ) || ! $this->files->isReadable( $path ) ) {
            throw new SyncFailedException( __( 'The OKF archive [:path] does not exist or is not readable.', [ 'path' => $path ] ) );
        }

        return (string) $this->files->get( $path );
    }
}
