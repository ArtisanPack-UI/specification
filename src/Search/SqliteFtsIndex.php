<?php

/**
 * SQLite FTS5 search index.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Search;

use ArtisanPackUI\Specification\Contracts\SearchIndex;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use ArtisanPackUI\Specification\Exceptions\SearchIndexUnavailableException;
use ArtisanPackUI\Specification\Models\Topic;
use PDO;
use PDOException;
use Throwable;

/**
 * An embedded SQLite FTS5 index in its own database file.
 *
 * The file is independent of the application's database connection, so the
 * index works offline and regardless of whether the app runs on MySQL,
 * PostgreSQL, or SQLite. User queries are reduced to word tokens and quoted
 * before reaching MATCH, so FTS5 query syntax cannot be injected.
 *
 * @since 1.0.0
 */
final class SqliteFtsIndex implements SearchIndex
{
    /**
     * Maximum number of query tokens considered.
     *
     * @var int
     */
    private const MAX_TOKENS = 16;

    /**
     * The lazily opened connection.
     *
     * @var PDO|null
     */
    private ?PDO $pdo = null;

    /**
     * Creates a new index.
     *
     * @since 1.0.0
     *
     * @param  string  $path  Absolute path to the index file, or `:memory:`.
     */
    public function __construct( private readonly string $path )
    {
    }

    /**
     * {@inheritdoc}
     *
     * @since 1.0.0
     */
    public function rebuild( iterable $topics, string $version ): void
    {
        $pdo = $this->connection();

        try {
            $pdo->beginTransaction();
            $pdo->exec( 'DELETE FROM topics' );

            $insert = $pdo->prepare( 'INSERT INTO topics (slug, category, status, resource_url, title, summary, body) VALUES (?, ?, ?, ?, ?, ?, ?)' );

            foreach ( $topics as $topic ) {
                /** @var Topic $topic */
                $insert->execute( [
                    $topic->slug,
                    $topic->category,
                    $topic->status->value,
                    $topic->resource_url,
                    $topic->title,
                    $topic->summary,
                    $topic->body_md,
                ] );
            }

            $pdo->prepare( "INSERT INTO meta (key, value) VALUES ('version', ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value" )
                ->execute( [ $version ] );
            $pdo->commit();
        } catch ( Throwable $exception ) {
            if ( $pdo->inTransaction() ) {
                $pdo->rollBack();
            }

            throw new SearchIndexUnavailableException( __( 'Could not rebuild the search index: :message', [
                'message' => $exception->getMessage(),
            ] ), previous: $exception );
        }
    }

    /**
     * {@inheritdoc}
     *
     * @since 1.0.0
     */
    public function search( string $query, int $limit = 10, ?string $category = null, ?SpecStatus $status = null ): array
    {
        $match = $this->matchExpression( $query );

        if ( '' === $match ) {
            return [];
        }

        $sql      = 'SELECT slug, category, status, resource_url, title, summary,'
            . " snippet(topics, 6, '[[', ']]', '…', 16) AS snippet,"
            . ' bm25(topics, 0.0, 0.0, 0.0, 0.0, 10.0, 5.0, 1.0) AS score'
            . ' FROM topics WHERE topics MATCH ?';
        $bindings = [ $match ];

        if ( null !== $category ) {
            $sql .= ' AND category = ?';
            $bindings[] = $category;
        }

        if ( null !== $status ) {
            $sql .= ' AND status = ?';
            $bindings[] = $status->value;
        }

        $sql .= ' ORDER BY score LIMIT ?';
        $bindings[] = max( 1, min( 100, $limit ) );

        try {
            $statement = $this->connection()->prepare( $sql );
            $statement->execute( $bindings );
            $rows = $statement->fetchAll( PDO::FETCH_ASSOC );
        } catch ( PDOException $exception ) {
            throw new SearchIndexUnavailableException( __( 'Could not query the search index: :message', [
                'message' => $exception->getMessage(),
            ] ), previous: $exception );
        }

        return array_map( static fn ( array $row ): SearchHit => new SearchHit(
            (string) $row['slug'],
            (string) $row['category'],
            SpecStatus::from( (string) $row['status'] ),
            (string) $row['title'],
            (string) $row['summary'],
            (string) $row['snippet'],
            (string) $row['resource_url'],
            (float) $row['score'],
        ), $rows );
    }

    /**
     * {@inheritdoc}
     *
     * @since 1.0.0
     */
    public function version(): ?string
    {
        $value = $this->connection()->query( "SELECT value FROM meta WHERE key = 'version'" )->fetchColumn();

        return false === $value ? null : (string) $value;
    }

    /**
     * Builds a safe FTS5 MATCH expression from free text.
     *
     * Each word token is double-quoted (so operators and column filters are
     * treated as literal text) and prefix-matched; tokens are ANDed.
     *
     * @since 1.0.0
     *
     * @param  string  $query  The free-text query.
     *
     * @return string
     */
    public function matchExpression( string $query ): string
    {
        preg_match_all( '/[\p{L}\p{N}]+/u', $query, $matches );

        $tokens = array_slice( array_unique( array_map( 'mb_strtolower', $matches[0] ) ), 0, self::MAX_TOKENS );

        return implode( ' ', array_map( static fn ( string $token ): string => '"' . $token . '"*', $tokens ) );
    }

    /**
     * Opens the index database and ensures its schema exists.
     *
     * @since 1.0.0
     *
     * @throws SearchIndexUnavailableException When the file cannot be opened or FTS5 is unavailable.
     *
     * @return PDO
     */
    private function connection(): PDO
    {
        if ( null !== $this->pdo ) {
            return $this->pdo;
        }

        try {
            if ( ':memory:' !== $this->path && ! is_dir( dirname( $this->path ) ) ) {
                mkdir( dirname( $this->path ), 0755, true );
            }

            $pdo = new PDO( 'sqlite:' . $this->path, null, null, [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ] );
            $pdo->exec( "CREATE VIRTUAL TABLE IF NOT EXISTS topics USING fts5(slug UNINDEXED, category UNINDEXED, status UNINDEXED, resource_url UNINDEXED, title, summary, body, tokenize = 'porter unicode61')" );
            $pdo->exec( 'CREATE TABLE IF NOT EXISTS meta (key TEXT PRIMARY KEY, value TEXT NOT NULL)' );
        } catch ( Throwable $exception ) {
            throw new SearchIndexUnavailableException( __( 'The SQLite FTS5 search index at [:path] is unavailable: :message', [
                'path'    => $this->path,
                'message' => $exception->getMessage(),
            ] ), previous: $exception );
        }

        return $this->pdo = $pdo;
    }
}
