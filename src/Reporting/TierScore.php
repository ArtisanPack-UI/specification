<?php

/**
 * Tier score.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Reporting;

/**
 * How many evaluated checks in one tier were met.
 *
 * @since 1.0.0
 */
final class TierScore
{
    /**
     * Creates a new score.
     *
     * @since 1.0.0
     *
     * @param  int  $met    Checks that passed.
     * @param  int  $total  Checks that passed or failed (skipped and errored excluded).
     */
    public function __construct(
        public readonly int $met = 0,
        public readonly int $total = 0,
    ) {
    }

    /**
     * Gets the percentage met, rounded to one decimal place.
     *
     * @since 1.0.0
     *
     * @return float|null Null when nothing in the tier was evaluated.
     */
    public function percent(): ?float
    {
        if ( 0 === $this->total ) {
            return null;
        }

        return round( $this->met / $this->total * 100, 1 );
    }

    /**
     * Gets the score as an array.
     *
     * @since 1.0.0
     *
     * @return array{met: int, total: int, percent: float|null}
     */
    public function toArray(): array
    {
        return [
            'met'     => $this->met,
            'total'   => $this->total,
            'percent' => $this->percent(),
        ];
    }
}
