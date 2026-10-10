<?php

declare( strict_types=1 );

use ArtisanPackUI\Specification\Checks\SpecContext;
use ArtisanPackUI\Specification\Enums\ResultOutcome;
use Tests\Support\StubCheck;

it( 'reports on results from a run', function (): void {
    specification()
        ->registerCheck( new StubCheck() )
        ->registerCheck( new class () extends StubCheck {
            public function __construct()
            {
                parent::__construct( 'foundations/title', outcome: ResultOutcome::Failed );
            }
        } );

    $report = specification()->report( specification()->run( SpecContext::page( 'https://example.test/' ) ) );

    expect( $report->page( 'https://example.test/' )->required->percent() )->toBe( 50.0 )
        ->and( $report->site()->toArray()['required_met'] )->toBe( 50.0 );
} );
