<?php

/**
 * Sync result.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Okf;

use Illuminate\Contracts\Support\Arrayable;

/**
 * What a sync changed.
 *
 * @implements Arrayable<string, mixed>
 *
 * @since 1.0.0
 */
final class SyncResult implements Arrayable
{
    /**
     * Creates a new result.
     *
     * @since 1.0.0
     *
     * @param  string                 $version     The synced snapshot version (archive SHA-256).
     * @param  bool                   $changed     False when the archive matched the current snapshot.
     * @param  array<int, string>     $created     Slugs added.
     * @param  array<int, string>     $updated     Slugs whose content changed.
     * @param  array<int, string>     $deleted     Slugs removed upstream.
     * @param  int                    $unchanged   Topics whose content did not change.
     * @param  array<string, string>  $errors      Topics that failed to parse, keyed by slug (kept as-is).
     */
    public function __construct(
        public readonly string $version,
        public readonly bool $changed,
        public readonly array $created = [],
        public readonly array $updated = [],
        public readonly array $deleted = [],
        public readonly int $unchanged = 0,
        public readonly array $errors = [],
    ) {
    }

    /**
     * Gets the result as an array.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'version'   => $this->version,
            'changed'   => $this->changed,
            'created'   => $this->created,
            'updated'   => $this->updated,
            'deleted'   => $this->deleted,
            'unchanged' => $this->unchanged,
            'errors'    => $this->errors,
        ];
    }
}
