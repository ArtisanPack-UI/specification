<?php

declare( strict_types=1 );

namespace Tests\Support;

use ArtisanPackUI\Specification\Contracts\McpClient;
use ArtisanPackUI\Specification\Enums\ChangeType;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use ArtisanPackUI\Specification\Exceptions\McpRequestException;
use ArtisanPackUI\Specification\Exceptions\McpUnavailableException;
use ArtisanPackUI\Specification\Mcp\McpChangeSet;
use ArtisanPackUI\Specification\Mcp\McpTopic;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Throwable;

/**
 * In-memory McpClient that records calls, for tests that must not touch the network.
 */
final class FakeMcpClient implements McpClient
{
    /**
     * Recorded calls as [method, arguments].
     *
     * @var array<int, array{0: string, 1: array<string, mixed>}>
     */
    public array $calls = [];

    /**
     * @param  array<int, McpTopic>  $topics   Topics served by listTopics, getTopic, and search.
     * @param  McpChangeSet|null     $changes  The change set served by getChanges.
     * @param  Throwable|null        $failure  Thrown by every call when set.
     */
    public function __construct(
        private bool $enabled = true,
        private array $topics = [],
        private ?McpChangeSet $changes = null,
        private ?Throwable $failure = null,
    ) {
    }

    public function isEnabled(): bool
    {
        return $this->enabled;
    }

    public function listTopics( ?string $category = null, ?SpecStatus $status = null, ?int $limit = null ): array
    {
        $this->record( 'listTopics', compact( 'category', 'status', 'limit' ) );

        $topics = array_values( array_filter( $this->topics, static fn ( McpTopic $topic ): bool => ( null === $category || $topic->category === $category )
            && ( null === $status || $topic->status === $status ) ) );

        return null === $limit ? $topics : array_slice( $topics, 0, $limit );
    }

    public function getTopic( string $slug ): McpTopic
    {
        $this->record( 'getTopic', compact( 'slug' ) );

        foreach ( $this->topics as $topic ) {
            if ( $topic->slug === $slug || str_ends_with( $topic->slug, '/' . $slug ) ) {
                return $topic;
            }
        }

        throw new McpRequestException( 'No spec page with slug "' . $slug . '".' );
    }

    public function getChanges( DateTimeInterface|string|null $since = null, ?ChangeType $type = null, ?int $limit = null ): McpChangeSet
    {
        $since = $since instanceof DateTimeInterface ? CarbonImmutable::instance( $since )->toDateString() : $since;

        $this->record( 'getChanges', compact( 'since', 'type', 'limit' ) );

        return $this->changes ?? new McpChangeSet( $since, null );
    }

    public function search( string $query, int $limit = 5 ): array
    {
        $this->record( 'search', compact( 'query', 'limit' ) );

        return array_slice( array_values( array_filter( $this->topics, static fn ( McpTopic $topic ): bool => str_contains( strtolower( $topic->title . ' ' . $topic->summary ), strtolower( $query ) ) ) ), 0, $limit );
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function record( string $method, array $arguments ): void
    {
        if ( ! $this->enabled ) {
            throw new McpUnavailableException( 'The specification.website MCP client is disabled.' );
        }

        $this->calls[] = [ $method, $arguments ];

        if ( null !== $this->failure ) {
            throw $this->failure;
        }
    }
}
