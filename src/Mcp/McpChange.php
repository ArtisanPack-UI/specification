<?php

/**
 * Upstream change entry.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Mcp;

use ArtisanPackUI\Specification\Enums\ChangeType;
use ArtisanPackUI\Specification\Exceptions\McpRequestException;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Support\Arrayable;
use Throwable;

/**
 * One entry from the upstream change log, as returned by `get_changes`.
 *
 * @implements Arrayable<string, mixed>
 *
 * @since 1.0.0
 */
final class McpChange implements Arrayable
{
    /**
     * Creates a new change.
     *
     * @since 1.0.0
     *
     * @param  CarbonImmutable          $date             The day of the change.
     * @param  ChangeType               $type             The kind of change.
     * @param  string                   $title            The headline.
     * @param  string                   $summary          A short description.
     * @param  array<int, McpTopic>     $topics           Current topics the change affects.
     * @param  array<int, string>       $unresolvedSlugs  Upstream slugs that are no longer live (e.g. removed topics).
     */
    public function __construct(
        public readonly CarbonImmutable $date,
        public readonly ChangeType $type,
        public readonly string $title,
        public readonly string $summary,
        public readonly array $topics = [],
        public readonly array $unresolvedSlugs = [],
    ) {
    }

    /**
     * Creates a change from an MCP structured-content item.
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
        $type = ChangeType::tryFrom( (string) ( $data['type'] ?? '' ) );

        if ( null === $type ) {
            throw new McpRequestException( __( 'The MCP server returned a change with an unknown type [:type].', [
                'type' => (string) ( $data['type'] ?? '' ),
            ] ) );
        }

        try {
            $date = CarbonImmutable::parse( (string) ( $data['date'] ?? '' ) )->startOfDay();
        } catch ( Throwable $exception ) {
            throw new McpRequestException( __( 'The MCP server returned a change without a valid date.' ), previous: $exception );
        }

        $topics = array_map(
            static fn ( mixed $topic ): McpTopic => McpTopic::fromArray( is_array( $topic ) ? $topic : [] ),
            is_array( $data['topics'] ?? null ) ? array_values( $data['topics'] ) : [],
        );

        return new self(
            $date,
            $type,
            (string) ( $data['title'] ?? '' ),
            (string) ( $data['summary'] ?? '' ),
            $topics,
            array_values( array_map( 'strval', is_array( $data['unresolvedSlugs'] ?? null ) ? $data['unresolvedSlugs'] : [] ) ),
        );
    }

    /**
     * Gets the local slugs of the topics this change affects.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    public function slugs(): array
    {
        return array_map( static fn ( McpTopic $topic ): string => $topic->slug, $this->topics );
    }

    /**
     * Gets the change as an array.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'date'             => $this->date->toDateString(),
            'type'             => $this->type->value,
            'title'            => $this->title,
            'summary'          => $this->summary,
            'topics'           => array_map( static fn ( McpTopic $topic ): array => $topic->toArray(), $this->topics ),
            'unresolved_slugs' => $this->unresolvedSlugs,
        ];
    }
}
