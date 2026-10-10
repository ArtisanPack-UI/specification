<?php

/**
 * Upstream change set.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Mcp;

use ArtisanPackUI\Specification\Exceptions\McpRequestException;
use Illuminate\Contracts\Support\Arrayable;

/**
 * The result of a `get_changes` call.
 *
 * @implements Arrayable<string, mixed>
 *
 * @since 1.0.0
 */
final class McpChangeSet implements Arrayable
{
    /**
     * Creates a new change set.
     *
     * @since 1.0.0
     *
     * @param  string|null             $since    The `since` day that was applied (YYYY-MM-DD), or null.
     * @param  string|null             $latest   The day of the most recent change in the whole spec, or null.
     * @param  array<int, McpChange>   $changes  Matching changes, newest first.
     */
    public function __construct(
        public readonly ?string $since,
        public readonly ?string $latest,
        public readonly array $changes = [],
    ) {
    }

    /**
     * Creates a change set from MCP structured content.
     *
     * @since 1.0.0
     *
     * @param  array<string, mixed>  $data  The structured content.
     *
     * @throws McpRequestException When the content is malformed.
     *
     * @return self
     */
    public static function fromArray( array $data ): self
    {
        if ( ! is_array( $data['changes'] ?? null ) ) {
            throw new McpRequestException( __( 'The MCP server returned changes without a [changes] list.' ) );
        }

        return new self(
            is_string( $data['since'] ?? null ) ? $data['since'] : null,
            is_string( $data['latest'] ?? null ) ? $data['latest'] : null,
            array_map(
                static fn ( mixed $change ): McpChange => McpChange::fromArray( is_array( $change ) ? $change : [] ),
                array_values( $data['changes'] ),
            ),
        );
    }

    /**
     * Determines whether the set contains no changes.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function isEmpty(): bool
    {
        return [] === $this->changes;
    }

    /**
     * Gets the unique, sorted local slugs affected by any change.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    public function slugs(): array
    {
        $slugs = array_values( array_unique( array_merge( [], ...array_map( static fn ( McpChange $change ): array => $change->slugs(), $this->changes ) ) ) );
        sort( $slugs );

        return $slugs;
    }

    /**
     * Gets the change set as an array.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'since'   => $this->since,
            'latest'  => $this->latest,
            'changes' => array_map( static fn ( McpChange $change ): array => $change->toArray(), $this->changes ),
        ];
    }
}
