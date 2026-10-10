<?php

/**
 * McpUnavailableException.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Exceptions;

/**
 * Thrown when the MCP client is used while it is disabled by configuration.
 *
 * @since 1.0.0
 */
class McpUnavailableException extends SpecificationException
{
}
