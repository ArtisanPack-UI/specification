<?php

/**
 * SpecSyncCommand.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Console\Commands;

use ArtisanPackUI\Specification\Console\Concerns\SanitizesOutput;
use ArtisanPackUI\Specification\Exceptions\SpecificationException;
use ArtisanPackUI\Specification\Okf\Synchronizer;
use Illuminate\Console\Command;

/**
 * Syncs the OKF bundle into the snapshot, topics table, and search index.
 *
 * Safe to run on cron: an unchanged archive is a no-op, and overlapping runs
 * are refused by the synchronizer's lock.
 *
 * @since 1.0.0
 */
class SpecSyncCommand extends Command
{
    use SanitizesOutput;

    /**
     * The name and signature of the console command.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $signature = 'keystone:spec-sync
        {--force : Re-apply the archive even if it matches the current snapshot}
        {--archive= : Sync from a local okf.tar.gz instead of downloading it}';

    /**
     * The console command description.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $description = 'Sync the specification.website OKF snapshot into the local index';

    /**
     * Executes the console command.
     *
     * @since 1.0.0
     *
     * @param  Synchronizer  $synchronizer  The OKF synchronizer.
     *
     * @return int
     */
    public function handle( Synchronizer $synchronizer ): int
    {
        $archive = $this->option( 'archive' );

        try {
            $result = $synchronizer->sync( (bool) $this->option( 'force' ), is_string( $archive ) && '' !== $archive ? $archive : null );
        } catch ( SpecificationException $exception ) {
            $this->components->error( $this->plain( $exception->getMessage() ) );

            return self::FAILURE;
        }

        $version = substr( $result->version, 0, 12 );

        if ( ! $result->changed ) {
            $this->components->info( __( 'The specification snapshot [:version] is already up to date (:count topics).', [
                'version' => $version,
                'count'   => $result->unchanged,
            ] ) );

            return self::SUCCESS;
        }

        $this->components->info( __( 'Synced specification snapshot [:version].', [ 'version' => $version ] ) );

        $this->components->twoColumnDetail( __( 'Created' ), (string) count( $result->created ) );
        $this->components->twoColumnDetail( __( 'Updated' ), (string) count( $result->updated ) );
        $this->components->twoColumnDetail( __( 'Deleted' ), (string) count( $result->deleted ) );
        $this->components->twoColumnDetail( __( 'Unchanged' ), (string) $result->unchanged );

        foreach ( $result->errors as $slug => $error ) {
            $this->components->warn( __( 'Skipped [:slug]: :error', [ 'slug' => $this->plain( (string) $slug ), 'error' => $this->plain( $error ) ] ) );
        }

        return self::SUCCESS;
    }
}
