<?php

/**
 * Parsed OKF bundle.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Okf;

/**
 * The validated contents of one OKF archive.
 *
 * @since 1.0.0
 */
final class OkfBundle
{
    /**
     * Creates a new bundle.
     *
     * @since 1.0.0
     *
     * @param  string                    $version     SHA-256 of the archive bytes.
     * @param  string|null               $okfVersion  The `okf_version` declared by `okf/index.md`.
     * @param  array<string, OkfTopic>   $topics      Parsed topics keyed by slug.
     * @param  array<string, string>     $files       Validated Markdown files keyed by archive path.
     * @param  array<string, string>     $errors      Topics that failed to parse, keyed by slug.
     * @param  string                    $archive     The raw archive bytes.
     */
    public function __construct(
        public readonly string $version,
        public readonly ?string $okfVersion,
        public readonly array $topics,
        public readonly array $files,
        public readonly array $errors,
        public readonly string $archive,
    ) {
    }
}
