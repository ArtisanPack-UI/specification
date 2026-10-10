<?php

/**
 * MCP client contract.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Contracts;

use ArtisanPackUI\Specification\Enums\ChangeType;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use ArtisanPackUI\Specification\Exceptions\McpRequestException;
use ArtisanPackUI\Specification\Exceptions\McpUnavailableException;
use ArtisanPackUI\Specification\Mcp\McpChangeSet;
use ArtisanPackUI\Specification\Mcp\McpTopic;
use DateTimeInterface;

/**
 * A client for the specification.website MCP server.
 *
 * The MCP server is an optional secondary source used for freshness; the OKF
 * snapshot stays authoritative. Every method except isEnabled() throws
 * McpUnavailableException when the client is disabled by configuration, and
 * McpRequestException when the request or its response is invalid.
 *
 * @since 1.0.0
 */
interface McpClient
{
    /**
     * Determines whether the client is enabled.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function isEnabled(): bool;

    /**
     * Lists topics, optionally filtered, in canonical spec order.
     *
     * @since 1.0.0
     *
     * @param  string|null      $category  Limit to a category.
     * @param  SpecStatus|null  $status    Limit to a status tier.
     * @param  int|null         $limit     Maximum topics (1–200); null for all.
     *
     * @throws McpUnavailableException When the client is disabled.
     * @throws McpRequestException     When the request fails.
     *
     * @return array<int, McpTopic>
     */
    public function listTopics( ?string $category = null, ?SpecStatus $status = null, ?int $limit = null ): array;

    /**
     * Fetches one topic, including its full Markdown.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  The local (`foundations/doctype`) or upstream (`doctype`) slug.
     *
     * @throws McpUnavailableException When the client is disabled.
     * @throws McpRequestException     When the request fails or the topic does not exist.
     *
     * @return McpTopic
     */
    public function getTopic( string $slug ): McpTopic;

    /**
     * Gets upstream change-log entries, newest first.
     *
     * @since 1.0.0
     *
     * @param  DateTimeInterface|string|null  $since  Only changes on or after this day.
     * @param  ChangeType|null                $type   Limit to one kind of change.
     * @param  int|null                       $limit  Maximum entries (1–100).
     *
     * @throws McpUnavailableException When the client is disabled.
     * @throws McpRequestException     When the request fails.
     *
     * @return McpChangeSet
     */
    public function getChanges( DateTimeInterface|string|null $since = null, ?ChangeType $type = null, ?int $limit = null ): McpChangeSet;

    /**
     * Searches the upstream spec, most relevant first.
     *
     * @since 1.0.0
     *
     * @param  string  $query  Free-text query.
     * @param  int     $limit  Maximum results (1–25).
     *
     * @throws McpUnavailableException When the client is disabled.
     * @throws McpRequestException     When the request fails.
     *
     * @return array<int, McpTopic>
     */
    public function search( string $query, int $limit = 5 ): array;
}
