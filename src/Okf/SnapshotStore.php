<?php

/**
 * On-disk snapshot store.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Okf;

use ArtisanPackUI\Specification\Exceptions\SyncFailedException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;
use Throwable;

/**
 * Vendors the validated OKF snapshot to disk.
 *
 * The snapshot is written to a sibling temporary directory first and then
 * swapped into place, so readers never observe a half-written snapshot.
 * Layout: `okf.tar.gz`, `manifest.json`, and the allow-listed `okf/**.md`
 * files.
 *
 * @since 1.0.0
 */
final class SnapshotStore
{
    /**
     * Creates a new store.
     *
     * @since 1.0.0
     *
     * @param  Filesystem  $files  The filesystem.
     * @param  string      $path   Absolute path to the snapshot directory.
     */
    public function __construct(
        private readonly Filesystem $files,
        private readonly string $path,
    ) {
    }

    /**
     * Gets the snapshot directory.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function path(): string
    {
        return $this->path;
    }

    /**
     * Writes a bundle as the current snapshot.
     *
     * @since 1.0.0
     *
     * @param  OkfBundle  $bundle  The bundle.
     *
     * @throws SyncFailedException When the snapshot cannot be written.
     *
     * @return void
     */
    public function write( OkfBundle $bundle ): void
    {
        $suffix    = Str::lower( Str::random( 8 ) );
        $temporary = $this->path . '.tmp-' . $suffix;
        $previous  = $this->path . '.old-' . $suffix;

        try {
            $this->files->ensureDirectoryExists( $temporary );

            foreach ( $bundle->files as $relative => $contents ) {
                if ( 1 !== preg_match( OkfBundleLoader::FILE_PATTERN, $relative ) ) {
                    continue;
                }

                $target = $temporary . '/' . $relative;
                $this->files->ensureDirectoryExists( dirname( $target ) );
                $this->files->put( $target, $contents );
            }

            $this->files->put( $temporary . '/okf.tar.gz', $bundle->archive );
            $this->files->put( $temporary . '/manifest.json', (string) json_encode( [
                'version'     => $bundle->version,
                'okf_version' => $bundle->okfVersion,
                'topic_count' => count( $bundle->topics ),
                'written_at'  => now()->toIso8601String(),
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES ) );

            if ( $this->files->isDirectory( $this->path ) ) {
                $this->files->moveDirectory( $this->path, $previous );
            }

            if ( ! $this->files->moveDirectory( $temporary, $this->path ) ) {
                throw new SyncFailedException( __( 'Could not move the snapshot into :path.', [ 'path' => $this->path ] ) );
            }
        } catch ( Throwable $exception ) {
            $this->restore( $previous );

            throw $exception;
        } finally {
            $this->files->deleteDirectory( $temporary );
            $this->files->deleteDirectory( $previous );
        }
    }

    /**
     * Gets the current snapshot's manifest.
     *
     * @since 1.0.0
     *
     * @return array{version: string, okf_version: string|null, topic_count: int, written_at: string}|null
     */
    public function manifest(): ?array
    {
        $file = $this->path . '/manifest.json';

        if ( ! $this->files->isFile( $file ) ) {
            return null;
        }

        $manifest = json_decode( (string) $this->files->get( $file ), true );

        return is_array( $manifest ) ? $manifest : null;
    }

    /**
     * Moves the previous snapshot back if the swap failed half-way.
     *
     * @since 1.0.0
     *
     * @param  string  $previous  The previous snapshot directory.
     *
     * @return void
     */
    private function restore( string $previous ): void
    {
        if ( $this->files->isDirectory( $previous ) && ! $this->files->isDirectory( $this->path ) ) {
            $this->files->moveDirectory( $previous, $this->path );
        }
    }
}
