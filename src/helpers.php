<?php

/**
 * Specification helper functions.
 *
 * This file contains global helper functions for the package.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

use ArtisanPackUI\Specification\Specification;

if ( ! function_exists( 'specification' ) ) {
    /**
     * Get the Specification instance.
     *
     * @since 1.0.0
     *
     * @return Specification
     */
    function specification(): Specification
    {
        return app( 'specification' );
    }
}
