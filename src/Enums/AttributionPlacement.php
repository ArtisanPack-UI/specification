<?php

/**
 * Attribution placement.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Enums;

/**
 * Where the attribution is rendered relative to the topic body.
 *
 * @since 1.0.0
 */
enum AttributionPlacement: string
{
    case Before = 'before';
    case After  = 'after';
}
