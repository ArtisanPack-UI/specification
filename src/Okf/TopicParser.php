<?php

/**
 * OKF topic parser.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Okf;

use ArtisanPackUI\Specification\Attribution\Attribution;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use ArtisanPackUI\Specification\Exceptions\InvalidArchiveException;
use Carbon\CarbonImmutable;
use Symfony\Component\Yaml\Exception\ParseException;
use Symfony\Component\Yaml\Yaml;
use Throwable;

/**
 * Parses OKF Markdown documents with YAML front matter.
 *
 * @since 1.0.0
 */
final class TopicParser
{
    /**
     * Front-matter keys every Check topic must provide.
     *
     * @var array<int, string>
     */
    private const REQUIRED_KEYS = [ 'title', 'description', 'requirement' ];

    /**
     * Source fields kept from each front-matter `sources` entry.
     *
     * @var array<int, string>
     */
    private const SOURCE_KEYS = [ 'id', 'title', 'resource', 'author', 'mirror' ];

    /**
     * RFC 2119 keywords accepted as a topic's conformance.
     *
     * @var array<int, string>
     */
    private const CONFORMANCE_KEYWORDS = [ 'MUST', 'MUST NOT', 'SHOULD', 'SHOULD NOT', 'MAY' ];

    /**
     * Longest slug and title accepted (the `slug` and `title` column lengths).
     *
     * @var int
     */
    private const MAX_STRING_LENGTH = 255;

    /**
     * Splits a document into its front matter and body.
     *
     * @since 1.0.0
     *
     * @param  string  $contents  The document.
     *
     * @throws InvalidArchiveException When the front matter is missing or not valid YAML.
     *
     * @return array{0: array<string, mixed>, 1: string}
     */
    public function frontMatter( string $contents ): array
    {
        if ( 1 !== preg_match( '/\A\x{FEFF}?---\r?\n(.*?)\r?\n---\r?\n?(.*)\z/su', $contents, $matches ) ) {
            throw new InvalidArchiveException( __( 'The document has no YAML front matter.' ) );
        }

        try {
            $meta = Yaml::parse( $matches[1] );
        } catch ( ParseException $exception ) {
            throw new InvalidArchiveException( __( 'The document front matter is not valid YAML: :message', [
                'message' => $exception->getMessage(),
            ] ), previous: $exception );
        }

        if ( ! is_array( $meta ) ) {
            throw new InvalidArchiveException( __( 'The document front matter must be a mapping.' ) );
        }

        return [ $meta, trim( $matches[2] ) ];
    }

    /**
     * Parses a topic document.
     *
     * The slug is derived from the archive path (`okf/{category}/{topic}.md`),
     * never from front-matter values.
     *
     * @since 1.0.0
     *
     * @param  string  $category  The category directory name.
     * @param  string  $topic     The topic file name without extension.
     * @param  string  $contents  The document.
     *
     * @throws InvalidArchiveException When the document is not a valid Check topic.
     *
     * @return OkfTopic|null Null when the document is not a `type: Check` topic.
     */
    public function parse( string $category, string $topic, string $contents ): ?OkfTopic
    {
        [ $meta, $body ] = $this->frontMatter( $contents );

        if ( 'Check' !== ( $meta['type'] ?? null ) ) {
            return null;
        }

        foreach ( self::REQUIRED_KEYS as $key ) {
            if ( ! is_string( $meta[ $key ] ?? null ) || '' === trim( $meta[ $key ] ) ) {
                throw new InvalidArchiveException( __( 'Topic [:slug] is missing the [:key] front-matter key.', [
                    'slug' => $category . '/' . $topic,
                    'key'  => $key,
                ] ) );
            }
        }

        $status = SpecStatus::tryFrom( strtolower( $meta['requirement'] ) );

        if ( null === $status ) {
            throw new InvalidArchiveException( __( 'Topic [:slug] has an unknown requirement [:requirement].', [
                'slug'        => $category . '/' . $topic,
                'requirement' => $meta['requirement'],
            ] ) );
        }

        $slug  = $category . '/' . $topic;
        $title = trim( $meta['title'] );

        if ( strlen( $slug ) > self::MAX_STRING_LENGTH || mb_strlen( $title ) > self::MAX_STRING_LENGTH ) {
            throw new InvalidArchiveException( __( 'Topic [:slug] has a slug or title longer than :max characters.', [
                'slug' => mb_substr( $slug, 0, 64 ),
                'max'  => self::MAX_STRING_LENGTH,
            ] ) );
        }

        return new OkfTopic(
            $slug,
            $category,
            $status,
            $this->conformance( $meta['conformance'] ?? null ),
            $title,
            trim( $meta['description'] ),
            $body,
            $this->sources( $meta['sources'] ?? [] ),
            Attribution::canonicalUrl( $slug, $meta['resource'] ?? null ),
            $this->timestamp( $meta['source_updated_at'] ?? null ),
        );
    }

    /**
     * Normalizes the `sources` front-matter list to known string fields.
     *
     * @since 1.0.0
     *
     * @param  mixed  $sources  The raw value.
     *
     * @return array<int, array<string, string>>
     */
    private function sources( mixed $sources ): array
    {
        if ( ! is_array( $sources ) ) {
            return [];
        }

        $normalized = [];

        foreach ( $sources as $source ) {
            if ( ! is_array( $source ) ) {
                continue;
            }

            $entry = [];

            foreach ( self::SOURCE_KEYS as $key ) {
                if ( is_scalar( $source[ $key ] ?? null ) ) {
                    $entry[ $key ] = (string) $source[ $key ];
                }
            }

            if ( isset( $entry['resource'] ) && ! Attribution::isHttpUrl( $entry['resource'] ) ) {
                unset( $entry['resource'] );
            }

            if ( [] !== $entry ) {
                $normalized[] = $entry;
            }
        }

        return $normalized;
    }

    /**
     * Normalizes the RFC 2119 conformance keyword.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  The raw value.
     *
     * @return string|null Null when the value is not a known keyword.
     */
    private function conformance( mixed $value ): ?string
    {
        if ( ! is_string( $value ) ) {
            return null;
        }

        $keyword = strtoupper( (string) preg_replace( '/\s+/', ' ', trim( $value ) ) );

        return in_array( $keyword, self::CONFORMANCE_KEYWORDS, true ) ? $keyword : null;
    }

    /**
     * Parses a timestamp value.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  The raw value.
     *
     * @return CarbonImmutable|null
     */
    private function timestamp( mixed $value ): ?CarbonImmutable
    {
        if ( is_int( $value ) ) {
            return CarbonImmutable::createFromTimestampUTC( $value );
        }

        if ( ! is_string( $value ) || '' === $value ) {
            return null;
        }

        try {
            return CarbonImmutable::parse( $value )->utc();
        } catch ( Throwable ) {
            return null;
        }
    }
}
