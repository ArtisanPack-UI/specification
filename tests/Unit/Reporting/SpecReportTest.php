<?php

declare( strict_types=1 );

use ArtisanPackUI\Specification\Checks\SpecResult;
use ArtisanPackUI\Specification\Enums\CheckScope;
use ArtisanPackUI\Specification\Enums\ResultOutcome;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use ArtisanPackUI\Specification\Reporting\SpecReport;
use ArtisanPackUI\Specification\Reporting\TierBreakdown;
use ArtisanPackUI\Specification\Reporting\TierScore;

function result( string $slug, SpecStatus $status, ResultOutcome $outcome, CheckScope $scope = CheckScope::Page, string $subject = 'https://example.test/' ): SpecResult
{
    return new SpecResult( $slug, $status, $outcome, $scope, $subject );
}

it( 'scores each tier separately with no overall percentage', function (): void {
    $breakdown = TierBreakdown::fromResults( [
        result( 'foundations/doctype', SpecStatus::Required, ResultOutcome::Passed ),
        result( 'foundations/title', SpecStatus::Required, ResultOutcome::Passed ),
        result( 'foundations/meta-charset', SpecStatus::Required, ResultOutcome::Failed ),
        result( 'seo/canonical', SpecStatus::Recommended, ResultOutcome::Passed ),
        result( 'foundations/popover-api', SpecStatus::Optional, ResultOutcome::Failed ),
        result( 'security/x-xss-protection', SpecStatus::Avoid, ResultOutcome::Failed ),
        result( 'security/hsts-preload', SpecStatus::Avoid, ResultOutcome::Passed ),
        result( 'seo/hreflang', SpecStatus::Recommended, ResultOutcome::Skipped ),
        result( 'seo/sitemaps', SpecStatus::Required, ResultOutcome::Errored ),
    ] );

    expect( $breakdown->toArray() )->toBe( [
        'required_met'    => 66.7,
        'recommended_met' => 100.0,
        'optional_met'    => 0.0,
        'avoid_present'   => 1,
        'required'        => [ 'met' => 2, 'total' => 3, 'percent' => 66.7 ],
        'recommended'     => [ 'met' => 1, 'total' => 1, 'percent' => 100.0 ],
        'optional'        => [ 'met' => 0, 'total' => 1, 'percent' => 0.0 ],
        'avoid'           => [ 'present' => 1, 'checked' => 2 ],
        'skipped'         => 1,
        'errored'         => 1,
    ] )
        ->and( $breakdown->toArray() )->not->toHaveKey( 'overall' );
} );

it( 'reports null rather than 100 percent for an empty tier', function (): void {
    expect( ( new TierScore() )->percent() )->toBeNull()
        ->and( TierBreakdown::fromResults( [] )->toArray()['required_met'] )->toBeNull()
        ->and( TierBreakdown::fromResults( [] )->avoidPresent )->toBe( 0 );
} );

it( 'groups results by site, category, and page', function (): void {
    $report = SpecReport::fromResults( [
        result( 'foundations/doctype', SpecStatus::Required, ResultOutcome::Passed, CheckScope::Page, 'https://example.test/' ),
        result( 'foundations/doctype', SpecStatus::Required, ResultOutcome::Failed, CheckScope::Page, 'https://example.test/about' ),
        result( 'accessibility/image-alt-text', SpecStatus::Required, ResultOutcome::Failed, CheckScope::Block, 'https://example.test/about' ),
        result( 'seo/robots-txt', SpecStatus::Required, ResultOutcome::Passed, CheckScope::Site, 'https://example.test' ),
        result( 'security/x-xss-protection', SpecStatus::Avoid, ResultOutcome::Failed, CheckScope::Server, 'https://example.test' ),
    ] );

    expect( $report->site()->required->met )->toBe( 2 )
        ->and( $report->site()->required->total )->toBe( 4 )
        ->and( $report->site()->avoidPresent )->toBe( 1 )
        ->and( array_keys( $report->categories() ) )->toBe( [ 'accessibility', 'foundations', 'security', 'seo' ] )
        ->and( $report->category( 'foundations' )->required->percent() )->toBe( 50.0 )
        ->and( $report->category( 'security' )->avoidPresent )->toBe( 1 )
        ->and( $report->category( 'privacy' ) )->toBeNull()
        ->and( array_keys( $report->pages() ) )->toBe( [ 'https://example.test/', 'https://example.test/about' ] )
        ->and( $report->page( 'https://example.test/about' )->required->toArray() )->toBe( [ 'met' => 0, 'total' => 2, 'percent' => 0.0 ] )
        ->and( $report->page( 'https://example.test' ) )->toBeNull()
        ->and( $report->results() )->toHaveCount( 5 );
} );

it( 'serializes to JSON with the four tiers at every level', function (): void {
    $report = SpecReport::fromResults( [ result( 'foundations/doctype', SpecStatus::Required, ResultOutcome::Passed ) ] );
    $json   = json_decode( (string) json_encode( $report ), true );

    expect( $json )->toHaveKeys( [ 'site', 'categories', 'pages' ] )
        ->and( $json['site'] )->toHaveKeys( [ 'required_met', 'recommended_met', 'optional_met', 'avoid_present' ] )
        ->and( $json['categories']['foundations']['required_met'] )->toEqual( 100 )
        ->and( $json['pages']['https://example.test/']['required_met'] )->toEqual( 100 );
} );
