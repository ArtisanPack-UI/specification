<?php

/**
 * SpecCheck scope.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Enums;

/**
 * The level a SpecCheck evaluates.
 *
 * Page, Section, and Block results roll up into per-page report scores;
 * Site and Server results only roll into category and site scores.
 *
 * @since 1.0.0
 */
enum CheckScope: string
{
    /**
     * Determines whether results at this scope belong to a single page.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function isPageLevel(): bool
    {
        return in_array( $this, [ self::Page, self::Section, self::Block ], true );
    }
    case Site    = 'site';
    case Page    = 'page';
    case Section = 'section';
    case Block   = 'block';
    case Server  = 'server';
}
