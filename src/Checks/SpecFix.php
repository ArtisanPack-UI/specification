<?php

/**
 * SpecCheck automatic fix.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Checks;

use Closure;

/**
 * An automatic fix a SpecCheck offers for a failing subject.
 *
 * The fix is described up front so a UI or agent can ask for confirmation,
 * and only changes anything when apply() is called.
 *
 * @since 1.0.0
 */
final class SpecFix
{
    /**
     * Creates a new fix.
     *
     * @since 1.0.0
     *
     * @param  string                $slug         The topic slug the fix addresses.
     * @param  string                $description  A human-readable description of the change.
     * @param  Closure(): void       $applier      Performs the change.
     * @param  array<string, mixed>  $details      Structured details of the change.
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $description,
        private readonly Closure $applier,
        public readonly array $details = [],
    ) {
    }

    /**
     * Applies the fix.
     *
     * @since 1.0.0
     *
     * @return void
     */
    public function apply(): void
    {
        ( $this->applier )();
    }
}
