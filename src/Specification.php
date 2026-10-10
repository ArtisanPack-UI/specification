<?php

/**
 * Main Specification class.
 *
 * This is the main class for the package, accessed via the `specification()`
 * helper function or the Specification facade.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification;

use ArtisanPackUI\Specification\Attribution\RenderedTopic;
use ArtisanPackUI\Specification\Attribution\SpecTopicRenderer;
use ArtisanPackUI\Specification\Checks\CheckRegistry;
use ArtisanPackUI\Specification\Checks\CheckRunner;
use ArtisanPackUI\Specification\Checks\SpecContext;
use ArtisanPackUI\Specification\Checks\SpecResult;
use ArtisanPackUI\Specification\Contracts\SearchIndex;
use ArtisanPackUI\Specification\Contracts\SpecCheck;
use ArtisanPackUI\Specification\Enums\RenderSurface;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use ArtisanPackUI\Specification\Models\Topic;
use ArtisanPackUI\Specification\Okf\Synchronizer;
use ArtisanPackUI\Specification\Okf\SyncResult;
use ArtisanPackUI\Specification\Reporting\SpecReport;
use ArtisanPackUI\Specification\Search\SearchHit;

/**
 * Entry point for the package.
 *
 * Exposes sync, search, the check registry and runner, reporting, and the
 * attributed topic renderer.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */
class Specification
{
    /**
     * Creates a new instance.
     *
     * @since 1.0.0
     *
     * @param  Synchronizer       $synchronizer  The OKF synchronizer.
     * @param  SearchIndex        $index         The local search index.
     * @param  CheckRegistry      $registry      The check registry.
     * @param  CheckRunner        $runner        The check runner.
     * @param  SpecTopicRenderer  $renderer      The topic renderer.
     */
    public function __construct(
        private readonly Synchronizer $synchronizer,
        private readonly SearchIndex $index,
        private readonly CheckRegistry $registry,
        private readonly CheckRunner $runner,
        private readonly SpecTopicRenderer $renderer,
    ) {
    }

    /**
     * Syncs the OKF snapshot, topics table, and search index.
     *
     * @since 1.0.0
     *
     * @param  bool         $force        Re-apply even if the archive is unchanged.
     * @param  string|null  $archivePath  Sync from a local archive instead of downloading.
     *
     * @return SyncResult
     */
    public function sync( bool $force = false, ?string $archivePath = null ): SyncResult
    {
        return $this->synchronizer->sync( $force, $archivePath );
    }

    /**
     * Searches the local index.
     *
     * @since 1.0.0
     *
     * @param  string           $query     Free-text query.
     * @param  int              $limit     Maximum hits.
     * @param  string|null      $category  Limit to a category.
     * @param  SpecStatus|null  $status    Limit to a status tier.
     *
     * @return array<int, SearchHit>
     */
    public function search( string $query, int $limit = 10, ?string $category = null, ?SpecStatus $status = null ): array
    {
        return $this->index->search( $query, $limit, $category, $status );
    }

    /**
     * Finds a topic by slug.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  The topic slug.
     *
     * @return Topic|null
     */
    public function topic( string $slug ): ?Topic
    {
        return Topic::query()->where( 'slug', $slug )->first();
    }

    /**
     * Gets the check registry.
     *
     * @since 1.0.0
     *
     * @return CheckRegistry
     */
    public function checks(): CheckRegistry
    {
        return $this->registry;
    }

    /**
     * Registers a check.
     *
     * @since 1.0.0
     *
     * @param  SpecCheck|string  $check  A check instance or class name.
     *
     * @return static
     */
    public function registerCheck( SpecCheck|string $check ): static
    {
        $this->registry->register( $check );

        return $this;
    }

    /**
     * Runs the registered checks that match the context's scope.
     *
     * @since 1.0.0
     *
     * @param  SpecContext              $context   The subject to check.
     * @param  array<int, string>|null  $slugs     Limit to these slugs.
     * @param  string|null              $category  Limit to this category.
     *
     * @return array<int, SpecResult>
     */
    public function run( SpecContext $context, ?array $slugs = null, ?string $category = null ): array
    {
        return $this->runner->run( $context, $slugs, $category );
    }

    /**
     * Builds a four-tier report from results.
     *
     * @since 1.0.0
     *
     * @param  iterable<SpecResult>  $results  The results.
     *
     * @return SpecReport
     */
    public function report( iterable $results ): SpecReport
    {
        return SpecReport::fromResults( $results );
    }

    /**
     * Renders a topic body with its attribution.
     *
     * @since 1.0.0
     *
     * @param  Topic          $topic    The topic.
     * @param  RenderSurface  $surface  Where the topic is displayed.
     *
     * @return RenderedTopic
     */
    public function render( Topic $topic, RenderSurface $surface = RenderSurface::Admin ): RenderedTopic
    {
        return $this->renderer->render( $topic, $surface );
    }
}
