<?php

/**
 * Rendered topic.
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
use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Contracts\Support\Htmlable;
use JsonSerializable;
use League\CommonMark\MarkdownConverter;
use Stringable;

/**
 * A topic body bound to its attribution.
 *
 * Every output format (HTML, Markdown, array/JSON, string cast) includes the
 * attribution; there is no accessor for the body on its own.
 *
 * @implements Arrayable<string, mixed>
 *
 * @since 1.0.0
 */
final class RenderedTopic implements Arrayable, Htmlable, JsonSerializable, Stringable
{
    /**
     * Creates a new rendered topic.
     *
     * @since 1.0.0
     *
     * @param  string                $slug         The topic slug.
     * @param  string                $bodyMarkdown The Markdown body.
     * @param  Attribution           $attribution  The attribution.
     * @param  AttributionPlacement  $placement    Where the attribution goes.
     * @param  RenderSurface         $surface      The surface being rendered for.
     * @param  MarkdownConverter     $converter    A converter configured to escape raw HTML.
     */
    public function __construct(
        public readonly string $slug,
        private readonly string $bodyMarkdown,
        public readonly Attribution $attribution,
        public readonly AttributionPlacement $placement,
        public readonly RenderSurface $surface,
        private readonly MarkdownConverter $converter,
    ) {
    }

    /**
     * Gets the HTML representation.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function __toString(): string
    {
        return $this->toHtml();
    }

    /**
     * Gets the body and attribution as HTML.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function toHtml(): string
    {
        $body        = '<div class="spec-topic-body">' . $this->converter->convert( $this->bodyMarkdown )->getContent() . '</div>';
        $attribution = $this->attribution->toHtml();

        return '<div class="spec-topic" data-spec-slug="' . e( $this->slug ) . '">'
            . $this->arrange( $body, $attribution, '' )
            . '</div>';
    }

    /**
     * Gets the body and attribution as Markdown.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function toMarkdown(): string
    {
        return $this->arrange( trim( $this->bodyMarkdown ), $this->attribution->toMarkdown(), "\n\n---\n\n" );
    }

    /**
     * Gets the rendered topic as an array.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug'        => $this->slug,
            'surface'     => $this->surface->value,
            'placement'   => $this->placement->value,
            'body_md'     => $this->bodyMarkdown,
            'attribution' => $this->attribution->toArray(),
        ];
    }

    /**
     * Gets the data to serialize to JSON.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }

    /**
     * Joins body and attribution in the configured order.
     *
     * @since 1.0.0
     *
     * @param  string  $body         The body.
     * @param  string  $attribution  The attribution.
     * @param  string  $separator    The separator.
     *
     * @return string
     */
    private function arrange( string $body, string $attribution, string $separator ): string
    {
        return AttributionPlacement::Before === $this->placement
            ? $attribution . $separator . $body
            : $body . $separator . $attribution;
    }
}
