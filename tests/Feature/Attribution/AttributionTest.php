<?php

declare( strict_types=1 );

use ArtisanPackUI\Specification\Attribution\Attribution;
use ArtisanPackUI\Specification\Attribution\LicenseGuard;
use ArtisanPackUI\Specification\Attribution\SpecTopicRenderer;
use ArtisanPackUI\Specification\Enums\AttributionPlacement;
use ArtisanPackUI\Specification\Enums\RenderSurface;
use ArtisanPackUI\Specification\Exceptions\LicenseViolationException;
use ArtisanPackUI\Specification\Models\Topic;

function doctypeTopic(): Topic
{
    specification()->sync( archivePath: writeOkfArchive( okfArchive() ) );

    return specification()->topic( 'foundations/doctype' );
}

it( 'builds a complete CC BY 4.0 attribution', function (): void {
    $attribution = new Attribution( 'The HTML doctype', 'https://specification.website/spec/foundations/doctype/' );

    expect( $attribution->toText() )->toBe( '"The HTML doctype" (https://specification.website/spec/foundations/doctype/) from The Website Specification (https://specification.website), licensed under CC BY 4.0 (https://creativecommons.org/licenses/by/4.0/).' )
        ->and( $attribution->canonical() )->toBe( $attribution->toText() )
        ->and( $attribution->toHtml() )->toContain( '<a href="https://creativecommons.org/licenses/by/4.0/" rel="license">CC BY 4.0</a>' )
        ->and( $attribution->toHtml() )->toContain( '<a href="https://specification.website">The Website Specification</a>' )
        ->and( $attribution->toMarkdown() )->toBe( '“[The HTML doctype](<https://specification.website/spec/foundations/doctype/>)” from [The Website Specification](<https://specification.website>), licensed under [CC BY 4.0](<https://creativecommons.org/licenses/by/4.0/>).' )
        ->and( $attribution->toArray() )->toHaveKeys( [ 'title', 'url', 'source', 'source_url', 'license', 'license_url', 'text' ] );
} );

it( 'escapes titles in HTML and Markdown attribution', function (): void {
    $attribution = new Attribution( '<script>alert(1)</script> [x](y)', 'https://specification.website/spec/a/b/' );

    expect( $attribution->toHtml() )->not->toContain( '<script>' )
        ->and( $attribution->toHtml() )->toContain( '&lt;script&gt;' )
        ->and( $attribution->toMarkdown() )->toContain( '\\[x\\]\\(y\\)' );
} );

it( 'renders the body with attribution in every format', function (): void {
    $rendered = doctypeTopic()->render();

    expect( $rendered->toHtml() )->toContain( '<h1>The HTML doctype</h1>' )
        ->and( $rendered->toHtml() )->toContain( 'class="spec-attribution"' )
        ->and( (string) $rendered )->toBe( $rendered->toHtml() )
        ->and( $rendered->toMarkdown() )->toStartWith( '# The HTML doctype' )
        ->and( $rendered->toMarkdown() )->toEndWith( '[CC BY 4.0](<https://creativecommons.org/licenses/by/4.0/>).' )
        ->and( $rendered->toArray()['attribution']['license'] )->toBe( 'CC BY 4.0' )
        ->and( json_decode( (string) json_encode( $rendered ), true )['attribution']['url'] )->toBe( 'https://specification.website/spec/foundations/doctype/' )
        ->and( $rendered->placement )->toBe( AttributionPlacement::After )
        ->and( strpos( $rendered->toHtml(), 'spec-attribution' ) )->toBeGreaterThan( strpos( $rendered->toHtml(), 'spec-topic-body' ) );
} );

it( 'escapes raw HTML and drops unsafe links in topic bodies', function (): void {
    $topic          = doctypeTopic();
    $topic->body_md = "<script>alert(1)</script>\n\n[click](javascript:alert(1)) <img src=x onerror=alert(1)>";

    $html = app( SpecTopicRenderer::class )->render( $topic )->toHtml();

    expect( $html )->not->toContain( '<script>' )
        ->and( $html )->not->toContain( '<img' )
        ->and( $html )->not->toContain( 'javascript:' )
        ->and( $html )->toContain( '&lt;script&gt;' );
} );

