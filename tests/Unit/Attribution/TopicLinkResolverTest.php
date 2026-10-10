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
    'angle brackets in hash'  => [ '/spec/security/hsts/#x<bar>', 'https://specification.website/spec/security/hsts/#x%3Cbar%3E' ],
    'quote in sibling hash'   => [ 'html-lang.md#a"b', 'https://specification.website/spec/foundations/html-lang/#a%22b' ],
    'reference with hash'     => [ '../references/html-living-standard-the-doctype.md#x<y', 'https://html.spec.whatwg.org/multipage/syntax.html#the-doctype#x%3Cy' ],
    'angle bracket in path'   => [ '/a<b', 'https://specification.website/a%3Cb' ],
    'existing escape kept'    => [ '/a%20b#c%3Cd', 'https://specification.website/a%20b#c%3Cd' ],
    'stray percent encoded'   => [ '/a%zz', 'https://specification.website/a%25zz' ],
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

it( 'keeps rewritten reference definitions valid when the destination has angle brackets', function (): void {
    expect( doctypeLinkResolver()->rewriteMarkdown( '[ref]: /spec/security/hsts/#x<bar>' ) )
        ->toBe( '[ref]: <https://specification.website/spec/security/hsts/#x%3Cbar%3E>' );
} );

it( 'rewrites inline links whose destination contains angle brackets', function (): void {
    expect( doctypeLinkResolver()->rewriteMarkdown( 'See [a](/spec/security/hsts/#x<bar>) and [b](</spec/a b/>).' ) )
        ->toBe( 'See [a](<https://specification.website/spec/security/hsts/#x%3Cbar%3E>) and [b](<https://specification.website/spec/a%20b/>).' );
} );
