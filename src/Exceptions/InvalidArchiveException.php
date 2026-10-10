<?php

/**
 * InvalidArchiveException.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Exceptions;

/**
 * Thrown when the OKF archive is malformed, oversized, or contains no topics.
 *
 * @since 1.0.0
 */
class InvalidArchiveException extends SpecificationException
{
}
