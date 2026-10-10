<?php

/**
 * Specification topic status.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Enums;

/**
 * The four honest tiers a specification.website topic can carry.
 *
 * Backed by the OKF `requirement` front-matter value.
 *
 * @since 1.0.0
 */
enum SpecStatus: string
{
    /**
     * Gets the translated, human-readable label.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function label(): string
    {
        return match ( $this ) {
            self::Required    => __( 'Required' ),
            self::Recommended => __( 'Recommended' ),
            self::Optional    => __( 'Optional' ),
            self::Avoid       => __( 'Avoid' ),
        };
    }

    /**
     * Gets the RFC 2119 conformance keyword that matches the status.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function conformance(): string
    {
        return match ( $this ) {
            self::Required    => 'MUST',
            self::Recommended => 'SHOULD',
            self::Optional    => 'MAY',
            self::Avoid       => 'MUST NOT',
        };
    }
    case Required    = 'required';
    case Recommended = 'recommended';
    case Optional    = 'optional';
    case Avoid       = 'avoid';
}
