<?php

/**
 * Upstream change type.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Enums;

/**
 * The kind of change an upstream `get_changes` entry describes.
 *
 * @since 1.0.0
 */
enum ChangeType: string
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
            self::Added   => __( 'Added' ),
            self::Changed => __( 'Changed' ),
            self::Status  => __( 'Status changed' ),
            self::Removed => __( 'Removed' ),
        };
    }
    case Added   = 'added';
    case Changed = 'changed';
    case Status  = 'status';
    case Removed = 'removed';
}
