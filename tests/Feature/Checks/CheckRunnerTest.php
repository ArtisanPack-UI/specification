<?php

declare( strict_types=1 );

use ArtisanPackUI\Specification\Checks\CheckRunner;
use ArtisanPackUI\Specification\Checks\SpecContext;
use ArtisanPackUI\Specification\Checks\SpecFix;
use ArtisanPackUI\Specification\Checks\SpecResult;
use ArtisanPackUI\Specification\Contracts\SpecCheck;
use ArtisanPackUI\Specification\Enums\CheckScope;
use ArtisanPackUI\Specification\Enums\ResultOutcome;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use Tests\Support\StubCheck;

it( 'runs only checks whose scope matches the context', function (): void {
    specification()
        ->registerCheck( new StubCheck( 'foundations/doctype', scope: CheckScope::Page ) )
        ->registerCheck( new class () extends StubCheck {
            public function __construct()
            {
                parent::__construct( 'security/hsts', SpecStatus::Recommended, CheckScope::Server );
            }
        } );

    $results = specification()->run( SpecContext::page( 'https://example.test/about', '<!doctype html>' ) );

    expect( $results )->toHaveCount( 1 )
        ->and( $results[0]->slug )->toBe( 'foundations/doctype' )
        ->and( $results[0]->subject )->toBe( 'https://example.test/about' )
        ->and( $results[0]->scope )->toBe( CheckScope::Page );
} );

it( 'limits runs by slug and category', function (): void {
    specification()
        ->registerCheck( new StubCheck( 'foundations/doctype' ) )
        ->registerCheck( new class () extends StubCheck {
            public function __construct()
            {
                parent::__construct( 'seo/meta-robots' );
            }
        } );

    $context = SpecContext::page( 'https://example.test/' );

    expect( specification()->run( $context, slugs: [ 'seo/meta-robots' ] ) )->toHaveCount( 1 )
        ->and( specification()->run( $context, category: 'foundations' )[0]->slug )->toBe( 'foundations/doctype' )
        ->and( specification()->run( $context, slugs: [] ) )->toBe( [] );
} );

it( 'turns a throwing check into an errored result', function (): void {
    specification()->registerCheck( new StubCheck( runner: fn () => throw new RuntimeException( 'boom' ) ) );

    $results = app( CheckRunner::class )->run( SpecContext::page( 'https://example.test/' ) );

    expect( $results[0]->outcome )->toBe( ResultOutcome::Errored )
        ->and( $results[0]->message )->toContain( 'boom' )
        ->and( $results[0]->details['exception'] )->toBe( RuntimeException::class );
} );

it( 'keeps running the other checks when one check is invalid', function (): void {
    specification()
        ->registerCheck( new StubCheck( 'Not A Slug' ) )
        ->registerCheck( new class () extends StubCheck {
            public function __construct()
            {
                parent::__construct( 'seo/meta-robots' );
            }
        } );

    $results = specification()->run( SpecContext::page( 'https://example.test/' ) );

    expect( $results )->toHaveCount( 1 )
        ->and( $results[0]->slug )->toBe( 'seo/meta-robots' )
        ->and( specification()->checks()->invalid() )->toHaveKey( StubCheck::class );
} );

it( 'rejects results reported for another slug', function (): void {
    specification()->registerCheck( new StubCheck( runner: fn ( SpecContext $context ): SpecResult => new SpecResult(
        'seo/other',
        SpecStatus::Required,
        ResultOutcome::Passed,
        $context->scope,
        $context->subject(),
    ) ) );

    $result = specification()->run( SpecContext::page( 'https://example.test/' ) )[0];

    expect( $result->outcome )->toBe( ResultOutcome::Errored )
        ->and( $result->slug )->toBe( 'foundations/doctype' );
} );

it( 'rejects results that misreport the status tier', function (): void {
    specification()->registerCheck( new StubCheck( runner: fn ( SpecContext $context ): SpecResult => new SpecResult(
        'foundations/doctype',
        SpecStatus::Optional,
        ResultOutcome::Failed,
        $context->scope,
        $context->subject(),
    ) ) );

    expect( specification()->run( SpecContext::page( 'https://example.test/' ) )[0]->outcome )->toBe( ResultOutcome::Errored );
} );

it( 'builds results from the check and context', function (): void {
    $check   = new StubCheck( 'security/x-xss-protection', SpecStatus::Avoid, CheckScope::Server );
    $context = SpecContext::server( 'https://example.test', [ 'X-XSS-Protection' => '1; mode=block' ] );

    $result = SpecResult::failed( $check, $context, 'Header present.', [ 'value' => $context->header( 'x-xss-protection' ) ] );

    expect( $result->toArray() )->toBe( [
        'slug'    => 'security/x-xss-protection',
        'status'  => 'avoid',
        'outcome' => 'failed',
        'scope'   => 'server',
        'subject' => 'https://example.test',
        'message' => 'Header present.',
        'details' => [ 'value' => '1; mode=block' ],
    ] )
        ->and( $result->category() )->toBe( 'security' )
        ->and( $result->isMet() )->toBeFalse();
} );

it( 'exposes context headers, data, and subject', function (): void {
    $context = new SpecContext( CheckScope::Block, 'https://example.test/post', null, [ 'Content-Type' => [ 'text/html', 'x' ] ], [ 'block' => [ 'id' => 7 ] ] );

    expect( $context->header( 'content-type' ) )->toBe( 'text/html' )
        ->and( $context->header( 'missing' ) )->toBeNull()
        ->and( $context->get( 'block.id' ) )->toBe( 7 )
        ->and( $context->subject() )->toBe( 'https://example.test/post' )
        ->and( ( new SpecContext( CheckScope::Section, data: [ 'subject' => 'home#hero' ] ) )->subject() )->toBe( 'home#hero' )
        ->and( SpecContext::site( 'https://example.test' )->scope )->toBe( CheckScope::Site );
} );

it( 'applies an autofix only when asked', function (): void {
    $applied = false;
    $check   = new class ( $applied ) extends StubCheck {
        public function __construct( private bool &$applied )
        {
            parent::__construct();
        }

        public function autofix( SpecContext $context ): ?SpecFix
        {
            return new SpecFix( $this->slug(), 'Prepend <!doctype html>.', function (): void {
                $this->applied = true;
            } );
        }
    };

    $fix = $check->autofix( SpecContext::page( 'https://example.test/' ) );

    expect( $fix->description )->toBe( 'Prepend <!doctype html>.' )
        ->and( $applied )->toBeFalse();

    $fix->apply();

    expect( $applied )->toBeTrue()
        ->and( ( new StubCheck() )->autofix( SpecContext::page( 'https://example.test/' ) ) )->toBeNull()
        ->and( $check )->toBeInstanceOf( SpecCheck::class );
} );
