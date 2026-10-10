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
use League\CommonMark\Extension\CommonMark\Node\Inline\Image;
use League\CommonMark\Extension\CommonMark\Node\Inline\Link;
use League\CommonMark\MarkdownConverter;
use League\CommonMark\Node\Block\Document;
use League\CommonMark\Node\Node;
use League\CommonMark\Parser\MarkdownParser;
use League\CommonMark\Renderer\HtmlRenderer;
use Stringable;

/**
 * A topic body bound to its attribution.
 *
 * Every output format (HTML, Markdown, array/JSON, string cast) includes the
 * attribution; there is no accessor for the body on its own. When a link
 * resolver is given, relative links in the body are made absolute in the HTML
 * and Markdown output (see TopicLinkResolver).
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
     * @param  string                  $slug          The topic slug.
     * @param  string                  $bodyMarkdown  The Markdown body.
     * @param  Attribution             $attribution   The attribution.
     * @param  AttributionPlacement    $placement     Where the attribution goes.
     * @param  RenderSurface           $surface       The surface being rendered for.
     * @param  MarkdownConverter       $converter     A converter configured to escape raw HTML.
     * @param  TopicLinkResolver|null  $links         Resolves relative links in the body.
     */
    public function __construct(
        public readonly string $slug,
        private readonly string $bodyMarkdown,
        public readonly Attribution $attribution,
        public readonly AttributionPlacement $placement,
        public readonly RenderSurface $surface,
        private readonly MarkdownConverter $converter,
        private readonly ?TopicLinkResolver $links = null,
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
     * The wrapper is marked `lang="en"` because topic text is English,
     * whatever the host application's locale.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function toHtml(): string
    {
        $environment = $this->converter->getEnvironment();
        $document    = ( new MarkdownParser( $environment ) )->parse( $this->bodyMarkdown );

        $this->resolveLinks( $document );

        $body        = '<div class="spec-topic-body">' . ( new HtmlRenderer( $environment ) )->renderDocument( $document )->getContent() . '</div>';
        $attribution = $this->attribution->toHtml();

        return '<div class="spec-topic" lang="en" dir="ltr" data-spec-slug="' . e( $this->slug ) . '">'
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
        $body = null === $this->links ? $this->bodyMarkdown : $this->links->rewriteMarkdown( $this->bodyMarkdown );

        return $this->arrange( trim( $body ), $this->attribution->toMarkdown(), "\n\n---\n\n" );
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
     * Makes the document's link and image URLs absolute.
     *
     * Links that cannot be resolved are unwrapped, keeping their text.
     *
     * @since 1.0.0
     *
     * @param  Document  $document  The parsed body.
     *
     * @return void
     */
    private function resolveLinks( Document $document ): void
    {
        if ( null === $this->links ) {
            return;
        }

        $nodes = [];

        foreach ( $document->iterator() as $node ) {
            if ( $node instanceof Link || $node instanceof Image ) {
                $nodes[] = $node;
            }
        }

        foreach ( $nodes as $node ) {
            $resolved = $this->links->resolve( $node->getUrl() );

            if ( null !== $resolved ) {
                $node->setUrl( $resolved );

                continue;
            }

            $this->unwrap( $node );
        }
    }

    /**
     * Replaces a node with its children.
     *
     * @since 1.0.0
     *
     * @param  Node  $node  The node.
     *
     * @return void
     */
    private function unwrap( Node $node ): void
    {
        $children = [];

        foreach ( $node->children() as $child ) {
            $children[] = $child;
        }

        foreach ( $children as $child ) {
            $node->insertBefore( $child );
        }

        $node->detach();
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
