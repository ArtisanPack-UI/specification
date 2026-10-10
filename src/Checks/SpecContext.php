<?php

/**
 * SpecCheck context.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Checks;

use ArtisanPackUI\Specification\Enums\CheckScope;

/**
 * The subject a SpecCheck evaluates.
 *
 * Carries the scope, the URL being checked, and optionally the fetched HTML,
 * response headers, and arbitrary data owner packages need. Section and Block
 * contexts carry the URL of the page they belong to so their results roll up
 * into that page's report.
 *
 * @since 1.0.0
 */
final class SpecContext
{
    /**
     * Response headers keyed by lower-cased header name.
     *
     * @var array<string, array<int, string>>
     */
    public readonly array $headers;

    /**
     * Creates a new context.
     *
     * @since 1.0.0
     *
     * @param  CheckScope                                $scope    The level being checked.
     * @param  string|null                               $url      The URL of the site or page.
     * @param  string|null                               $html     The fetched HTML, when available.
     * @param  array<string, array<int, string>|string>  $headers  Response headers.
     * @param  array<string, mixed>                      $data     Additional data for owner packages.
     */
    public function __construct(
        public readonly CheckScope $scope,
        public readonly ?string $url = null,
        public readonly ?string $html = null,
        array $headers = [],
        public readonly array $data = [],
    ) {
        $normalized = [];

        foreach ( $headers as $name => $value ) {
            $normalized[ strtolower( (string) $name ) ] = array_values( array_map( 'strval', (array) $value ) );
        }

        $this->headers = $normalized;
    }

    /**
     * Creates a site-level context.
     *
     * @since 1.0.0
     *
     * @param  string                $url   The site's base URL.
     * @param  array<string, mixed>  $data  Additional data.
     *
     * @return self
     */
    public static function site( string $url, array $data = [] ): self
    {
        return new self( CheckScope::Site, $url, null, [], $data );
    }

    /**
     * Creates a page-level context.
     *
     * @since 1.0.0
     *
     * @param  string                                    $url      The page URL.
     * @param  string|null                               $html     The page HTML.
     * @param  array<string, array<int, string>|string>  $headers  Response headers.
     * @param  array<string, mixed>                      $data     Additional data.
     *
     * @return self
     */
    public static function page( string $url, ?string $html = null, array $headers = [], array $data = [] ): self
    {
        return new self( CheckScope::Page, $url, $html, $headers, $data );
    }

    /**
     * Creates a server-level context.
     *
     * @since 1.0.0
     *
     * @param  string                                    $url      The origin being probed.
     * @param  array<string, array<int, string>|string>  $headers  Response headers.
     * @param  array<string, mixed>                      $data     Additional data.
     *
     * @return self
     */
    public static function server( string $url, array $headers = [], array $data = [] ): self
    {
        return new self( CheckScope::Server, $url, null, $headers, $data );
    }

    /**
     * Gets the first value of a response header, case-insensitively.
     *
     * @since 1.0.0
     *
     * @param  string  $name  The header name.
     *
     * @return string|null
     */
    public function header( string $name ): ?string
    {
        return $this->headers[ strtolower( $name ) ][0] ?? null;
    }

    /**
     * Gets a value from the additional data using dot notation.
     *
     * @since 1.0.0
     *
     * @param  string  $key      The key.
     * @param  mixed   $default  The default value.
     *
     * @return mixed
     */
    public function get( string $key, mixed $default = null ): mixed
    {
        return data_get( $this->data, $key, $default );
    }

    /**
     * Gets the identifier results are grouped under in reports.
     *
     * Uses the URL when present, then a `subject` data key, then the scope.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function subject(): string
    {
        return $this->url ?? (string) ( $this->data['subject'] ?? $this->scope->value );
    }
}
