<?php

/**
 * SpecCheckCommand.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Console\Commands;

use ArtisanPackUI\Specification\Checks\CheckRegistry;
use ArtisanPackUI\Specification\Checks\CheckRunner;
use ArtisanPackUI\Specification\Checks\SpecContext;
use ArtisanPackUI\Specification\Checks\SpecResult;
use ArtisanPackUI\Specification\Console\Concerns\SanitizesOutput;
use ArtisanPackUI\Specification\Contracts\SpecCheck;
use ArtisanPackUI\Specification\Enums\CheckScope;
use ArtisanPackUI\Specification\Enums\ResultOutcome;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use ArtisanPackUI\Specification\Reporting\SpecReport;
use ArtisanPackUI\Specification\Reporting\TierScore;
use Illuminate\Console\Command;
use Illuminate\Http\Client\Factory as HttpFactory;
use Throwable;

/**
 * Runs the registered checks against a URL from the command line.
 *
 * Site, Page, and Server checks run; the page is fetched once and its HTML
 * and response headers feed the Page and Server contexts. Section and Block
 * checks need editor context and are skipped. The command fails when any
 * Required check fails, any Avoid practice is present, any check errors, or
 * any registered check could not be used, so it can gate a CI pipeline.
 *
 * @since 1.0.0
 */
class SpecCheckCommand extends Command
{
    use SanitizesOutput;

    /**
     * The name and signature of the console command.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $signature = 'keystone:spec-check
        {--category= : Only run checks in this category, e.g. foundations}
        {--slug=* : Only run checks for these topic slugs}
        {--url= : The URL to check (defaults to app.url)}
        {--timeout=15 : Seconds to wait when fetching the URL}';

    /**
     * The console command description.
     *
     * @since 1.0.0
     *
     * @var string
     */
    protected $description = 'Run the registered specification checks against a URL';

    /**
     * Executes the console command.
     *
     * @since 1.0.0
     *
     * @param  CheckRegistry  $registry  The check registry.
     * @param  CheckRunner    $runner    The check runner.
     * @param  HttpFactory    $http      The HTTP client factory.
     *
     * @return int
     */
    public function handle( CheckRegistry $registry, CheckRunner $runner, HttpFactory $http ): int
    {
        $category = $this->option( 'category' );
        $category = is_string( $category ) && '' !== $category ? strtolower( $category ) : null;
        $slugs    = array_values( array_filter( (array) $this->option( 'slug' ), static fn ( mixed $slug ): bool => is_string( $slug ) && '' !== $slug ) );
        $slugs    = [] === $slugs ? null : $slugs;
        $url      = (string) ( $this->option( 'url' ) ?: config( 'app.url' ) );

        if ( null !== $category && 1 !== preg_match( '#^[a-z0-9]+(?:-[a-z0-9]+)*$#', $category ) ) {
            $this->components->error( __( 'The --category option must be a category slug such as "foundations".' ) );

            return self::INVALID;
        }

        if ( false === filter_var( $url, FILTER_VALIDATE_URL ) || ! in_array( strtolower( (string) parse_url( $url, PHP_URL_SCHEME ) ), [ 'http', 'https' ], true ) ) {
            $this->components->error( __( 'The URL [:url] is not a valid http(s) URL. Pass --url or set app.url.', [ 'url' => $this->plain( $url ) ] ) );

            return self::INVALID;
        }

        $checks = array_values( array_filter( $registry->all(), static fn ( SpecCheck $check ): bool => ( null === $slugs || in_array( $check->slug(), $slugs, true ) )
            && ( null === $category || str_starts_with( $check->slug(), $category . '/' ) ) ) );
        $invalid = $registry->invalid();

        foreach ( $invalid as $class => $reason ) {
            $this->components->error( __( 'Check [:class] could not be used: :reason', [ 'class' => $this->plain( $class ), 'reason' => $this->plain( $reason ) ] ) );
        }

        if ( [] === $checks ) {
            $this->components->warn( __( 'No specification checks are registered for this selection.' ) );

            return [] === $invalid ? self::SUCCESS : self::FAILURE;
        }

        $scopes = array_unique( array_map( static fn ( SpecCheck $check ): string => $check->scope()->value, $checks ) );

        $contexts = [];

        if ( in_array( CheckScope::Site->value, $scopes, true ) ) {
            $contexts[] = SpecContext::site( $url );
        }

        if ( in_array( CheckScope::Page->value, $scopes, true ) || in_array( CheckScope::Server->value, $scopes, true ) ) {
            try {
                $response = $http->timeout( max( 1, (int) $this->option( 'timeout' ) ) )->get( $url );
            } catch ( Throwable $exception ) {
                $this->components->error( __( 'Could not fetch :url: :message', [ 'url' => $this->plain( $url ), 'message' => $this->plain( $exception->getMessage() ) ] ) );

                return self::FAILURE;
            }

            if ( $response->status() >= 400 ) {
                $this->components->warn( __( 'The URL returned HTTP :status; page and server checks ran against the error response.', [ 'status' => $response->status() ] ) );
            }

            $contexts[] = SpecContext::page( $url, $response->body(), $response->headers(), [ 'status' => $response->status() ] );
            $contexts[] = SpecContext::server( $url, $response->headers(), [ 'status' => $response->status() ] );
        }

        $results = [];

        foreach ( $contexts as $context ) {
            array_push( $results, ...$runner->run( $context, $slugs, $category ) );
        }

        $editorOnly = count( array_filter( $checks, static fn ( SpecCheck $check ): bool => in_array( $check->scope(), [ CheckScope::Section, CheckScope::Block ], true ) ) );

        $this->renderResults( $url, $results, $editorOnly );

        return [] !== $invalid || $this->blocking( $results ) ? self::FAILURE : self::SUCCESS;
    }

