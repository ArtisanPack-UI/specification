<?php

/**
 * Topic model.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Models;

use ArtisanPackUI\Specification\Attribution\RenderedTopic;
use ArtisanPackUI\Specification\Attribution\SpecTopicRenderer;
use ArtisanPackUI\Specification\Enums\RenderSurface;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * One specification.website topic parsed from the OKF snapshot.
 *
 * `body_md` is hidden from array/JSON serialization so topic text cannot
 * leave the application without its CC BY 4.0 attribution; use render()
 * (or SpecTopicRenderer) to output it.
 *
 * @property int                                         $id
 * @property string                                      $slug
 * @property string                                      $category
 * @property SpecStatus                                  $status
 * @property string|null                                 $conformance
 * @property string                                      $title
 * @property string                                      $summary
 * @property string                                      $body_md
 * @property array<int, array<string, string>>           $sources_json
 * @property string                                      $resource_url
 * @property string                                      $snapshot_version
 * @property string                                      $attribution
 * @property Carbon|null                                 $source_updated_at
 * @property Carbon|null                                 $created_at
 * @property Carbon|null                                 $updated_at
 *
 * @since 1.0.0
 */
class Topic extends Model
{
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'specification_topics';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'slug',
        'category',
        'status',
        'conformance',
        'title',
        'summary',
        'body_md',
        'sources_json',
        'resource_url',
        'snapshot_version',
        'attribution',
        'source_updated_at',
    ];

    /**
     * The attributes hidden from serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'body_md',
    ];

    /**
     * Renders the topic body with its attribution for the given surface.
     *
     * @since 1.0.0
     *
     * @param  RenderSurface  $surface  Where the topic is displayed.
     *
     * @return RenderedTopic
     */
    public function render( RenderSurface $surface = RenderSurface::Admin ): RenderedTopic
    {
        return app( SpecTopicRenderer::class )->render( $this, $surface );
    }

    /**
     * Scopes the query to a category.
     *
     * @since 1.0.0
     *
     * @param  Builder<self>  $query     The query.
     * @param  string         $category  The category.
     *
     * @return Builder<self>
     */
    public function scopeInCategory( Builder $query, string $category ): Builder
    {
        return $query->where( 'category', $category );
    }

    /**
     * Scopes the query to a status tier.
     *
     * @since 1.0.0
     *
     * @param  Builder<self>  $query   The query.
     * @param  SpecStatus     $status  The status.
     *
     * @return Builder<self>
     */
    public function scopeWithStatus( Builder $query, SpecStatus $status ): Builder
    {
        return $query->where( 'status', $status->value );
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
            'status'            => SpecStatus::class,
            'sources_json'      => 'array',
            'source_updated_at' => 'datetime',
        ];
    }
}
