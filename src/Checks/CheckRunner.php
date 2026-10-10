<?php

/**
 * SpecCheck runner.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Checks;

use ArtisanPackUI\Specification\Contracts\SpecCheck;
use ArtisanPackUI\Specification\Enums\ResultOutcome;
use Throwable;

/**
 * Runs registered checks against a context and collects their results.
 *
 * Only checks whose scope matches the context's scope run. A check that
 * throws, or returns a result for a different slug, produces an Errored
 * result instead of aborting the run.
 *
 * @since 1.0.0
 */
final class CheckRunner
{
    /**
     * Creates a new runner.
     *
     * @since 1.0.0
     *
     * @param  CheckRegistry  $registry  The check registry.
     */
    public function __construct( private readonly CheckRegistry $registry )
    {
    }

    /**
     * Runs the matching checks against the context.
     *
     * @since 1.0.0
     *
     * @param  SpecContext              $context   The subject to check.
     * @param  array<int, string>|null  $slugs     Limit the run to these topic slugs.
     * @param  string|null              $category  Limit the run to this category.
     *
     * @return array<int, SpecResult>
     */
    public function run( SpecContext $context, ?array $slugs = null, ?string $category = null ): array
    {
        $results = [];

        foreach ( $this->registry->forScope( $context->scope ) as $check ) {
            if ( null !== $slugs && ! in_array( $check->slug(), $slugs, true ) ) {
                continue;
            }

            if ( null !== $category && ! str_starts_with( $check->slug(), $category . '/' ) ) {
                continue;
            }

            $results[] = $this->runCheck( $check, $context );
        }

        return $results;
    }

    /**
     * Runs a single check, isolating failures.
     *
     * @since 1.0.0
     *
     * @param  SpecCheck    $check    The check.
     * @param  SpecContext  $context  The context.
     *
     * @return SpecResult
     */
    private function runCheck( SpecCheck $check, SpecContext $context ): SpecResult
    {
        try {
            $result = $check->run( $context );
        } catch ( Throwable $exception ) {
            report( $exception );

            return SpecResult::errored( $check, $context, __( 'The check threw an exception: :message', [
                'message' => $exception->getMessage(),
            ] ), [ 'exception' => $exception::class ] );
        }

        if ( $result->slug !== $check->slug() ) {
            return SpecResult::errored( $check, $context, __( 'The check returned a result for [:returned] instead of [:expected].', [
                'returned' => $result->slug,
                'expected' => $check->slug(),
            ] ) );
        }

        if ( ResultOutcome::Errored !== $result->outcome && $result->status !== $check->status() ) {
            return SpecResult::errored( $check, $context, __( 'The check returned status [:returned] instead of [:expected].', [
                'returned' => $result->status->value,
                'expected' => $check->status()->value,
            ] ) );
        }

        return $result;
    }
}
