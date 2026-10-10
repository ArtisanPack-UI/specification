<?php

/**
 * SpecCheck registry.
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
use ArtisanPackUI\Specification\Exceptions\InvalidCheckException;
use Illuminate\Contracts\Container\Container;

/**
 * Holds every SpecCheck known to the application.
 *
 * Checks are discovered from three places: direct registration (usually in
 * an owner package's service provider), the `artisanpack.specification.checks`
 * config array, and container services tagged with TAG. Class-string
 * registrations are resolved lazily through the container. Each check class
 * is registered at most once.
 *
 * @since 1.0.0
 */
final class CheckRegistry
{
    /**
     * Container tag owner packages may use to register checks.
     *
     * @var string
     */
    public const TAG = 'artisanpack.specification.checks';

    /**
     * Pattern every check slug must match, e.g. `foundations/doctype`.
     *
     * @var string
     */
    public const SLUG_PATTERN = '#^[a-z0-9]+(?:-[a-z0-9]+)*/[a-z0-9]+(?:-[a-z0-9]+)*$#';

    /**
     * Registered checks keyed by class name.
     *
     * @var array<class-string<SpecCheck>, class-string<SpecCheck>|SpecCheck>
     */
    private array $checks = [];

    /**
     * Creates a new registry.
     *
     * @since 1.0.0
     *
     * @param  Container  $container  The container used to resolve class-string registrations.
     */
    public function __construct( private readonly Container $container )
    {
    }

    /**
     * Registers a check instance or class name.
     *
     * @since 1.0.0
     *
     * @param  SpecCheck|string  $check  A check instance or a class name implementing SpecCheck.
     *
     * @throws InvalidCheckException When the class does not implement SpecCheck.
     *
     * @return static
     */
    public function register( SpecCheck|string $check ): static
    {
        if ( is_string( $check ) && ! is_subclass_of( $check, SpecCheck::class ) ) {
            throw new InvalidCheckException( __( 'Class [:class] must implement :contract.', [
                'class'    => $check,
                'contract' => SpecCheck::class,
            ] ) );
        }

        $class = is_string( $check ) ? ltrim( $check, '\\' ) : $check::class;

        $this->checks[ $class ] ??= $check;

        return $this;
    }

    /**
     * Registers several checks.
     *
     * @since 1.0.0
     *
     * @param  iterable<SpecCheck|string>  $checks  Check instances or class names.
     *
     * @return static
     */
    public function registerMany( iterable $checks ): static
    {
        foreach ( $checks as $check ) {
            $this->register( $check );
        }

        return $this;
    }

    /**
     * Gets every registered check, including tagged services.
     *
     * @since 1.0.0
     *
     * @throws InvalidCheckException When a check reports an invalid slug.
     *
     * @return array<int, SpecCheck>
     */
    public function all(): array
    {
        foreach ( $this->container->tagged( self::TAG ) as $tagged ) {
            if ( ! $tagged instanceof SpecCheck ) {
                throw new InvalidCheckException( __( 'Services tagged [:tag] must implement :contract.', [
                    'tag'      => self::TAG,
                    'contract' => SpecCheck::class,
                ] ) );
            }

            $this->register( $tagged );
        }

        foreach ( $this->checks as $class => $check ) {
            if ( is_string( $check ) ) {
                $check = $this->container->make( $check );
            }

            if ( 1 !== preg_match( self::SLUG_PATTERN, $check->slug() ) ) {
                throw new InvalidCheckException( __( 'Check [:class] has an invalid slug [:slug]; expected "category/topic".', [
                    'class' => $class,
                    'slug'  => $check->slug(),
                ] ) );
            }

            $this->checks[ $class ] = $check;
        }

        return array_values( $this->checks );
    }

    /**
     * Gets the checks that enforce the given topic slug.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  The topic slug.
     *
     * @return array<int, SpecCheck>
     */
    public function forSlug( string $slug ): array
    {
        return $this->filter( static fn ( SpecCheck $check ): bool => $check->slug() === $slug );
    }

    /**
     * Gets the checks in the given category.
     *
     * @since 1.0.0
     *
     * @param  string  $category  The category, e.g. `seo`.
     *
     * @return array<int, SpecCheck>
     */
    public function forCategory( string $category ): array
    {
        return $this->filter( static fn ( SpecCheck $check ): bool => str_starts_with( $check->slug(), $category . '/' ) );
    }

    /**
     * Gets the checks that run at the given scope.
     *
     * @since 1.0.0
     *
     * @param  CheckScope  $scope  The scope.
     *
     * @return array<int, SpecCheck>
     */
    public function forScope( CheckScope $scope ): array
    {
        return $this->filter( static fn ( SpecCheck $check ): bool => $check->scope() === $scope );
    }

    /**
     * Determines whether any check enforces the given slug.
     *
     * @since 1.0.0
     *
     * @param  string  $slug  The topic slug.
     *
     * @return bool
     */
    public function has( string $slug ): bool
    {
        return [] !== $this->forSlug( $slug );
    }

    /**
     * Gets the unique, sorted slugs covered by registered checks.
     *
     * @since 1.0.0
     *
     * @return array<int, string>
     */
    public function slugs(): array
    {
        $slugs = array_unique( array_map( static fn ( SpecCheck $check ): string => $check->slug(), $this->all() ) );
        sort( $slugs );

        return $slugs;
    }

    /**
     * Filters all checks with the given callback.
     *
     * @since 1.0.0
     *
     * @param  callable(SpecCheck): bool  $callback  The filter.
     *
     * @return array<int, SpecCheck>
     */
    private function filter( callable $callback ): array
    {
        return array_values( array_filter( $this->all(), $callback ) );
    }
}
