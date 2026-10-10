<?php

/**
 * Search hit.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Search;

use ArtisanPackUI\Specification\Enums\SpecStatus;
use Illuminate\Contracts\Support\Arrayable;

/**
 * One search result.
 *
 * Carries the topic's canonical URL so any UI that shows the summary or
 * snippet can link to the upstream source. The title, summary, and snippet
 * are raw third-party text (the snippet is unrendered Markdown); escape them
 * when outputting HTML, e.g. with Blade's {{ }}.
 *
 * @implements Arrayable<string, mixed>
 *
 * @since 1.0.0
 */
final class SearchHit implements Arrayable
{
    /**
     * Creates a new hit.
     *
     * @since 1.0.0
     *
     * @param  string      $slug         The topic slug.
     * @param  string      $category     The category.
     * @param  SpecStatus  $status       The status tier.
     * @param  string      $title        The title.
     * @param  string      $summary      The summary.
     * @param  string      $snippet      A body excerpt around the match, with matches wrapped in [[ ]].
     * @param  string      $resourceUrl  The canonical specification.website URL.
     * @param  float       $score        Relevance; lower is more relevant (FTS5 bm25).
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $category,
        public readonly SpecStatus $status,
        public readonly string $title,
        public readonly string $summary,
        public readonly string $snippet,
        public readonly string $resourceUrl,
        public readonly float $score,
    ) {
    }

    /**
     * Gets the hit as an array.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug'         => $this->slug,
            'category'     => $this->category,
            'status'       => $this->status->value,
            'title'        => $this->title,
            'summary'      => $this->summary,
            'snippet'      => $this->snippet,
            'resource_url' => $this->resourceUrl,
            'score'        => $this->score,
        ];
    }
}
