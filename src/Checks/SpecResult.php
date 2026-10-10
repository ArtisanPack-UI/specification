<?php

/**
 * SpecCheck result.
 *
 * @package    ArtisanPack_UI
 * @subpackage Specification
 *
 * @since      1.0.0
 */

declare( strict_types=1 );

namespace ArtisanPackUI\Specification\Checks;

use ArtisanPackUI\Specification\Contracts\SpecCheck;
use ArtisanPackUI\Specification\Enums\CheckScope;
use ArtisanPackUI\Specification\Enums\ResultOutcome;
use ArtisanPackUI\Specification\Enums\SpecStatus;
use Illuminate\Contracts\Support\Arrayable;

/**
 * The immutable result of one SpecCheck run against one subject.
 *
 * @implements Arrayable<string, mixed>
 *
 * @since 1.0.0
 */
final class SpecResult implements Arrayable
{
    /**
     * Creates a new result.
     *
     * @since 1.0.0
     *
     * @param  string                $slug     The topic slug.
     * @param  SpecStatus            $status   The topic's status tier.
     * @param  ResultOutcome         $outcome  The outcome.
     * @param  CheckScope            $scope    The scope the check ran at.
     * @param  string                $subject  The subject identifier (see SpecContext::subject()).
     * @param  string|null           $message  A human-readable explanation.
     * @param  array<string, mixed>  $details  Structured details for UIs and agents.
     */
    public function __construct(
        public readonly string $slug,
        public readonly SpecStatus $status,
        public readonly ResultOutcome $outcome,
        public readonly CheckScope $scope,
        public readonly string $subject,
        public readonly ?string $message = null,
        public readonly array $details = [],
    ) {
    }

    /**
     * Creates a passed result for the given check and context.
     *
     * @since 1.0.0
     *
     * @param  SpecCheck             $check    The check.
     * @param  SpecContext           $context  The context.
     * @param  string|null           $message  An explanation.
     * @param  array<string, mixed>  $details  Structured details.
     *
     * @return self
     */
    public static function passed( SpecCheck $check, SpecContext $context, ?string $message = null, array $details = [] ): self
    {
        return self::make( $check, $context, ResultOutcome::Passed, $message, $details );
    }

    /**
     * Creates a failed result for the given check and context.
     *
     * @since 1.0.0
     *
     * @param  SpecCheck             $check    The check.
     * @param  SpecContext           $context  The context.
     * @param  string|null           $message  An explanation.
     * @param  array<string, mixed>  $details  Structured details.
     *
     * @return self
     */
    public static function failed( SpecCheck $check, SpecContext $context, ?string $message = null, array $details = [] ): self
    {
        return self::make( $check, $context, ResultOutcome::Failed, $message, $details );
    }

    /**
     * Creates a skipped (not applicable) result for the given check and context.
     *
     * @since 1.0.0
     *
     * @param  SpecCheck             $check    The check.
     * @param  SpecContext           $context  The context.
     * @param  string|null           $message  An explanation.
     * @param  array<string, mixed>  $details  Structured details.
     *
     * @return self
     */
    public static function skipped( SpecCheck $check, SpecContext $context, ?string $message = null, array $details = [] ): self
    {
        return self::make( $check, $context, ResultOutcome::Skipped, $message, $details );
    }

    /**
     * Creates an errored result for the given check and context.
     *
     * @since 1.0.0
     *
     * @param  SpecCheck             $check    The check.
     * @param  SpecContext           $context  The context.
     * @param  string|null           $message  An explanation.
     * @param  array<string, mixed>  $details  Structured details.
     *
     * @return self
     */
    public static function errored( SpecCheck $check, SpecContext $context, ?string $message = null, array $details = [] ): self
    {
        return self::make( $check, $context, ResultOutcome::Errored, $message, $details );
    }

    /**
     * Gets the category portion of the slug.
     *
     * @since 1.0.0
     *
     * @return string
     */
    public function category(): string
    {
        return explode( '/', $this->slug, 2 )[0];
    }

    /**
     * Determines whether the check's expectation is met.
     *
     * @since 1.0.0
     *
     * @return bool
     */
    public function isMet(): bool
    {
        return ResultOutcome::Passed === $this->outcome;
    }

    /**
     * Gets the result as an array.
     *
     * @since 1.0.0
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'slug'    => $this->slug,
            'status'  => $this->status->value,
            'outcome' => $this->outcome->value,
            'scope'   => $this->scope->value,
            'subject' => $this->subject,
            'message' => $this->message,
            'details' => $this->details,
        ];
    }

    /**
     * Builds a result from a check and context.
     *
     * @since 1.0.0
     *
     * @param  SpecCheck             $check    The check.
     * @param  SpecContext           $context  The context.
     * @param  ResultOutcome         $outcome  The outcome.
     * @param  string|null           $message  An explanation.
     * @param  array<string, mixed>  $details  Structured details.
     *
     * @return self
     */
    private static function make( SpecCheck $check, SpecContext $context, ResultOutcome $outcome, ?string $message, array $details ): self
    {
        return new self( $check->slug(), $check->status(), $outcome, $context->scope, $context->subject(), $message, $details );
    }
}
