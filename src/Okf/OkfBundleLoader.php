<?php

/**
 * OKF bundle loader.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Okf;

use ArtisanPackUI\Specification\Exceptions\InvalidArchiveException;

/**
 * Turns raw OKF archive bytes into a validated OkfBundle.
 *
 * Only Markdown files whose paths match a strict allow-list are kept, so
 * traversal paths (`../`), absolute paths, and unexpected files never reach
 * the disk or the database. A topic that fails to parse is recorded as an
 * error rather than failing the whole bundle.
 *
 * @since 1.0.0
 */
final class OkfBundleLoader
{
    /**
     * Allow-list for any Markdown file kept from the archive.
     *
     * @var string
     */
    public const FILE_PATTERN = '#^okf/(?:[a-z0-9]+(?:-[a-z0-9]+)*/)?[a-z0-9]+(?:-[a-z0-9]+)*\.md$#';

    /**
     * Pattern for topic files: `okf/{category}/{topic}.md`.
     *
     * @var string
     */
    private const TOPIC_PATTERN = '#^okf/([a-z0-9]+(?:-[a-z0-9]+)*)/([a-z0-9]+(?:-[a-z0-9]+)*)\.md$#';

    /**
     * Directories inside the bundle that never hold topics.
     *
     * @var array<int, string>
     */
    private const NON_TOPIC_DIRECTORIES = [ 'references' ];

    /**
     * Creates a new loader.
     *
     * @since 1.0.0
     *
     * @param  TarGzReader  $reader  The archive reader.
     * @param  TopicParser  $parser  The topic parser.
     */
    public function __construct(
        private readonly TarGzReader $reader,
        private readonly TopicParser $parser,
    ) {
    }

    /**
     * Loads a bundle from archive bytes.
     *
     * @since 1.0.0
     *
     * @param  string  $archive  The gzip-compressed tar bytes.
     *
     * @throws InvalidArchiveException When the archive is invalid or contains no topics.
     *
     * @return OkfBundle
     */
    public function load( string $archive ): OkfBundle
    {
        $files = array_filter(
            $this->reader->read( $archive ),
            static fn ( string $path ): bool => 1 === preg_match( self::FILE_PATTERN, $path ),
            ARRAY_FILTER_USE_KEY,
        );
        ksort( $files );

        $topics = [];
        $errors = [];

        foreach ( $files as $path => $contents ) {
            if ( 1 !== preg_match( self::TOPIC_PATTERN, $path, $matches ) ) {
                continue;
            }

            [ , $category, $name ] = $matches;

            if ( 'index' === $name || in_array( $category, self::NON_TOPIC_DIRECTORIES, true ) ) {
                continue;
            }

            try {
                $topic = $this->parser->parse( $category, $name, $contents );
            } catch ( InvalidArchiveException $exception ) {
                $errors[ $category . '/' . $name ] = $exception->getMessage();

                continue;
            }

            if ( null !== $topic ) {
                $topics[ $topic->slug ] = $topic;
            }
        }

        if ( [] === $topics ) {
            throw new InvalidArchiveException( __( 'The OKF archive contains no valid topics.' ) );
        }

        return new OkfBundle(
            hash( 'sha256', $archive ),
            $this->okfVersion( $files['okf/index.md'] ?? null ),
            $topics,
            $files,
            $errors,
            $archive,
        );
    }

    /**
     * Reads `okf_version` from the bundle index front matter.
     *
     * @since 1.0.0
     *
     * @param  string|null  $index  The `okf/index.md` contents.
     *
     * @return string|null
     */
    private function okfVersion( ?string $index ): ?string
    {
        if ( null === $index ) {
            return null;
        }

        try {
            [ $meta ] = $this->parser->frontMatter( $index );
        } catch ( InvalidArchiveException ) {
            return null;
        }

        return is_scalar( $meta['okf_version'] ?? null ) ? (string) $meta['okf_version'] : null;
    }
}
