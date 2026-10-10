<?php

declare( strict_types=1 );

namespace Tests\Support;

use ArtisanPackUI\Specification\Checks\SpecContext;
use ArtisanPackUI\Specification\Checks\SpecFix;
use ArtisanPackUI\Specification\Checks\SpecResult;
use ArtisanPackUI\Specification\Contracts\SpecCheck;
use ArtisanPackUI\Specification\Enums\CheckScope;
use ArtisanPackUI\Specification\Enums\ResultOutcome;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use Closure;

/**
 * Configurable SpecCheck for registry, runner, and report tests.
 */
class StubCheck implements SpecCheck
{
    /**
     * @param  Closure(SpecContext, SpecCheck): SpecResult|null  $runner
     */
    public function __construct(
        private string $slug = 'foundations/doctype',
        private SpecStatus $status = SpecStatus::Required,
        private CheckScope $scope = CheckScope::Page,
        private ResultOutcome $outcome = ResultOutcome::Passed,
        private ?Closure $runner = null,
    ) {
    }

    public function slug(): string
    {
        return $this->slug;
    }

    public function status(): SpecStatus
    {
        return $this->status;
    }

    public function scope(): CheckScope
    {
        return $this->scope;
    }

    public function run( SpecContext $context ): SpecResult
    {
        if ( null !== $this->runner ) {
            return ( $this->runner )( $context, $this );
        }

        return new SpecResult( $this->slug, $this->status, $this->outcome, $context->scope, $context->subject() );
    }

    public function autofix( SpecContext $context ): ?SpecFix
    {
        return null;
    }
}
