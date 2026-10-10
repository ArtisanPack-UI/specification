<?php

/**
 * SpecCheck result outcome.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Enums;

/**
 * The outcome of a single SpecCheck run.
 *
 * For an Avoid-status check, Passed means the avoided practice is absent
 * and Failed means it is present.
 *
 * @since 1.0.0
 */
enum ResultOutcome: string
{
    case Passed  = 'passed';
    case Failed  = 'failed';
    case Skipped = 'skipped';
    case Errored = 'errored';
}