it( 'blocks rendering on marketing surfaces', function (): void {
    doctypeTopic()->render( RenderSurface::Marketing );
} )->throws( LicenseViolationException::class, 'marketing' );

it( 'places attribution before the body on paid surfaces', function (): void {
    $rendered = doctypeTopic()->render( RenderSurface::Paid );

    expect( $rendered->placement )->toBe( AttributionPlacement::Before )
        ->and( strpos( $rendered->toHtml(), 'spec-attribution' ) )->toBeLessThan( strpos( $rendered->toHtml(), 'spec-topic-body' ) )
        ->and( $rendered->toMarkdown() )->toStartWith( '“[The HTML doctype]' );
} );

it( 'honours configured license surfaces', function (): void {
    $guard = new LicenseGuard( [ 'public', 'not-a-surface' ], [ RenderSurface::Agent ] );

    expect( $guard->isBlocked( RenderSurface::Public ) )->toBeTrue()
        ->and( $guard->isBlocked( RenderSurface::Marketing ) )->toBeFalse()
        ->and( $guard->placement( RenderSurface::Agent ) )->toBe( AttributionPlacement::Before )
        ->and( $guard->placement( RenderSurface::Paid ) )->toBe( AttributionPlacement::After );

    config()->set( 'artisanpack.specification.license.blocked_surfaces', [ 'agent' ] );
    app()->forgetInstance( LicenseGuard::class );
    app()->forgetInstance( SpecTopicRenderer::class );

    expect( fn () => doctypeTopic()->render( RenderSurface::Agent ) )->toThrow( LicenseViolationException::class );
} );

it( 'keeps the topic body out of model serialization', function (): void {
    $topic = doctypeTopic();

    expect( $topic->toArray() )->not->toHaveKey( 'body_md' )
        ->and( $topic->toJson() )->not->toContain( 'quirks mode' )
        ->and( $topic->toArray()['attribution'] )->toContain( 'CC BY 4.0' );
} );

it( 'renders through the entry point', function (): void {
    expect( specification()->render( doctypeTopic(), RenderSurface::Editor )->surface )->toBe( RenderSurface::Editor );
} );

it( 'never links a non-http(s) attribution URL', function ( string $url ): void {
    $attribution = new Attribution( 'Title', $url );

    expect( $attribution->toHtml() )->not->toContain( 'href="' . e( $url ) )
        ->and( $attribution->toHtml() )->toContain( '“Title”' )
        ->and( $attribution->toMarkdown() )->not->toContain( $url )
        ->and( $attribution->toMarkdown() )->toStartWith( '“Title”' );
} )->with( [
    'javascript' => 'javascript:alert(1)',
    'data'       => 'data:text/html,<script>alert(1)</script>',
    'relative'   => '/spec/a/b/',
] );

it( 'keeps canonical upstream URLs and derives the rest from the slug', function ( mixed $url, string $expected ): void {
    expect( Attribution::canonicalUrl( 'foundations/doctype', $url ) )->toBe( $expected );
} )->with( [
    'upstream'      => [ 'https://specification.website/spec/foundations/doctype/', 'https://specification.website/spec/foundations/doctype/' ],
    'foreign host'  => [ 'https://evil.example/spec/foundations/doctype/', 'https://specification.website/spec/foundations/doctype/' ],
    'look-alike'    => [ 'https://specification.website.evil.example/x', 'https://specification.website/spec/foundations/doctype/' ],
    'javascript'    => [ 'javascript:alert(1)', 'https://specification.website/spec/foundations/doctype/' ],
    'too long'      => [ 'https://specification.website/' . str_repeat( 'a', 600 ), 'https://specification.website/spec/foundations/doctype/' ],
    'not a string'  => [ null, 'https://specification.website/spec/foundations/doctype/' ],
] );