    /**
     * Prints the results table and the four-tier summary.
     *
     * @since 1.0.0
     *
     * @param  string                  $url         The checked URL.
     * @param  array<int, SpecResult>  $results     The results.
     * @param  int                     $editorOnly  Number of Section/Block checks that were not run.
     *
     * @return void
     */
    private function renderResults( string $url, array $results, int $editorOnly ): void
    {
        $this->components->info( __( 'Specification checks for :url', [ 'url' => $this->plain( $url ) ] ) );

        $this->table(
            [ __( 'Topic' ), __( 'Status' ), __( 'Scope' ), __( 'Outcome' ), __( 'Message' ) ],
            array_map( fn ( SpecResult $result ): array => [
                $this->plain( $result->slug ),
                $result->status->label(),
                $result->scope->value,
                $this->outcome( $result ),
                $this->plain( (string) $result->message ),
            ], $results ),
        );

        $site = SpecReport::fromResults( $results )->site();

        $this->components->twoColumnDetail( SpecStatus::Required->label(), $this->score( $site->required ) );
        $this->components->twoColumnDetail( SpecStatus::Recommended->label(), $this->score( $site->recommended ) );
        $this->components->twoColumnDetail( SpecStatus::Optional->label(), $this->score( $site->optional ) );
        $this->components->twoColumnDetail( __( 'Avoid present' ), __( ':present of :checked', [ 'present' => $site->avoidPresent, 'checked' => $site->avoidChecked ] ) );

        if ( $site->errored > 0 ) {
            $this->components->twoColumnDetail( __( 'Errored' ), (string) $site->errored );
        }

        if ( $editorOnly > 0 ) {
            $this->components->warn( trans_choice( ':count section or block check was not run: it needs editor context.|:count section or block checks were not run: they need editor context.', $editorOnly, [ 'count' => $editorOnly ] ) );
        }
    }

    /**
     * Formats an outcome with a colour.
     *
     * @since 1.0.0
     *
     * @param  SpecResult  $result  The result.
     *
     * @return string
     */
    private function outcome( SpecResult $result ): string
    {
        $color = match ( $result->outcome ) {
            ResultOutcome::Passed  => 'green',
            ResultOutcome::Failed  => SpecStatus::Required === $result->status || SpecStatus::Avoid === $result->status ? 'red' : 'yellow',
            ResultOutcome::Skipped => 'gray',
            ResultOutcome::Errored => 'red',
        };

        return '<fg=' . $color . '>' . $result->outcome->value . '</>';
    }

    /**
     * Formats a tier score.
     *
     * @since 1.0.0
     *
     * @param  TierScore  $score  The score.
     *
     * @return string
     */
    private function score( TierScore $score ): string
    {
        $percent = $score->percent();

        return null === $percent
            ? __( 'none checked' )
            : __( ':met of :total met (:percent%)', [ 'met' => $score->met, 'total' => $score->total, 'percent' => round( $percent ) ] );
    }

    /**
     * Determines whether any result should fail the command.
     *
     * @since 1.0.0
     *
     * @param  array<int, SpecResult>  $results  The results.
     *
     * @return bool
     */
    private function blocking( array $results ): bool
    {
        foreach ( $results as $result ) {
            if ( ResultOutcome::Errored === $result->outcome ) {
                return true;
            }

            if ( ResultOutcome::Failed === $result->outcome && in_array( $result->status, [ SpecStatus::Required, SpecStatus::Avoid ], true ) ) {
                return true;
            }
        }

        return false;
    }
}
