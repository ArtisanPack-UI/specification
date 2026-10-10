<?php

/**
 * Four-tier breakdown.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Reporting;

use ArtisanPackUI\Specification\Checks\SpecResult;
use ArtisanPackUI\Specification\Enums\ResultOutcome;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use Illuminate\Contracts\Support\Arrayable;

/**
 * Scores for one slice of results, kept per tier.
 *
 * Required, Recommended, and Optional are reported as separate met
 * percentages; Avoid is reported as a count of violations present. There is
 * deliberately no combined "overall" percentage.
 *
 * @implements Arrayable<string, mixed>
 *
 * @since 1.0.0
 */
final class TierBreakdown implements Arrayable
{
    /**
     * Creates a new breakdown.
     *
     * @since 1.0.0
     *
     * @param  TierScore  $required      Required-tier score.
     * @param  TierScore  $recommended   Recommended-tier score.
     * @param  TierScore  $optional      Optional-tier score.
     * @param  int        $avoidPresent  Avoid-tier checks that found the avoided practice.
     * @param  int        $avoidChecked  Avoid-tier checks that were evaluated.
     * @param  int        $skipped       Results that were not applicable.
     * @param  int        $errored       Results that could not be evaluated.
     */
    public function __construct(
        public readonly TierScore $required = new TierScore(),
        public readonly TierScore $recommended = new TierScore(),
        public readonly TierScore $optional = new TierScore(),
        public readonly int $avoidPresent = 0,
        public readonly int $avoidChecked = 0,
        public readonly int $skipped = 0,
        public readonly int $errored = 0,
    ) {
    }

    /**
     * Builds a breakdown from results.
     *
     * @since 1.0.0
     *
     * @param  iterable<SpecResult>  $results  The results.
     *
     * @return self
     */
    public static function fromResults( iterable $results ): self
    {
        $met = $total = [
            SpecStatus::Required->value    => 0,
            SpecStatus::Recommended->value => 0,
            SpecStatus::Optional->value    => 0,
            SpecStatus::Avoid->value       => 0,
        ];
        $skipped = 0;
        $errored = 0;

        foreach ( $results as $result ) {
            if ( ResultOutcome::Skipped === $result->outcome ) {
                $skipped++;

                continue;
            }

            if ( ResultOutcome::Errored === $result->outcome ) {
                $errored++;

                continue;
            }

            $total[ $result->status->value ]++;

            if ( $result->isMet() ) {
                $met[ $result->status->value ]++;
            }
        }

        $avoid = SpecStatus::Avoid->value;

        return new self(
            new TierScore( $met[ SpecStatus::Required->value ], $total[ SpecStatus::Required->value ] ),
            new TierScore( $met[ SpecStatus::Recommended->value ], $total[ SpecStatus::Recommended->value ] ),
            new TierScore( $met[ SpecStatus::Optional->value ], $total[ SpecStatus::Optional->value ] ),
            $total[ $avoid ] - $met[ $avoid ],
            $total[ $avoid ],
            $skipped,
            $errored,
        );
    }

    /**
     * Gets the breakdown as an array.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'required_met'    => $this->required->percent(),
            'recommended_met' => $this->recommended->percent(),
            'optional_met'    => $this->optional->percent(),
            'avoid_present'   => $this->avoidPresent,
            'required'        => $this->required->toArray(),
            'recommended'     => $this->recommended->toArray(),
            'optional'        => $this->optional->toArray(),
            'avoid'           => [
                'present' => $this->avoidPresent,
                'checked' => $this->avoidChecked,
            ],
            'skipped'         => $this->skipped,
            'errored'         => $this->errored,
        ];
    }
}
