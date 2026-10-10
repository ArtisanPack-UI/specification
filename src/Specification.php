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
use ArtisanPackUI\Specification\Contracts\McpClient;
use ArtisanPackUI\Specification\Contracts\SearchIndex;
use ArtisanPackUI\Specification\Contracts\SpecCheck;
use ArtisanPackUI\Specification\Enums\RenderSurface;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use ArtisanPackUI\Specification\Exceptions\SearchIndexUnavailableException;
use ArtisanPackUI\Specification\Models\Snapshot;
use ArtisanPackUI\Specification\Models\Topic;
use ArtisanPackUI\Specification\Okf\Synchronizer;
use ArtisanPackUI\Specification\Okf\SyncResult;
use ArtisanPackUI\Specification\Reporting\SpecReport;
use ArtisanPackUI\Specification\Search\SearchHit;
use Illuminate\Contracts\Cache\LockProvider;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\QueryException;

/**
 * Entry point for the package.
 *
 * Exposes sync, search, the check registry and runner, reporting, the
 * attributed topic renderer, and the optional MCP client.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */
class Specification
{
    /**
     * Cache lock that keeps concurrent requests from rebuilding the index together.
     *
     * @var string
     */
    public const INDEX_LOCK_NAME = 'artisanpack-specification-index-rebuild';

    /**
     * Seconds to wait for another request's index rebuild before giving up.
     *
     * @var int
     */
    public const INDEX_LOCK_WAIT_SECONDS = 10;

    /**
     * The snapshot version the local index was last confirmed to match.
     *
     * @var string|null
     */
    private ?string $verifiedIndexVersion = null;

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
     * @param  McpClient          $mcp           The MCP client.
     * @param  CacheRepository    $cache         Provides the index rebuild lock.
     */
    public function __construct(
        private readonly Synchronizer $synchronizer,
        private readonly SearchIndex $index,
        private readonly CheckRegistry $registry,
        private readonly CheckRunner $runner,
        private readonly SpecTopicRenderer $renderer,
        private readonly McpClient $mcp,
        private readonly CacheRepository $cache,
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
     * The index is a file on this server, while topics live in the shared
     * database. If the index does not match the current snapshot (another
     * server ran the sync, or the file was lost), it is rebuilt from the
     * topic rows before searching.
     *
     * @since 1.0.0
     *
     * @param  string           $query     Free-text query.
     * @param  int              $limit     Maximum hits.
     * @param  string|null      $category  Limit to a category.
     * @param  SpecStatus|null  $status    Limit to a status tier.
     *
     * @throws SearchIndexUnavailableException When the index cannot be opened, rebuilt, or queried.
     *
     * @return array<int, SearchHit>
     */
    public function search( string $query, int $limit = 10, ?string $category = null, ?SpecStatus $status = null ): array
    {
        $this->ensureIndexIsCurrent();

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

    /**
     * Gets the specification.website MCP client.
     *
     * The client is disabled unless `artisanpack.specification.mcp.enabled`
     * is true; check isEnabled() before calling it.
     *
     * @since 1.0.0
     *
     * @return McpClient
     */
    public function mcp(): McpClient
    {
        return $this->mcp;
    }

    /**
     * Determines whether checks for experimental Agent Readiness topics are enabled.
     *
     * Emerging Agent Readiness topics (WebMCP, DNS-AID, ai-catalog, etc.) are
     * still moving upstream. Owner packages call this before registering or
     * running checks for them; it reads the
     * `artisanpack.specification.agent_readiness_experimental` config value.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function agentReadinessExperimentalEnabled(): bool
    {
        return true === (bool) config( 'artisanpack.specification.agent_readiness_experimental', false );
    }

    /**
     * Rebuilds the local index from the topic rows when it is stale.
     *
     * Waits up to INDEX_LOCK_WAIT_SECONDS for a rebuild running elsewhere,
     * then re-checks the version, so a search never runs against an index
     * that has not been confirmed current.
     *
     * @since 1.0.0
     *
     * @throws SearchIndexUnavailableException When another rebuild holds the lock for too long.
     *
     * @return void
     */
    private function ensureIndexIsCurrent(): void
    {
        try {
            $version = Snapshot::current()?->version;
        } catch ( QueryException ) {
            return;
        }

        if ( null === $version || $version === $this->verifiedIndexVersion ) {
            return;
        }

        if ( $this->index->version() !== $version ) {
            $store = $this->cache->getStore();
            $lock  = $store instanceof LockProvider ? $store->lock( self::INDEX_LOCK_NAME, 60 ) : null;

            try {
                $lock?->block( self::INDEX_LOCK_WAIT_SECONDS );
            } catch ( LockTimeoutException $exception ) {
                throw new SearchIndexUnavailableException( __( 'The search index is being rebuilt by another process; try again shortly.' ), previous: $exception );
            }

            try {
                if ( $this->index->version() !== $version ) {
                    $this->index->rebuild( Topic::query()->orderBy( 'slug' )->get(), $version );
                }
            } finally {
                $lock?->release();
            }
        }

        $this->verifiedIndexVersion = $version;
    }
}
