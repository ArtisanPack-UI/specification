<?php

/**
 * CC BY 4.0 attribution.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Attribution;

use ArtisanPackUI\Specification\Models\Topic;
use Illuminate\Contracts\Support\Arrayable;

/**
 * The CC BY 4.0 attribution for one specification.website topic.
 *
 * Covers the elements CC BY 4.0 requires: the title, the creator, a link to
 * the source, and the license with a link to it.
 *
 * @implements Arrayable<string, string>
 *
 * @since 1.0.0
 */
final class Attribution implements Arrayable
{
    /**
     * The upstream source name.
     *
     * @var string
     */
    public const SOURCE_NAME = 'The Website Specification';

    /**
     * The upstream origin (no trailing slash).
     *
     * @var string
     */
    public const SOURCE_URL = 'https://specification.website';

    /**
     * The license name.
     *
     * @var string
     */
    public const LICENSE_NAME = 'CC BY 4.0';

    /**
     * The license URL.
     *
     * @var string
     */
    public const LICENSE_URL = 'https://creativecommons.org/licenses/by/4.0/';

    /**
     * Creates a new attribution.
     *
     * @since 1.0.0
     *
     * @param  string  $title  The topic title.
     * @param  string  $url    The topic's canonical upstream URL.
     */
    public function __construct(
        public readonly string $title,
        public readonly string $url,
    ) {
    }

    /**
     * Creates the attribution for a topic.
     *
     * @since 1.0.0
     *
     * @param  Topic  $topic  The topic.
     *
     * @return self
     */
    public static function forTopic( Topic $topic ): self
    {
        return new self( $topic->title, $topic->resource_url );
    }

    /**
     * Gets the untranslated attribution stored alongside each topic row.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function canonical(): string
    {
        return sprintf(
            '"%s" (%s) from %s (%s), licensed under %s (%s).',
            $this->title,
            $this->url,
            self::SOURCE_NAME,
            self::SOURCE_URL,
            self::LICENSE_NAME,
            self::LICENSE_URL,
        );
    }

    /**
     * Gets the translated plain-text attribution.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function toText(): string
    {
        return __( '":title" (:url) from :source (:source_url), licensed under :license (:license_url).', [
            'title'       => $this->title,
            'url'         => $this->url,
            'source'      => self::SOURCE_NAME,
            'source_url'  => self::SOURCE_URL,
            'license'     => self::LICENSE_NAME,
            'license_url' => self::LICENSE_URL,
        ] );
    }

    /**
     * Gets the translated attribution as escaped HTML.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function toHtml(): string
    {
        $sentence = __( ':title from :source, licensed under :license.', [
            'title'   => '“' . $this->link( $this->url, $this->title ) . '”',
            'source'  => $this->link( self::SOURCE_URL, self::SOURCE_NAME ),
            'license' => $this->link( self::LICENSE_URL, self::LICENSE_NAME, 'license' ),
        ] );

        return '<p class="spec-attribution">' . $sentence . '</p>';
    }

    /**
     * Gets the translated attribution as Markdown.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function toMarkdown(): string
    {
        return __( ':title from :source, licensed under :license.', [
            'title'   => '“[' . $this->escapeMarkdown( $this->title ) . '](<' . $this->url . '>)”',
            'source'  => '[' . self::SOURCE_NAME . '](<' . self::SOURCE_URL . '>)',
            'license' => '[' . self::LICENSE_NAME . '](<' . self::LICENSE_URL . '>)',
        ] );
    }

    /**
     * Gets the attribution as an array.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    public function toArray(): array
    {
        return [
            'title'       => $this->title,
            'url'         => $this->url,
            'source'      => self::SOURCE_NAME,
            'source_url'  => self::SOURCE_URL,
            'license'     => self::LICENSE_NAME,
            'license_url' => self::LICENSE_URL,
            'text'        => $this->toText(),
        ];
    }

    /**
     * Builds an escaped anchor.
     *
     * @since 1.0.0
     *
     * @param  string       $href  The URL.
     * @param  string       $text  The link text.
     * @param  string|null  $rel   An optional rel value.
     *
     * @return string
     */
    private function link( string $href, string $text, ?string $rel = null ): string
    {
        $relAttribute = null === $rel ? '' : ' rel="' . e( $rel ) . '"';

        return '<a href="' . e( $href ) . '"' . $relAttribute . '>' . e( $text ) . '</a>';
    }

    /**
     * Escapes Markdown link-text metacharacters.
     *
     * @since 1.0.0
     *
     * @param  string  $text  The text.
     *
     * @return string
     */
    private function escapeMarkdown( string $text ): string
    {
        return addcslashes( $text, '\\[]()*_`<>' );
    }
}
