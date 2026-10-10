<?php

/**
 * Specification Facade.
 *
 * Provides static access to the Specification class.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * Specification Facade.
 *
 * @see \ArtisanPackUI\Specification\Specification
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */
class Specification extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @since 1.0.0
     *
     * @return string
     */
    protected static function getFacadeAccessor(): string
    {
        return 'specification';
    }
}
