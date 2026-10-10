<?php

/**
 * SpecCheck contract.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Contracts;

use ArtisanPackUI\Specification\Checks\SpecContext;
use ArtisanPackUI\Specification\Checks\SpecFix;
use ArtisanPackUI\Specification\Checks\SpecResult;
use ArtisanPackUI\Specification\Enums\CheckScope;
use ArtisanPackUI\Specification\Enums\SpecStatus;

/**
 * A single enforceable check against one specification.website topic.
 *
 * Owner packages (seo, security, accessibility, …) implement this contract
 * and register their checks with the CheckRegistry. This package never
 * implements domain checks itself.
 *
 * @since 1.0.0
 */
interface SpecCheck
{
    /**
     * Gets the topic slug this check enforces, e.g. `seo/xml-sitemaps`.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function slug(): string;

    /**
     * Gets the topic's status tier.
     *
     * @since 1.0.0
     *
     * @return SpecStatus
     */
    public function status(): SpecStatus;

    /**
     * Gets the level this check evaluates.
     *
     * @since 1.0.0
     *
     * @return CheckScope
     */
    public function scope(): CheckScope;

    /**
     * Runs the check against the given context.
     *
     * @since 1.0.0
     *
     * @param  SpecContext  $context  The subject being checked.
     *
     * @return SpecResult
     */
    public function run( SpecContext $context ): SpecResult;

    /**
     * Gets an automatic fix for the given context.
     *
     * @since 1.0.0
     *
     * @param  SpecContext  $context  The subject being checked.
     *
     * @return SpecFix|null Null when the check can only be resolved by a human.
     */
    public function autofix( SpecContext $context ): ?SpecFix;
}
