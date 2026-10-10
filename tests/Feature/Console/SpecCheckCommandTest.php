<?php

declare( strict_types=1 );

use ArtisanPackUI\Specification\Checks\Foundations\DoctypeCheck;
use ArtisanPackUI\Specification\Checks\SpecContext;
use ArtisanPackUI\Specification\Checks\SpecResult;
use ArtisanPackUI\Specification\Enums\CheckScope;
use ArtisanPackUI\Specification\Enums\ResultOutcome;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Support\StubCheck;

const SITE_URL = 'https://example.test';

it( 'warns when no checks are registered', function (): void {
    Http::fake();

    $this->artisan( 'keystone:spec-check', [ '--url' => SITE_URL ] )
        ->expectsOutputToContain( 'No specification checks are registered' )
        ->assertSuccessful();

    Http::assertNothingSent();
} );

it( 'passes when the fetched page has a doctype', function (): void {
    specification()->registerCheck( DoctypeCheck::class );
    Http::fake( [ SITE_URL => Http::response( "<!doctype html>\n<html lang=\"en\"></html>", 200, [ 'Content-Type' => 'text/html' ] ) ] );

    $this->artisan( 'keystone:spec-check', [ '--category' => 'foundations', '--url' => SITE_URL ] )
        ->expectsOutputToContain( 'The page starts with the HTML doctype.' )
        ->expectsOutputToContain( '1 of 1 met (100%)' )
        ->assertSuccessful();

    Http::assertSentCount( 1 );
} );

it( 'fails when a required check fails', function (): void {
    specification()->registerCheck( DoctypeCheck::class );
    Http::fake( [ SITE_URL => Http::response( '<html></html>' ) ] );

    $this->artisan( 'keystone:spec-check', [ '--url' => SITE_URL ] )
        ->expectsOutputToContain( 'no doctype' )
        ->expectsOutputToContain( '0 of 1 met (0%)' )
        ->assertFailed();
} );

it( 'runs the usable checks and fails when a registered check is invalid', function (): void {
    specification()->registerCheck( DoctypeCheck::class )->registerCheck( new StubCheck( 'Bad Slug' ) );
    Http::fake( [ SITE_URL => Http::response( "<!doctype html>\n<html></html>" ) ] );

    $this->artisan( 'keystone:spec-check', [ '--url' => SITE_URL ] )
        ->expectsOutputToContain( 'could not be used' )
        ->expectsOutputToContain( 'The page starts with the HTML doctype.' )
        ->assertFailed();
} );

it( 'fails when every registered check is invalid', function (): void {
    specification()->registerCheck( new StubCheck( 'Bad Slug' ) );
    Http::fake();

    $this->artisan( 'keystone:spec-check', [ '--url' => SITE_URL ] )
        ->expectsOutputToContain( 'could not be used' )
        ->assertFailed();

    Http::assertNothingSent();
} );

it( 'defaults to app.url', function (): void {
    config()->set( 'app.url', 'https://app.example.test' );
    specification()->registerCheck( DoctypeCheck::class );
    Http::fake( [ 'https://app.example.test' => Http::response( '<!doctype html><html></html>' ) ] );

    $this->artisan( 'keystone:spec-check' )->assertSuccessful();

    Http::assertSent( fn ( Request $request ): bool => 'https://app.example.test' === $request->url() );
} );

it( 'only runs checks in the selected category or slugs', function (): void {
    specification()->registerCheck( DoctypeCheck::class );
    specification()->registerCheck( new StubCheck( 'security/x-xss-protection', SpecStatus::Avoid, CheckScope::Server, ResultOutcome::Failed ) );
    Http::fake( [ SITE_URL => Http::response( '<!doctype html><html></html>' ) ] );

    $this->artisan( 'keystone:spec-check', [ '--category' => 'foundations', '--url' => SITE_URL ] )
        ->doesntExpectOutputToContain( 'security/x-xss-protection' )
        ->assertSuccessful();

    $this->artisan( 'keystone:spec-check', [ '--slug' => [ 'security/x-xss-protection' ], '--url' => SITE_URL ] )
        ->doesntExpectOutputToContain( 'foundations/doctype' )
        ->expectsOutputToContain( '1 of 1' )
        ->assertFailed();
} );

