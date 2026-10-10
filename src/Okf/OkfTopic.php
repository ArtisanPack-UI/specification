<?php

/**
 * Parsed OKF topic.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Okf;

use ArtisanPackUI\Specification\Enums\SpecStatus;
use Carbon\CarbonImmutable;

/**
 * One `type: Check` topic parsed from an OKF bundle.
 *
 * @since 1.0.0
 */
final class OkfTopic
{
    /**
     * Creates a new topic.
     *
     * @since 1.0.0
     *
     * @param  string                             $slug             The topic slug, e.g. `foundations/doctype`.
     * @param  string                             $category         The category.
     * @param  SpecStatus                         $status           The status tier.
     * @param  string|null                        $conformance      The RFC 2119 keyword.
     * @param  string                             $title            The title.
     * @param  string                             $summary          The one-paragraph summary.
     * @param  string                             $bodyMarkdown     The Markdown body (front matter removed).
     * @param  array<int, array<string, string>>  $sources          The cited sources.
     * @param  string                             $resourceUrl      The canonical specification.website URL.
     * @param  CarbonImmutable|null               $sourceUpdatedAt  When the topic was last updated upstream.
     */
    public function __construct(
        public readonly string $slug,
        public readonly string $category,
        public readonly SpecStatus $status,
        public readonly ?string $conformance,
        public readonly string $title,
        public readonly string $summary,
        public readonly string $bodyMarkdown,
        public readonly array $sources,
        public readonly string $resourceUrl,
        public readonly ?CarbonImmutable $sourceUpdatedAt,
    ) {
    }
}
