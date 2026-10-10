<?php

/**
 * SpecReport aggregate.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Reporting;

use ArtisanPackUI\Specification\Checks\SpecResult;
use Illuminate\Contracts\Support\Arrayable;
use JsonSerializable;

/**
 * Rolls SpecCheck results into site, per-category, and per-page scores.
 *
 * Every level is a TierBreakdown, so the four tiers are always reported
 * separately. Page-level, Section-level, and Block-level results are grouped
 * under their page's subject; Site and Server results only feed the site and
 * category breakdowns.
 *
 * @implements Arrayable<string, mixed>
 *
 * @since 1.0.0
 */
final class SpecReport implements Arrayable, JsonSerializable
{
    /**
     * Creates a new report.
     *
     * @since 1.0.0
     *
     * @param  array<int, SpecResult>        $results     All results.
     * @param  TierBreakdown                 $site        Site-level breakdown.
     * @param  array<string, TierBreakdown>  $categories  Breakdowns keyed by category.
     * @param  array<string, TierBreakdown>  $pages       Breakdowns keyed by page subject.
     */
    private function __construct(
        private readonly array $results,
        private readonly TierBreakdown $site,
        private readonly array $categories,
        private readonly array $pages,
    ) {
    }

    /**
     * Builds a report from results.
     *
     * @since 1.0.0
     *
     * @param  iterable<SpecResult>  $results  The results.
     *
     * @return self
     */
    public static function fromResults( iterable $results ): self
    {
        $all        = [];
        $byCategory = [];
        $byPage     = [];

        foreach ( $results as $result ) {
            $all[]                                 = $result;
            $byCategory[ $result->category() ][]   = $result;

            if ( $result->scope->isPageLevel() ) {
                $byPage[ $result->subject ][] = $result;
            }
        }

        ksort( $byCategory );
        ksort( $byPage );

        return new self(
            $all,
            TierBreakdown::fromResults( $all ),
            array_map( TierBreakdown::fromResults( ... ), $byCategory ),
            array_map( TierBreakdown::fromResults( ... ), $byPage ),
        );
    }

    /**
     * Gets the site-level breakdown.
     *
     * @since 1.0.0
     *
     * @return TierBreakdown
     */
    public function site(): TierBreakdown
    {
        return $this->site;
    }

    /**
     * Gets the per-category breakdowns.
     *
     * @since 1.0.0
     *
     * @return array<string, TierBreakdown>
     */
    public function categories(): array
    {
        return $this->categories;
    }

    /**
     * Gets one category's breakdown.
     *
     * @since 1.0.0
     *
     * @param  string  $category  The category.
     *
     * @return TierBreakdown|null
     */
    public function category( string $category ): ?TierBreakdown
    {
        return $this->categories[ $category ] ?? null;
    }

    /**
     * Gets the per-page breakdowns.
     *
     * @since 1.0.0
     *
     * @return array<string, TierBreakdown>
     */
    public function pages(): array
    {
        return $this->pages;
    }

    /**
     * Gets one page's breakdown.
     *
     * @since 1.0.0
     *
     * @param  string  $subject  The page subject (usually its URL).
     *
     * @return TierBreakdown|null
     */
    public function page( string $subject ): ?TierBreakdown
    {
        return $this->pages[ $subject ] ?? null;
    }

    /**
     * Gets the underlying results.
     *
     * @since 1.0.0
     *
     * @return array<int, SpecResult>
     */
    public function results(): array
    {
        return $this->results;
    }

    /**
     * Gets the report as an array.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'site'       => $this->site->toArray(),
            'categories' => array_map( static fn ( TierBreakdown $breakdown ): array => $breakdown->toArray(), $this->categories ),
            'pages'      => array_map( static fn ( TierBreakdown $breakdown ): array => $breakdown->toArray(), $this->pages ),
        ];
    }

    /**
     * Gets the data to serialize to JSON.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
