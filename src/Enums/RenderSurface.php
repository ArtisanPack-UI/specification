<?php

/**
 * Topic render surface.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Enums;

/**
 * Where topic text is being rendered.
 *
 * The LicenseGuard decides per surface whether CC BY 4.0 topic text may be
 * rendered at all and where its attribution is placed.
 *
 * @since 1.0.0
 */
enum RenderSurface: string
{
    case Admin     = 'admin';
    case Editor    = 'editor';
    case Agent     = 'agent';
    case Public    = 'public';
    case Paid      = 'paid';
    case Marketing = 'marketing';
}
