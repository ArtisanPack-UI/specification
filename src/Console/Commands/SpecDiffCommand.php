<?php

/**
 * SpecDiffCommand.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Console\Commands;

use ArtisanPackUI\Specification\Checks\CheckRegistry;
use ArtisanPackUI\Specification\Console\Concerns\SanitizesOutput;
use ArtisanPackUI\Specification\Contracts\McpClient;
use ArtisanPackUI\Specification\Exceptions\SpecificationException;
use ArtisanPackUI\Specification\Mcp\McpChange;
use ArtisanPackUI\Specification\Mcp\McpTopic;
use ArtisanPackUI\Specification\Models\Snapshot;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

/**
 * Shows what the last sync changed and, when the MCP client is enabled, what
 * has changed upstream since then.
 *
 * Topics enforced by a registered SpecCheck are flagged, since those changes
 * can affect the site's results.
 *
 * @since 1.0.0
 */
class SpecDiffCommand extends Command
{
    use SanitizesOutput;

    /**
     * The name and signature of the console command.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $signature = 'keystone:spec-diff
        {--since= : Ask the MCP server for changes since this date (YYYY-MM-DD) instead of the last sync}';

    /**
     * The console command description.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $description = 'Show what changed in the specification since the last sync';

    /**
     * Executes the console command.
     *
     * @since 1.0.0
     *
     * @param  McpClient      $mcp       The MCP client.
     * @param  CheckRegistry  $registry  The check registry.
     *
     * @return int
     */
    public function handle( McpClient $mcp, CheckRegistry $registry ): int
    {
        $since = $this->option( 'since' );

        if ( is_string( $since ) && '' !== $since ) {
            try {
                $parsed = CarbonImmutable::createFromFormat( '!Y-m-d', $since );
            } catch ( Throwable ) {
                $parsed = null;
            }

            // Reject dates PHP silently rolls over, such as 2026-13-45.
            $since = $parsed instanceof CarbonImmutable && $parsed->format( 'Y-m-d' ) === $since ? $parsed : false;

            if ( false === $since ) {
                $this->components->error( __( 'The --since option must be a date in YYYY-MM-DD format.' ) );

                return self::INVALID;
            }
        } else {
            $since = null;
        }

        $current = Snapshot::current();

        $this->snapshotDiff( $current, $registry );

        if ( ! $mcp->isEnabled() ) {
            $this->components->info( __( 'The MCP client is disabled, so only the snapshot diff is shown. Set SPECIFICATION_MCP_ENABLED=true to also check for upstream changes.' ) );

            return self::SUCCESS;
        }

        $since ??= $current?->synced_at;

        try {
            $changes = $mcp->getChanges( $since );
        } catch ( SpecificationException $exception ) {
            $this->components->error( $this->plain( $exception->getMessage() ) );

            return self::FAILURE;
        }

        $this->newLine();

        if ( $changes->isEmpty() ) {
            $this->components->info( null === $changes->since
                ? __( 'The MCP server reported no upstream changes.' )
                : __( 'No upstream changes since :date.', [ 'date' => $this->plain( $changes->since ) ] ) );

            return self::SUCCESS;
        }

        $this->components->info( null === $changes->since
            ? trans_choice( ':count recent upstream change:|:count recent upstream changes:', count( $changes->changes ), [ 'count' => count( $changes->changes ) ] )
            : trans_choice( ':count upstream change since :date:|:count upstream changes since :date:', count( $changes->changes ), [
                'count' => count( $changes->changes ),
                'date'  => $this->plain( $changes->since ),
            ] ) );

        $this->table(
            [ __( 'Date' ), __( 'Type' ), __( 'Change' ), __( 'Topics' ) ],
            array_map( fn ( McpChange $change ): array => [
                $change->date->toDateString(),
                $change->type->label(),
                $this->plain( $change->title ),
                implode( "\n", array_merge(
                    array_map( fn ( McpTopic $topic ): string => $this->slugLabel( $topic->slug, $registry ), $change->topics ),
                    array_map( fn ( string $slug ): string => $this->plain( $slug ), $change->unresolvedSlugs ),
                ) ),
            ], $changes->changes ),
        );

        $affected = array_values( array_filter( $changes->slugs(), static fn ( string $slug ): bool => $registry->has( $slug ) ) );

        if ( [] !== $affected ) {
            $this->components->warn( trans_choice( ':count change affects a registered check: :slugs|:count changes affect registered checks: :slugs', count( $affected ), [
                'count' => count( $affected ),
                'slugs' => $this->plain( implode( ', ', $affected ) ),
            ] ) );
        }

        $this->components->info( __( 'Run keystone:spec-sync to pull upstream changes into the local snapshot.' ) );

        return self::SUCCESS;
    }

    /**
     * Prints what the sync that produced the current snapshot changed.
     *
     * @since 1.0.0
     *
     * @param  Snapshot|null  $current   The current snapshot.
     * @param  CheckRegistry  $registry  The check registry.
     *
     * @return void
     */
    private function snapshotDiff( ?Snapshot $current, CheckRegistry $registry ): void
    {
        if ( null === $current ) {
            $this->components->warn( __( 'No specification snapshot has been synced yet. Run keystone:spec-sync first.' ) );

            return;
        }

        $this->components->info( __( 'Last sync: :date (snapshot [:version], :count topics).', [
            'date'    => $current->synced_at->toDateTimeString(),
            'version' => substr( $current->version, 0, 12 ),
            'count'   => $current->topic_count,
        ] ) );

        if ( null === $current->changes ) {
            $this->components->bulletList( [ __( 'No change record is stored for this snapshot.' ) ] );

            return;
        }

        $sections = [
            'created' => __( 'Added' ),
            'updated' => __( 'Updated' ),
            'deleted' => __( 'Removed' ),
        ];

        $printed = false;

        foreach ( $sections as $key => $label ) {
            $slugs = (array) ( $current->changes[ $key ] ?? [] );

            if ( [] === $slugs ) {
                continue;
            }

            $printed = true;
            $this->components->twoColumnDetail( '<fg=yellow>' . $label . '</>', (string) count( $slugs ) );
            $this->components->bulletList( array_map( fn ( string $slug ): string => $this->slugLabel( $slug, $registry ), $slugs ) );
        }

        if ( ! $printed ) {
            $this->components->bulletList( [ __( 'The last sync did not change any topics.' ) ] );
        }
    }

    /**
     * Labels a slug, flagging it when a registered check enforces it.
     *
     * @since 1.0.0
     *
     * @param  string         $slug      The topic slug.
     * @param  CheckRegistry  $registry  The check registry.
     *
     * @return string
     */
    private function slugLabel( string $slug, CheckRegistry $registry ): string
    {
        $label = $this->plain( $slug );

        return $registry->has( $slug ) ? $label . ' ' . __( '(checked)' ) : $label;
    }
}