it( 'feeds site, page, and server contexts from one fetch', function (): void {
    $seen   = [];
    $runner = function ( SpecContext $context, StubCheck $check ) use ( &$seen ): SpecResult {
        $seen[ $context->scope->value ] = [ $context->url, $context->html, $context->header( 'x-test' ) ];

        return SpecResult::passed( $check, $context );
    };

    specification()
        ->registerCheck( new StubCheck( 'seo/site-check', SpecStatus::Recommended, CheckScope::Site, runner: $runner ) )
        ->registerCheck( new class ( $runner ) extends StubCheck {
            public function __construct( Closure $runner )
            {
                parent::__construct( 'seo/page-check', SpecStatus::Recommended, CheckScope::Page, runner: $runner );
            }
        } )
        ->registerCheck( new class ( $runner ) extends StubCheck {
            public function __construct( Closure $runner )
            {
                parent::__construct( 'security/server-check', SpecStatus::Recommended, CheckScope::Server, runner: $runner );
            }
        } );
    Http::fake( [ SITE_URL => Http::response( '<p>hi</p>', 200, [ 'X-Test' => 'yes' ] ) ] );

    $this->artisan( 'keystone:spec-check', [ '--url' => SITE_URL ] )->assertSuccessful();

    expect( $seen )->toBe( [
        'site'   => [ SITE_URL, null, null ],
        'page'   => [ SITE_URL, '<p>hi</p>', 'yes' ],
        'server' => [ SITE_URL, null, 'yes' ],
    ] );

    Http::assertSentCount( 1 );
} );

it( 'does not fetch the URL when only site checks are selected', function (): void {
    specification()->registerCheck( new StubCheck( 'seo/site-check', SpecStatus::Required, CheckScope::Site ) );
    Http::fake();

    $this->artisan( 'keystone:spec-check', [ '--url' => SITE_URL ] )->assertSuccessful();

    Http::assertNothingSent();
} );

it( 'notes section and block checks it cannot run', function (): void {
    specification()->registerCheck( new StubCheck( 'accessibility/image-alt-text', SpecStatus::Required, CheckScope::Block ) );
    Http::fake();

    $this->artisan( 'keystone:spec-check', [ '--url' => SITE_URL ] )
        ->expectsOutputToContain( '1 section or block check was not run' )
        ->assertSuccessful();
} );

it( 'tolerates failed recommended checks', function (): void {
    specification()->registerCheck( new StubCheck( 'seo/recommended', SpecStatus::Recommended, CheckScope::Site, ResultOutcome::Failed ) );

    $this->artisan( 'keystone:spec-check', [ '--url' => SITE_URL ] )
        ->expectsOutputToContain( 'failed' )
        ->assertSuccessful();
} );

it( 'fails when a check errors or an avoided practice is present', function ( StubCheck $check, string $output ): void {
    specification()->registerCheck( $check );

    $this->artisan( 'keystone:spec-check', [ '--url' => SITE_URL ] )
        ->expectsOutputToContain( $output )
        ->assertFailed();
} )->with( [
    'errored'       => [ fn (): StubCheck => new StubCheck( 'seo/broken', SpecStatus::Optional, CheckScope::Site, runner: fn () => throw new RuntimeException( 'boom' ) ), 'boom' ],
    'avoid present' => [ fn (): StubCheck => new StubCheck( 'security/x-xss-protection', SpecStatus::Avoid, CheckScope::Site, ResultOutcome::Failed ), '1 of 1' ],
] );

it( 'fails when the URL cannot be fetched', function (): void {
    specification()->registerCheck( DoctypeCheck::class );
    Http::fake( fn () => throw new ConnectionException( 'connection refused' ) );

    $this->artisan( 'keystone:spec-check', [ '--url' => SITE_URL ] )
        ->expectsOutputToContain( 'connection refused' )
        ->assertFailed();
} );

it( 'rejects invalid options', function ( array $options ): void {
    $this->artisan( 'keystone:spec-check', $options )->assertExitCode( 2 );
} )->with( [
    'non-http URL'     => [ [ '--url' => 'ftp://example.test' ] ],
    'not a URL'        => [ [ '--url' => 'example' ] ],
    'invalid category' => [ [ '--url' => SITE_URL, '--category' => '../seo' ] ],
] );

it( 'warns when the URL returns an error status', function (): void {
    specification()->registerCheck( DoctypeCheck::class );
    Http::fake( [ SITE_URL => Http::response( "<!doctype html>\n<html></html>", 500 ) ] );

    $this->artisan( 'keystone:spec-check', [ '--url' => SITE_URL ] )
        ->expectsOutputToContain( 'returned HTTP 500' )
        ->assertSuccessful();
} );

it( 'does not warn for a successful response', function (): void {
    specification()->registerCheck( DoctypeCheck::class );
    Http::fake( [ SITE_URL => Http::response( "<!doctype html>\n<html></html>" ) ] );

    $this->artisan( 'keystone:spec-check', [ '--url' => SITE_URL ] )
        ->doesntExpectOutputToContain( 'returned HTTP' )
        ->assertSuccessful();
} );
