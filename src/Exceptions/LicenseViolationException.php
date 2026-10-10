<?php

/**
 * LicenseViolationException.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Exceptions;

/**
 * Thrown when topic text is rendered in a way the CC BY 4.0 license guard forbids.
 *
 * @since 1.0.0
 */
class LicenseViolationException extends SpecificationException
{
}
