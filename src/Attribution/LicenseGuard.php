<?php

/**
 * CC BY 4.0 license guard.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Attribution;

use ArtisanPackUI\Specification\Enums\AttributionPlacement;
use ArtisanPackUI\Specification\Enums\RenderSurface;
use ArtisanPackUI\Specification\Exceptions\LicenseViolationException;

/**
 * Decides whether topic text may be rendered on a surface, and how.
 *
 * Blocked surfaces (marketing by default) may never embed topic text.
 * Prominent surfaces (paid by default) get the attribution before the body
 * so it reads as a first-class element of the result, not a footer.
 *
 * @since 1.0.0
 */
final class LicenseGuard
{
    /**
     * Blocked surfaces.
     *
     * @var array<int, RenderSurface>
     */
    private readonly array $blocked;

    /**
     * Surfaces that get prominent attribution.
     *
     * @var array<int, RenderSurface>
     */
    private readonly array $prominent;

    /**
     * Creates a new guard.
     *
     * @since 1.0.0
     *
     * @param  array<int, RenderSurface|string>  $blocked    Surfaces that may not render topic text.
     * @param  array<int, RenderSurface|string>  $prominent  Surfaces that get attribution before the body.
     */
    public function __construct( array $blocked = [ RenderSurface::Marketing ], array $prominent = [ RenderSurface::Paid ] )
    {
        $this->blocked   = $this->surfaces( $blocked );
        $this->prominent = $this->surfaces( $prominent );
    }

    /**
     * Asserts that topic text may be rendered on the surface.
     *
     * @since 1.0.0
     *
     * @param  RenderSurface  $surface  The surface.
     *
     * @throws LicenseViolationException When the surface is blocked.
     *
     * @return void
     */
    public function assertCanRender( RenderSurface $surface ): void
    {
        if ( $this->isBlocked( $surface ) ) {
            throw new LicenseViolationException( __( 'specification.website topic text may not be rendered on the [:surface] surface.', [
                'surface' => $surface->value,
            ] ) );
        }
    }

    /**
     * Determines whether the surface is blocked.
     *
     * @since 1.0.0
     *
     * @param  RenderSurface  $surface  The surface.
     *
     * @return bool
     */
    public function isBlocked( RenderSurface $surface ): bool
    {
        return in_array( $surface, $this->blocked, true );
    }

    /**
     * Gets where the attribution goes on the surface.
     *
     * @since 1.0.0
     *
     * @param  RenderSurface  $surface  The surface.
     *
     * @return AttributionPlacement
     */
    public function placement( RenderSurface $surface ): AttributionPlacement
    {
        return in_array( $surface, $this->prominent, true ) ? AttributionPlacement::Before : AttributionPlacement::After;
    }

    /**
     * Normalizes configured surfaces, ignoring unknown values.
     *
     * @since 1.0.0
     *
     * @param  array<int, RenderSurface|string>  $surfaces  The configured surfaces.
     *
     * @return array<int, RenderSurface>
     */
    private function surfaces( array $surfaces ): array
    {
        $normalized = [];

        foreach ( $surfaces as $surface ) {
            $surface = $surface instanceof RenderSurface ? $surface : RenderSurface::tryFrom( (string) $surface );

            if ( null !== $surface ) {
                $normalized[] = $surface;
            }
        }

        return $normalized;
    }
}
