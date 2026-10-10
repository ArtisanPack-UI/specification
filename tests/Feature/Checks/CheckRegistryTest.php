<?php

declare( strict_types=1 );

use ArtisanPackUI\Specification\Checks\CheckRegistry;
use ArtisanPackUI\Specification\Enums\CheckScope;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use ArtisanPackUI\Specification\Exceptions\InvalidCheckException;
use Tests\Support\StubCheck;

it( 'registers check instances and class names', function (): void {
    $registry = app( CheckRegistry::class );
    $instance = new class () extends StubCheck {
        public function __construct()
        {
            parent::__construct( 'seo/xml-sitemaps', SpecStatus::Recommended, CheckScope::Site );
        }
    };

    $registry->register( $instance )->register( StubCheck::class );

    expect( $registry->all() )->toHaveCount( 2 )
        ->and( $registry->slugs() )->toBe( [ 'foundations/doctype', 'seo/xml-sitemaps' ] )
        ->and( $registry->has( 'seo/xml-sitemaps' ) )->toBeTrue()
        ->and( $registry->has( 'seo/robots-txt' ) )->toBeFalse();
} );

it( 'registers each check class once', function (): void {
    $registry = app( CheckRegistry::class );

    $registry->registerMany( [ StubCheck::class, '\\' . StubCheck::class, new StubCheck() ] );

    expect( $registry->all() )->toHaveCount( 1 );
} );

it( 'resolves class-name registrations lazily through the container', function (): void {
    $resolved = 0;
    app()->bind( StubCheck::class, function () use ( &$resolved ): StubCheck {
        $resolved++;

        return new StubCheck();
    } );

    $registry = app( CheckRegistry::class )->register( StubCheck::class );

    expect( $resolved )->toBe( 0 );

    $registry->all();
    $registry->all();

    expect( $resolved )->toBe( 1 );
} );

it( 'filters by slug, category, and scope', function (): void {
    $registry = app( CheckRegistry::class )->registerMany( [
        new StubCheck(),
        new class () extends StubCheck {
            public function __construct()
            {
                parent::__construct( 'foundations/title', SpecStatus::Required, CheckScope::Page );
            }
        },
        new class () extends StubCheck {
            public function __construct()
            {
                parent::__construct( 'security/hsts', SpecStatus::Recommended, CheckScope::Server );
            }
        },
    ] );

    expect( $registry->forSlug( 'foundations/doctype' ) )->toHaveCount( 1 )
        ->and( $registry->forCategory( 'foundations' ) )->toHaveCount( 2 )
        ->and( $registry->forCategory( 'found' ) )->toBe( [] )
        ->and( $registry->forScope( CheckScope::Server )[0]->slug() )->toBe( 'security/hsts' )
        ->and( $registry->forScope( CheckScope::Block ) )->toBe( [] );
} );

it( 'discovers checks from config and container tags', function (): void {
    config()->set( 'artisanpack.specification.checks', [ StubCheck::class ] );
    app()->forgetInstance( CheckRegistry::class );

    app()->bind( 'tagged-check', fn (): StubCheck => new class () extends StubCheck {
        public function __construct()
        {
            parent::__construct( 'privacy/gpc' );
        }
    } );
    app()->tag( [ 'tagged-check' ], CheckRegistry::TAG );

    expect( app( CheckRegistry::class )->slugs() )->toBe( [ 'foundations/doctype', 'privacy/gpc' ] )
        ->and( specification()->checks() )->toBe( app( CheckRegistry::class ) );
} );

it( 'rejects classes that do not implement SpecCheck', function (): void {
    app( CheckRegistry::class )->register( stdClass::class );
} )->throws( InvalidCheckException::class, 'must implement' );

it( 'rejects tagged services that do not implement SpecCheck', function (): void {
    app()->bind( 'not-a-check', fn (): stdClass => new stdClass() );
    app()->tag( [ 'not-a-check' ], CheckRegistry::TAG );

    app( CheckRegistry::class )->all();
} )->throws( InvalidCheckException::class, 'must implement' );

it( 'rejects checks with malformed slugs', function ( string $slug ): void {
    app( CheckRegistry::class )->register( new StubCheck( $slug ) )->all();
} )->with( [ 'doctype', 'Foundations/doctype', 'foundations/', 'foundations/doc type', 'a/b/c', '../etc' ] )
    ->throws( InvalidCheckException::class, 'invalid slug' );
