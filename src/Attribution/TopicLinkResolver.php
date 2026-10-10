<?php

/**
 * Topic link resolver.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Attribution;

use ArtisanPackUI\Specification\Models\Topic;

/**
 * Resolves the relative links in a topic body to absolute URLs.
 *
 * OKF topic bodies link relative to the bundle (`../references/{id}.md`,
 * `hsts.md`) and to the upstream site (`/spec/security/hsts/`). Rendered in
 * a host application those would resolve against the host, so every link is
 * made absolute: references point at the cited source's own URL, sibling
 * topics and root-relative paths point at specification.website. Links that
 * cannot be resolved, or use a scheme other than http(s) or mailto, resolve
 * to null so the renderer can drop the link and keep its text.
 *
 * @since 1.0.0
 */
final class TopicLinkResolver
{
    /**
     * Schemes that are kept as-is.
     *
     * @var array<int, string>
     */
    private const SAFE_SCHEMES = [ 'http', 'https', 'mailto' ];

    /**
     * Source URLs keyed by source id.
     *
     * @var array<string, string>
     */
    private readonly array $references;

    /**
     * Creates a new resolver.
     *
     * @since 1.0.0
     *
     * @param  string                             $category     The topic's category.
     * @param  string                             $resourceUrl  The topic's canonical upstream URL.
     * @param  array<int, array<string, string>>  $sources      The topic's normalized sources.
     */
    public function __construct(
        private readonly string $category,
        private readonly string $resourceUrl,
        array $sources = [],
    ) {
        $references = [];

        foreach ( $sources as $source ) {
            if ( ! isset( $source['resource'] ) || ! Attribution::isHttpUrl( $source['resource'] ) ) {
                continue;
            }

            if ( isset( $source['id'] ) ) {
                $references[ $source['id'] ] = $source['resource'];
            }

            if ( isset( $source['mirror'] ) && 1 === preg_match( '#(?:^|/)references/([a-z0-9]+(?:-[a-z0-9]+)*)\.md$#', $source['mirror'], $matches ) ) {
                $references[ $matches[1] ] ??= $source['resource'];
            }
        }

        $this->references = $references;
    }

    /**
     * Creates the resolver for a topic.
     *
     * @since 1.0.0
     *
     * @param  Topic  $topic  The topic.
     *
     * @return self
     */
    public static function forTopic( Topic $topic ): self
    {
        return new self( $topic->category, $topic->resource_url, (array) $topic->sources_json );
    }

    /**
     * Resolves a link destination.
     *
     * @since 1.0.0
     *
     * @param  string  $url  The destination as written in the topic body.
     *
     * @return string|null The absolute URL, or null when the link should be dropped.
     */
    public function resolve( string $url ): ?string
    {
        $url = trim( $url );

        if ( '' === $url ) {
            return null;
        }

        if ( str_starts_with( $url, '#' ) ) {
            return $url;
        }

        if ( str_starts_with( $url, '//' ) ) {
            return $this->safe( 'https:' . $url );
        }

        $scheme = parse_url( $url, PHP_URL_SCHEME );

        if ( is_string( $scheme ) && '' !== $scheme ) {
            return $this->safe( $url );
        }

        [ $path, $fragment ] = $this->splitFragment( $url );

        if ( 1 === preg_match( '#(?:^|/)references/([a-z0-9]+(?:-[a-z0-9]+)*)\.md$#', $path, $matches ) ) {
            return isset( $this->references[ $matches[1] ] ) ? $this->references[ $matches[1] ] . $fragment : null;
        }

        if ( str_starts_with( $path, '/' ) ) {
            return $this->safe( Attribution::SOURCE_URL . $path . $fragment );
        }

        if ( str_ends_with( $path, '.md' ) ) {
            $resolved = $this->normalize( 'okf/' . $this->category . '/' . $path );

            if ( null !== $resolved && 1 === preg_match( '#^okf/([a-z0-9]+(?:-[a-z0-9]+)*)/([a-z0-9]+(?:-[a-z0-9]+)*)\.md$#', $resolved, $matches ) && 'references' !== $matches[1] && 'index' !== $matches[2] ) {
                return Attribution::SOURCE_URL . '/spec/' . $matches[1] . '/' . $matches[2] . '/' . $fragment;
            }

            return null;
        }

        $basePath = (string) parse_url( $this->resourceUrl, PHP_URL_PATH );
        $resolved = $this->normalize( substr( $basePath, 0, (int) strrpos( $basePath, '/' ) + 1 ) . $path );

        return null === $resolved ? null : $this->safe( Attribution::SOURCE_URL . '/' . ltrim( $resolved, '/' ) . $fragment );
    }

