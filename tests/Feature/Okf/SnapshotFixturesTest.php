<?php

declare( strict_types=1 );

use ArtisanPackUI\Specification\Attribution\Attribution;
use ArtisanPackUI\Specification\Enums\RenderSurface;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use ArtisanPackUI\Specification\Models\Topic;

/**
 * The three fixture topics: one per status tier that ships with a check pattern.
 *
 * @return array<string, array{0: string, 1: string, 2: SpecStatus, 3: string, 4: string}>
 */
function fixtureTopics(): array
{
    return [
        'required'    => [ 'foundations/doctype', 'foundations', SpecStatus::Required, 'The HTML doctype', 'https://specification.website/spec/foundations/doctype/' ],
        'recommended' => [ 'performance/vary', 'performance', SpecStatus::Recommended, 'The Vary header', 'https://specification.website/spec/performance/vary/' ],
        'avoid'       => [ 'security/x-xss-protection', 'security', SpecStatus::Avoid, 'X-XSS-Protection', 'https://specification.website/spec/security/x-xss-protection/' ],
    ];
}

it( 'ships exactly three fixture topics', function (): void {
    $result = specification()->sync( archivePath: writeOkfArchive( okfArchive() ) );

    expect( $result->created )->toBe( array_column( fixtureTopics(), 0 ) )
        ->and( $result->errors )->toBe( [] );
} );

it( 'parses each fixture topic into a stable row', function ( string $slug, string $category, SpecStatus $status, string $title, string $url ): void {
    $archive = okfArchive();
    specification()->sync( archivePath: writeOkfArchive( $archive ) );

    $topic = Topic::query()->where( 'slug', $slug )->firstOrFail();

    expect( $topic->category )->toBe( $category )
        ->and( $topic->status )->toBe( $status )
        ->and( $topic->conformance )->toBe( $status->conformance() )
        ->and( $topic->title )->toBe( $title )
        ->and( $topic->resource_url )->toBe( $url )
        ->and( $topic->snapshot_version )->toBe( hash( 'sha256', $archive ) )
        ->and( $topic->attribution )->toBe( ( new Attribution( $title, $url ) )->canonical() )
        ->and( $topic->sources_json )->not->toBeEmpty()
        ->and( $topic->source_updated_at )->not->toBeNull();
} )->with( fixtureTopics() );

it( 'renders each fixture topic with its CC BY 4.0 attribution', function ( string $slug, string $category, SpecStatus $status, string $title, string $url ): void {
    specification()->sync( archivePath: writeOkfArchive( okfArchive() ) );

    $topic       = specification()->topic( $slug );
    $attribution = new Attribution( $title, $url );
    $admin       = specification()->render( $topic );
    $paid        = specification()->render( $topic, RenderSurface::Paid );

    expect( $admin->toHtml() )->toStartWith( '<div class="spec-topic" data-spec-slug="' . $slug . '"><div class="spec-topic-body">' )
        ->and( $admin->toHtml() )->toEndWith( $attribution->toHtml() . '</div>' )
        ->and( $admin->toHtml() )->toContain( '<a href="' . $url . '">' )
        ->and( $admin->toMarkdown() )->toEndWith( "\n\n---\n\n" . $attribution->toMarkdown() )
        ->and( $paid->toHtml() )->toStartWith( '<div class="spec-topic" data-spec-slug="' . $slug . '">' . $attribution->toHtml() )
        ->and( $paid->toMarkdown() )->toStartWith( $attribution->toMarkdown() . "\n\n---\n\n" )
        ->and( json_decode( (string) json_encode( $admin ), true )['attribution']['url'] )->toBe( $url )
        ->and( (string) $admin )->toBe( $admin->toHtml() );
} )->with( fixtureTopics() );
