<?php

declare( strict_types=1 );

use ArtisanPackUI\Specification\Enums\SpecStatus;
use ArtisanPackUI\Specification\Exceptions\InvalidArchiveException;
use ArtisanPackUI\Specification\Okf\TopicParser;

function topicDocument( array $overrides = [], string $body = "# Title\n\nBody." ): string
{
    $meta = array_merge( [
        'type'        => 'Check',
        'title'       => '"The HTML doctype"',
        'description' => '"Start with the doctype."',
        'resource'    => 'https://specification.website/spec/foundations/doctype/',
        'requirement' => 'required',
        'conformance' => '"MUST"',
    ], $overrides );

    $yaml = '';

    foreach ( $meta as $key => $value ) {
        if ( null !== $value ) {
            $yaml .= $key . ': ' . $value . "\n";
        }
    }

    return "---\n" . $yaml . "---\n\n" . $body;
}

it( 'parses a fixture topic', function (): void {
    $topic = ( new TopicParser() )->parse( 'foundations', 'doctype', (string) file_get_contents( __DIR__ . '/../../Fixtures/okf/foundations/doctype.md' ) );

    expect( $topic->slug )->toBe( 'foundations/doctype' )
        ->and( $topic->category )->toBe( 'foundations' )
        ->and( $topic->status )->toBe( SpecStatus::Required )
        ->and( $topic->conformance )->toBe( 'MUST' )
        ->and( $topic->title )->toBe( 'The HTML doctype' )
        ->and( $topic->summary )->toBe( 'Every HTML document must start with <!doctype html> as its first line.' )
        ->and( $topic->bodyMarkdown )->toStartWith( '# The HTML doctype' )
        ->and( $topic->bodyMarkdown )->not->toContain( 'requirement:' )
        ->and( $topic->resourceUrl )->toBe( 'https://specification.website/spec/foundations/doctype/' )
        ->and( $topic->sourceUpdatedAt?->toIso8601String() )->toBe( '2026-07-27T00:00:00+00:00' )
        ->and( $topic->sources )->toBe( [ [
            'id'       => 'html-living-standard-the-doctype',
            'title'    => 'HTML Living Standard — The DOCTYPE',
            'resource' => 'https://html.spec.whatwg.org/multipage/syntax.html#the-doctype',
            'author'   => 'WHATWG',
            'mirror'   => '../references/html-living-standard-the-doctype.md',
        ] ] );
} );

it( 'returns null for documents that are not checks', function (): void {
    expect( ( new TopicParser() )->parse( 'references', 'mdn-doctype', topicDocument( [ 'type' => 'Reference' ] ) ) )->toBeNull();
} );

it( 'derives the slug from the path, not the front matter', function (): void {
    $topic = ( new TopicParser() )->parse( 'seo', 'robots-txt', topicDocument( [ 'category' => 'security' ] ) );

    expect( $topic->slug )->toBe( 'seo/robots-txt' )
        ->and( $topic->category )->toBe( 'seo' );
} );

it( 'falls back to the upstream URL when the resource points elsewhere', function ( string $resource ): void {
    $topic = ( new TopicParser() )->parse( 'foundations', 'doctype', topicDocument( [ 'resource' => $resource ] ) );

    expect( $topic->resourceUrl )->toBe( 'https://specification.website/spec/foundations/doctype/' );
} )->with( [
    'other origin'       => 'https://evil.example/spec/foundations/doctype/',
    'lookalike origin'   => 'https://specification.website.evil.example/x',
    'javascript url'     => '"javascript:alert(1)"',
] );

it( 'drops non-http source links', function (): void {
    $document = topicDocument( [ 'sources' => "\n  - id: bad\n    title: \"Bad\"\n    resource: \"javascript:alert(1)\"" ] );

    expect( ( new TopicParser() )->parse( 'foundations', 'doctype', $document )->sources )->toBe( [ [ 'id' => 'bad', 'title' => 'Bad' ] ] );
} );

it( 'rejects topics missing required keys', function ( string $key ): void {
    ( new TopicParser() )->parse( 'foundations', 'doctype', topicDocument( [ $key => null ] ) );
} )->with( [ 'title', 'description', 'requirement' ] )->throws( InvalidArchiveException::class, 'missing the' );

it( 'rejects unknown requirement values', function (): void {
    ( new TopicParser() )->parse( 'foundations', 'doctype', topicDocument( [ 'requirement' => 'mandatory' ] ) );
} )->throws( InvalidArchiveException::class, 'unknown requirement' );

it( 'rejects documents without front matter', function (): void {
    ( new TopicParser() )->parse( 'foundations', 'doctype', "# Just markdown\n" );
} )->throws( InvalidArchiveException::class, 'no YAML front matter' );

it( 'rejects invalid YAML front matter', function (): void {
    ( new TopicParser() )->parse( 'foundations', 'doctype', "---\ntitle: [unclosed\n---\nBody" );
} )->throws( InvalidArchiveException::class, 'not valid YAML' );

it( 'normalizes the conformance keyword', function ( string $value, ?string $expected ): void {
    expect( ( new TopicParser() )->parse( 'foundations', 'doctype', topicDocument( [ 'conformance' => $value ] ) )->conformance )->toBe( $expected );
} )->with( [
    'must'          => [ '"MUST"', 'MUST' ],
    'lower case'    => [ '"should not"', 'SHOULD NOT' ],
    'extra spacing' => [ '"MUST  NOT"', 'MUST NOT' ],
    'unknown'       => [ '"SOMETHING VERY LONG INDEED"', null ],
    'not a string'  => [ '[ MUST ]', null ],
] );

it( 'rejects titles longer than the column', function (): void {
    ( new TopicParser() )->parse( 'foundations', 'doctype', topicDocument( [ 'title' => '"' . str_repeat( 'x', 256 ) . '"' ] ) );
} )->throws( InvalidArchiveException::class, 'longer than 255' );

it( 'falls back to the derived URL when the resource is longer than the column', function (): void {
    $topic = ( new TopicParser() )->parse( 'foundations', 'doctype', topicDocument( [ 'resource' => 'https://specification.website/' . str_repeat( 'a', 600 ) ] ) );

    expect( $topic->resourceUrl )->toBe( 'https://specification.website/spec/foundations/doctype/' );
} );
