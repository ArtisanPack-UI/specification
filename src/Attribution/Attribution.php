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
     * Longest canonical URL accepted (the `resource_url` column length).
     *
     * @var int
     */
    public const MAX_URL_LENGTH = 512;

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
     * Gets a topic's canonical upstream URL.
     *
     * Returns the given URL only when it is an absolute https URL on the
     * upstream origin (and fits the `resource_url` column); otherwise the URL
     * is derived from the slug. Attribution links can therefore never point
     * anywhere other than specification.website.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  The topic slug, e.g. `foundations/doctype`.
     * @param  mixed   $url   The candidate URL.
     *
     * @return string
     */
    public static function canonicalUrl( string $slug, mixed $url ): string
    {
        $prefix = self::SOURCE_URL . '/';

        if (
            is_string( $url )
            && strlen( $url ) <= self::MAX_URL_LENGTH
            && str_starts_with( $url, $prefix )
            && false !== filter_var( $url, FILTER_VALIDATE_URL )
            && 'specification.website' === strtolower( (string) parse_url( $url, PHP_URL_HOST ) )
        ) {
            return $url;
        }

        return $prefix . 'spec/' . $slug . '/';
    }

    /**
     * Determines whether a URL is an absolute http(s) URL.
     *
     * @since 1.0.0
     *
     * @param  string  $url  The URL.
     *
     * @return bool
     */
    public static function isHttpUrl( string $url ): bool
    {
        return false !== filter_var( $url, FILTER_VALIDATE_URL )
            && in_array( strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) ), [ 'http', 'https' ], true );
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
            'title'   => '“' . $this->markdownLink( $this->url, $this->title ) . '”',
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
     * Only http(s) URLs are linked; anything else renders as plain text so
     * schemes such as `javascript:` can never reach an href.
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
        if ( ! self::isHttpUrl( $href ) ) {
            return e( $text );
        }

        $relAttribute = null === $rel ? '' : ' rel="' . e( $rel ) . '"';

        return '<a href="' . e( $href ) . '"' . $relAttribute . '>' . e( $text ) . '</a>';
    }

    /**
     * Builds a Markdown link, or escaped text when the URL is not http(s).
     *
     * @since 1.0.0
     *
     * @param  string  $href  The URL.
     * @param  string  $text  The link text.
     *
     * @return string
     */
    private function markdownLink( string $href, string $text ): string
    {
        $text = $this->escapeMarkdown( $text );

        if ( ! self::isHttpUrl( $href ) ) {
            return $text;
        }

        return '[' . $text . '](<' . (string) preg_replace( '/[<>\s]/', '', $href ) . '>)';
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
