<?php

/**
 * Search index contract.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Contracts;

use ArtisanPackUI\Specification\Enums\SpecStatus;
use ArtisanPackUI\Specification\Models\Topic;
use ArtisanPackUI\Specification\Search\SearchHit;

/**
 * A local full-text index over the specification topics.
 *
 * @since 1.0.0
 */
interface SearchIndex
{
    /**
     * Replaces the index contents with the given topics.
     *
     * @since 1.0.0
     *
     * @param  iterable<Topic>  $topics   The topics to index.
     * @param  string           $version  The snapshot version being indexed.
     *
     * @return void
     */
    public function rebuild( iterable $topics, string $version ): void;

    /**
     * Searches the index.
     *
     * @since 1.0.0
     *
     * @param  string           $query     Free-text query.
     * @param  int              $limit     Maximum hits (1–100).
     * @param  string|null      $category  Limit to a category.
     * @param  SpecStatus|null  $status    Limit to a status tier.
     *
     * @return array<int, SearchHit> Hits ordered by relevance.
     */
    public function search( string $query, int $limit = 10, ?string $category = null, ?SpecStatus $status = null ): array;

    /**
     * Gets the snapshot version currently indexed.
     *
     * @since 1.0.0
     *
     * @return string|null Null when the index has never been built.
     */
    public function version(): ?string;
}
