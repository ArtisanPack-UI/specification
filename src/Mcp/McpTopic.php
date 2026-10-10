<?php

/**
 * Topic returned by the MCP server.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Mcp;

use ArtisanPackUI\Specification\Attribution\Attribution;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use ArtisanPackUI\Specification\Exceptions\McpRequestException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Throwable;

/**
 * One topic as reported by the specification.website MCP server.
 *
 * The upstream server identifies topics by a bare slug (`doctype`); the slug
 * here is normalized to the local `category/topic` form (`foundations/doctype`)
 * so it can be compared with Topic rows and registered checks. The summary and
 * Markdown are CC BY 4.0 text: display them alongside attribution().
 *
 * @implements Arrayable<string, mixed>
 *
 * @since 1.0.0
 */
final class McpTopic implements Arrayable
{
    /**
     * Creates a new topic.
     *
     * @since 1.0.0
     *
     * @param  string                $slug       The local slug, e.g. `foundations/doctype`.
     * @param  string                $category   The category.
     * @param  SpecStatus            $status     The status tier.
     * @param  string                $title      The title.
     * @param  string                $summary    The one-sentence summary.
     * @param  string                $url        The canonical specification.website URL.
     * @param  string|null           $markdown   The full Markdown page (only from get_topic).
     * @param  CarbonImmutable|null  $updatedAt  When the topic was last updated upstream (only from get_topic).
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $category,
        public readonly SpecStatus $status,
        public readonly string $title,
        public readonly string $summary,
        public readonly string $url,
        public readonly ?string $markdown = null,
        public readonly ?CarbonImmutable $updatedAt = null,
    ) {
    }

    /**
     * Creates a topic from an MCP structured-content item.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data  The item.
     *
     * @throws McpRequestException When a required field is missing or invalid.
     *
     * @return self
     */
    public static function fromArray( array $data ): self
    {
        $slug     = self::string( $data, 'slug' );
        $category = self::string( $data, 'category' );
        $status   = SpecStatus::tryFrom( self::string( $data, 'status' ) );

        if ( null === $status ) {
            throw new McpRequestException( __( 'The MCP server returned an unknown status [:status] for [:slug].', [
                'status' => (string) $data['status'],
                'slug'   => $slug,
            ] ) );
        }

        return new self(
            self::localSlug( $category, $slug ),
            $category,
            $status,
            self::string( $data, 'title' ),
            is_string( $data['summary'] ?? null ) ? $data['summary'] : '',
            self::string( $data, 'url' ),
            is_string( $data['markdown'] ?? null ) ? $data['markdown'] : null,
            self::date( $data['updated'] ?? null ),
        );
    }

    /**
     * Converts an upstream bare slug to the local `category/topic` form.
     *
     * @since 1.0.0
     *
     * @param  string  $category  The category.
     * @param  string  $slug      The upstream slug.
     *
     * @return string
     */
    public static function localSlug( string $category, string $slug ): string
    {
        return str_contains( $slug, '/' ) ? $slug : $category . '/' . $slug;
    }

    /**
     * Gets the CC BY 4.0 attribution for the topic.
     *
     * @since 1.0.0
     *
     * @return Attribution
     */
    public function attribution(): Attribution
    {
        return new Attribution( $this->title, $this->url );
    }

    /**
     * Gets the topic as an array.
     *
     * The Markdown body is left out so topic text cannot be serialized away
     * from its attribution; the attribution is always included.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug'        => $this->slug,
            'category'    => $this->category,
            'status'      => $this->status->value,
            'title'       => $this->title,
            'summary'     => $this->summary,
            'url'         => $this->url,
            'updated_at'  => $this->updatedAt?->toIso8601String(),
            'attribution' => $this->attribution()->toArray(),
        ];
    }

    /**
     * Reads a required, non-empty string field.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data  The item.
     * @param  string                $key   The field.
     *
     * @throws McpRequestException When the field is missing or not a non-empty string.
     *
     * @return string
     */
    private static function string( array $data, string $key ): string
    {
        $value = $data[ $key ] ?? null;

        if ( ! is_string( $value ) || '' === trim( $value ) ) {
            throw new McpRequestException( __( 'The MCP server returned a topic without a valid [:field] field.', [ 'field' => $key ] ) );
        }

        return $value;
    }

    /**
     * Parses an optional ISO 8601 date.
     *
     * @since 1.0.0
     *
     * @param  mixed  $value  The raw value.
     *
     * @return CarbonImmutable|null
     */
    private static function date( mixed $value ): ?CarbonImmutable
    {
        if ( ! is_string( $value ) || '' === $value ) {
            return null;
        }

        try {
            return CarbonImmutable::parse( $value );
        } catch ( Throwable ) {
            return null;
        }
    }
}
