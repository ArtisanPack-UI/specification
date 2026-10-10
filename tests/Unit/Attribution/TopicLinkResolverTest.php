<?php

declare( strict_types=1 );

use ArtisanPackUI\Specification\Attribution\TopicLinkResolver;

function doctypeLinkResolver(): TopicLinkResolver
{
    return new TopicLinkResolver( 'foundations', 'https://specification.website/spec/foundations/doctype/', [
        [
            'id'       => 'html-living-standard-the-doctype',
            'resource' => 'https://html.spec.whatwg.org/multipage/syntax.html#the-doctype',
            'mirror'   => '../references/html-living-standard-the-doctype.md',
        ],
        [
            'id'       => 'unsafe-source',
            'resource' => 'javascript:alert(1)',
        ],
    ] );
}

it( 'resolves link destinations to absolute URLs', function ( string $url, ?string $expected ): void {
    expect( doctypeLinkResolver()->resolve( $url ) )->toBe( $expected );
} )->with( [
    'reference mirror'        => [ '../references/html-living-standard-the-doctype.md', 'https://html.spec.whatwg.org/multipage/syntax.html#the-doctype' ],
    'unknown reference'       => [ '../references/unknown.md', null ],
    'unsafe source resource'  => [ '../references/unsafe-source.md', null ],
    'root-relative spec page' => [ '/spec/security/hsts/', 'https://specification.website/spec/security/hsts/' ],
    'root-relative with hash' => [ '/spec/security/hsts/#max-age', 'https://specification.website/spec/security/hsts/#max-age' ],
    'root-relative file'      => [ '/llms.txt', 'https://specification.website/llms.txt' ],
    'sibling topic'           => [ 'html-lang.md', 'https://specification.website/spec/foundations/html-lang/' ],
    'other category topic'    => [ '../security/hsts.md#preload', 'https://specification.website/spec/security/hsts/#preload' ],
    'category index'          => [ 'index.md', null ],
    'escaping the bundle'     => [ '../../../etc/passwd.md', null ],
    'relative to the page'    => [ '../html-lang/', 'https://specification.website/spec/foundations/html-lang/' ],
    'fragment'                => [ '#verification', '#verification' ],
    'absolute https'          => [ 'https://example.com/a', 'https://example.com/a' ],
    'protocol-relative'       => [ '//example.com/a', 'https://example.com/a' ],
    'mailto'                  => [ 'mailto:hello@example.com', 'mailto:hello@example.com' ],
    'javascript'              => [ 'javascript:alert(1)', null ],
    'data'                    => [ 'data:text/html,hi', null ],
    'empty'                   => [ '', null ],
] );

it( 'rewrites Markdown links outside code', function (): void {
    $markdown = implode( "\n", [
        'See [the standard](../references/html-living-standard-the-doctype.md) and [HSTS](/spec/security/hsts/ "HSTS").',
        'Drop [this](../references/unknown.md) and [that](javascript:alert(1)).',
        'Keep `[code](../references/unknown.md)` as is.',
        '[ref]: ../security/hsts.md',
        '',
        '```md',
        '[fenced](../references/unknown.md)',
        '```',
        '',
        '    [indented](../references/unknown.md)',
    ] );

    expect( doctypeLinkResolver()->rewriteMarkdown( $markdown ) )->toBe( implode( "\n", [
        'See [the standard](<https://html.spec.whatwg.org/multipage/syntax.html#the-doctype>) and [HSTS](<https://specification.website/spec/security/hsts/> "HSTS").',
        'Drop this and that.',
        'Keep `[code](../references/unknown.md)` as is.',
        '[ref]: <https://specification.website/spec/security/hsts/>',
        '',
        '```md',
        '[fenced](../references/unknown.md)',
        '```',
        '',
        '    [indented](../references/unknown.md)',
    ] ) );
} );

it( 'rewrites Markdown links whose text contains code spans', function (): void {
    expect( doctypeLinkResolver()->rewriteMarkdown( 'Mark the rest [`inert`](/spec/accessibility/inert-attribute/) and see [`/llms.txt`](/llms.txt).' ) )
        ->toBe( 'Mark the rest [`inert`](<https://specification.website/spec/accessibility/inert-attribute/>) and see [`/llms.txt`](<https://specification.website/llms.txt>).' );
} );