    /**
     * Rewrites the link and image destinations in Markdown source.
     *
     * Inline links (`[text](url)`, `![alt](url)`) and link reference
     * definitions are rewritten; fenced code blocks and inline code spans are
     * left untouched. A link that cannot be resolved is replaced by its text.
     *
     * @since 1.0.0
     *
     * @param  string  $markdown  The Markdown source.
     *
     * @return string
     */
    public function rewriteMarkdown( string $markdown ): string
    {
        $lines = preg_split( '/(\r?\n)/', $markdown, -1, PREG_SPLIT_DELIM_CAPTURE ) ?: [];
        $fence = null;

        foreach ( $lines as $index => $line ) {
            if ( 1 === $index % 2 ) {
                continue;
            }

            if ( 1 === preg_match( '/^ {0,3}(`{3,}|~{3,})/', $line, $matches ) ) {
                if ( null === $fence ) {
                    $fence = $matches[1];
                } elseif ( $matches[1][0] === $fence[0] && strlen( $matches[1] ) >= strlen( $fence ) ) {
                    $fence = null;
                }

                continue;
            }

            if ( null !== $fence || 1 === preg_match( '/^(?: {4}|\t)/', $line ) ) {
                continue;
            }

            $lines[ $index ] = $this->rewriteMarkdownLine( $line );
        }

        return implode( '', $lines );
    }

    /**
     * Rewrites the links on one Markdown line, skipping inline code spans.
     *
     * @since 1.0.0
     *
     * @param  string  $line  The line.
     *
     * @return string
     */
    private function rewriteMarkdownLine( string $line ): string
    {
        if ( 1 === preg_match( '/^( {0,3}\[[^\]]+\]:\s*)<?([^\s>]+)>?(.*)$/', $line, $matches ) ) {
            $resolved = $this->resolve( $matches[2] );

            return null === $resolved ? '' : $matches[1] . '<' . $resolved . '>' . $matches[3];
        }

        // Code spans are matched first and returned untouched; a link whose
        // text contains a code span starts at its `[` and so still matches.
        return (string) preg_replace_callback(
            '/(?<code>(?<ticks>`+)(?:(?!\k<ticks>).)+?\k<ticks>)|(?<bang>!?)\[(?<text>(?:[^\[\]]|\[[^\[\]]*\])*)\]\(\s*<?(?<url>(?:[^\s()<>]|\([^\s()]*\))*)>?(?<title>(?:\s+"[^"]*")?)\s*\)/',
            function ( array $match ): string {
                if ( '' !== ( $match['code'] ?? '' ) ) {
                    return $match[0];
                }

                $resolved = $this->resolve( $match['url'] );

                if ( null === $resolved ) {
                    return $match['text'];
                }

                return $match['bang'] . '[' . $match['text'] . '](<' . $resolved . '>' . $match['title'] . ')';
            },
            $line,
        );
    }

    /**
     * Returns the URL when its scheme is allowed.
     *
     * @since 1.0.0
     *
     * @param  string  $url  The absolute URL.
     *
     * @return string|null
     */
    private function safe( string $url ): ?string
    {
        $scheme = strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) );

        if ( ! in_array( $scheme, self::SAFE_SCHEMES, true ) ) {
            return null;
        }

        return 'mailto' === $scheme || Attribution::isHttpUrl( $url ) ? $url : null;
    }

    /**
     * Splits a relative URL into its path (with query) and fragment.
     *
     * @since 1.0.0
     *
     * @param  string  $url  The relative URL.
     *
     * @return array{0: string, 1: string}
     */
    private function splitFragment( string $url ): array
    {
        $position = strpos( $url, '#' );

        return false === $position ? [ $url, '' ] : [ substr( $url, 0, $position ), substr( $url, $position ) ];
    }

    /**
     * Collapses `.` and `..` segments in a path.
     *
     * @since 1.0.0
     *
     * @param  string  $path  The path.
     *
     * @return string|null Null when the path climbs above its root.
     */
    private function normalize( string $path ): ?string
    {
        $segments = [];

        foreach ( explode( '/', $path ) as $segment ) {
            if ( '.' === $segment || ( '' === $segment && [] !== $segments ) ) {
                continue;
            }

            if ( '..' === $segment ) {
                if ( [] === $segments ) {
                    return null;
                }

                array_pop( $segments );

                continue;
            }

            $segments[] = $segment;
        }

        $normalized = implode( '/', $segments );

        return str_ends_with( $path, '/' ) && ! str_ends_with( $normalized, '/' ) ? $normalized . '/' : $normalized;
    }
}
