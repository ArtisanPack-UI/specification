<?php

/**
 * Snapshot model.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Records each distinct OKF archive that has been synced.
 *
 * The version is the SHA-256 of the archive, so re-syncing identical bytes
 * is detected and skipped. `changes` records which slugs the sync that
 * produced the snapshot created, updated, and deleted.
 *
 * @property int          $id
 * @property string       $version
 * @property string|null  $okf_version
 * @property int          $topic_count
 * @property array{created: array<int, string>, updated: array<int, string>, deleted: array<int, string>}|null  $changes
 * @property Carbon       $synced_at
 * @property Carbon|null  $created_at
 * @property Carbon|null  $updated_at
 *
 * @since 1.0.0
 */
class Snapshot extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'specification_snapshots';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'version',
        'okf_version',
        'topic_count',
        'changes',
        'synced_at',
    ];

    /**
     * Gets the most recently synced snapshot.
     *
     * @since 1.0.0
     *
     * @return self|null
     */
    public static function current(): ?self
    {
        return static::query()->orderByDesc( 'synced_at' )->orderByDesc( 'id' )->first();
    }

    /**
     * Gets the attributes that should be cast.
     *
     * @since 1.0.0
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'topic_count' => 'integer',
            'changes'     => 'array',
            'synced_at'   => 'datetime',
        ];
    }
}
