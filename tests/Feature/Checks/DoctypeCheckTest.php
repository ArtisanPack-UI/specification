<?php

declare( strict_types=1 );

use ArtisanPackUI\Specification\Checks\CheckRegistry;
use ArtisanPackUI\Specification\Checks\Foundations\DoctypeCheck;
use ArtisanPackUI\Specification\Checks\SpecContext;
use ArtisanPackUI\Specification\Enums\CheckScope;
use ArtisanPackUI\Specification\Enums\ResultOutcome;
use ArtisanPackUI\Specification\Enums\SpecStatus;

const PAGE_URL = 'https://example.test/';

/**
 * Builds a page context, optionally with an HTML writer.
 */
function doctypePage( ?string $html, ?Closure $writer = null ): SpecContext
{
    return SpecContext::page( PAGE_URL, $html, [], null === $writer ? [] : [ DoctypeCheck::HTML_WRITER => $writer ] );
}

it( 'declares the foundations/doctype topic as a required page check', function (): void {
    $check = new DoctypeCheck();

    expect( $check->slug() )->toBe( 'foundations/doctype' )
        ->and( $check->slug() )->toMatch( CheckRegistry::SLUG_PATTERN )
        ->and( $check->status() )->toBe( SpecStatus::Required )
        ->and( $check->scope() )->toBe( CheckScope::Page );
} );

it( 'passes pages that start with the HTML doctype', function ( string $html ): void {
    $result = ( new DoctypeCheck() )->run( doctypePage( $html ) );

    expect( $result->outcome )->toBe( ResultOutcome::Passed )
        ->and( $result->subject )->toBe( PAGE_URL )
        ->and( $result->details['found'] )->toStartWith( '<!' );
} )->with( [
    'lower case'           => [ "<!doctype html>\n<html></html>" ],
    'upper case'           => [ '<!DOCTYPE HTML><html></html>' ],
    'legacy-compat'        => [ '<!DOCTYPE html SYSTEM "about:legacy-compat"><html></html>' ],
    'leading whitespace'   => [ "\n\n  <!doctype html><html></html>" ],
    'byte order mark'      => [ "\u{FEFF}<!doctype html><html></html>" ],
    'leading comments'     => [ "<!-- build 42 -->\n<!-- x --><!doctype html><html></html>" ],
] );

it( 'fails pages without a valid leading doctype', function ( string $html, ?string $found, string $message ): void {
    $result = ( new DoctypeCheck() )->run( doctypePage( $html ) );

    expect( $result->outcome )->toBe( ResultOutcome::Failed )
        ->and( $result->status )->toBe( SpecStatus::Required )
        ->and( $result->message )->toContain( $message )
        ->and( $result->details )->toBe( [ 'found' => $found, 'expected' => '<!doctype html>' ] );
} )->with( [
    'missing'                 => [ '<html><head></head></html>', null, 'no doctype' ],
    'HTML 4.01'               => [ '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd"><html></html>', '<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">', 'legacy doctype' ],
    'XHTML'                   => [ '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN"><html></html>', '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN">', 'legacy doctype' ],
    'after markup'            => [ '<meta charset="utf-8"><!doctype html><html></html>', '<!doctype html>', 'not the first thing' ],
    'unclosed comment before' => [ '<!-- oops <!doctype html><html></html>', '<!doctype html>', 'not the first thing' ],
    'only in a script'        => [ "<html><head><script>var t = '<!DOCTYPE html>';</script></head></html>", null, 'no doctype' ],
    'only in a comment'       => [ '<html><!-- <!doctype html> --></html>', null, 'no doctype' ],
    'only in a textarea'      => [ '<html><body><textarea><!doctype html></textarea></body></html>', null, 'no doctype' ],
] );

it( 'skips pages without HTML', function ( ?string $html ): void {
    expect( ( new DoctypeCheck() )->run( doctypePage( $html ) )->outcome )->toBe( ResultOutcome::Skipped );
} )->with( [ 'null' => [ null ], 'blank' => [ "  \n" ] ] );

it( 'offers no autofix without an HTML writer', function (): void {
    expect( ( new DoctypeCheck() )->autofix( doctypePage( '<html></html>' ) ) )->toBeNull();
} );

it( 'offers no autofix when the page already passes or has no HTML', function ( ?string $html ): void {
    expect( ( new DoctypeCheck() )->autofix( doctypePage( $html, fn ( string $fixed ) => null ) ) )->toBeNull();
} )->with( [ 'passing' => [ '<!doctype html><html></html>' ], 'no HTML' => [ null ] ] );

it( 'describes and applies an autofix through the context writer', function ( string $html, string $expected, ?string $before ): void {
    $written = null;
    $fix     = ( new DoctypeCheck() )->autofix( doctypePage( $html, function ( string $fixed ) use ( &$written ): void {
        $written = $fixed;
    } ) );

    expect( $fix )->not->toBeNull()
        ->and( $fix->slug )->toBe( 'foundations/doctype' )
        ->and( $fix->details )->toBe( [ 'before' => $before, 'after' => '<!doctype html>', 'html' => $expected ] )
        ->and( $written )->toBeNull();

    $fix->apply();

    expect( $written )->toBe( $expected )
        ->and( ( new DoctypeCheck() )->run( doctypePage( $written ) )->outcome )->toBe( ResultOutcome::Passed );
} )->with( [
    'missing'                  => [ '<html></html>', "<!doctype html>\n<html></html>", null ],
    'legacy'                   => [ "<!-- c -->\n<!DOCTYPE html PUBLIC \"-//W3C//DTD XHTML 1.0 Strict//EN\"><html></html>", "<!-- c -->\n<!doctype html><html></html>", '<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN">' ],
    'misplaced'                => [ '<meta charset="utf-8"><!DOCTYPE html><html></html>', "<!doctype html>\n<meta charset=\"utf-8\"><html></html>", '<!DOCTYPE html>' ],
    'keeps BOM'                => [ "\u{FEFF}<html></html>", "\u{FEFF}<!doctype html>\n<html></html>", null ],
    'script'                   => [ "<html><head><script>var t = '<!DOCTYPE html>';</script></head></html>", "<!doctype html>\n<html><head><script>var t = '<!DOCTYPE html>';</script></head></html>", null ],
    'comment'                  => [ '<html><!-- <!doctype html> --></html>', "<!doctype html>\n<html><!-- <!doctype html> --></html>", null ],
    'misplaced after a script' => [ '<script>x("<!doctype html>")</script><!doctype html><html></html>', "<!doctype html>\n<script>x(\"<!doctype html>\")</script><html></html>", '<!doctype html>' ],
] );

it( 'runs through the registry and runner once registered', function (): void {
    specification()->registerCheck( DoctypeCheck::class );

    $results = specification()->run( doctypePage( '<html></html>' ) );

    expect( specification()->checks()->has( 'foundations/doctype' ) )->toBeTrue()
        ->and( $results )->toHaveCount( 1 )
        ->and( $results[0]->outcome )->toBe( ResultOutcome::Failed )
        ->and( specification()->report( $results )->site()->required->total )->toBe( 1 );
} );

it( 'is not registered by default', function (): void {
    expect( specification()->checks()->has( 'foundations/doctype' ) )->toBeFalse();
} );
