<?php

/**
 * Topic renderer.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Attribution;

use ArtisanPackUI\Specification\Enums\RenderSurface;
use ArtisanPackUI\Specification\Exceptions\LicenseViolationException;
use ArtisanPackUI\Specification\Models\Topic;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\GithubFlavoredMarkdownExtension;
use League\CommonMark\MarkdownConverter;

/**
 * The renderer boundary for topic text.
 *
 * Attribution is enforced here rather than by callers: a RenderedTopic always
 * carries its CC BY 4.0 attribution, and the LicenseGuard is consulted before
 * anything is produced. Topic Markdown comes from a third party, so raw HTML
 * in it is escaped and unsafe link protocols are dropped.
 *
 * @since 1.0.0
 */
final class SpecTopicRenderer
{
    /**
     * The Markdown converter.
     *
     * @var MarkdownConverter
     */
    private readonly MarkdownConverter $converter;

    /**
     * Creates a new renderer.
     *
     * @since 1.0.0
     *
     * @param  LicenseGuard  $guard  The license guard.
     */
    public function __construct( private readonly LicenseGuard $guard )
    {
        $environment = new Environment( [
            'html_input'         => 'escape',
            'allow_unsafe_links' => false,
            'max_nesting_level'  => 50,
        ] );
        $environment->addExtension( new CommonMarkCoreExtension() );
        $environment->addExtension( new GithubFlavoredMarkdownExtension() );

        $this->converter = new MarkdownConverter( $environment );
    }

    /**
     * Renders a topic for a surface.
     *
     * @since 1.0.0
     *
     * @param  Topic          $topic    The topic.
     * @param  RenderSurface  $surface  Where the topic is displayed.
     *
     * @throws LicenseViolationException When the surface may not render topic text.
     *
     * @return RenderedTopic
     */
    public function render( Topic $topic, RenderSurface $surface = RenderSurface::Admin ): RenderedTopic
    {
        $this->guard->assertCanRender( $surface );

        return new RenderedTopic(
            $topic->slug,
            $topic->body_md,
            Attribution::forTopic( $topic ),
            $this->guard->placement( $surface ),
            $surface,
            $this->converter,
        );
    }
}
