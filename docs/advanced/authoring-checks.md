---
title: Authoring SpecChecks
---

# Authoring SpecChecks

Owner packages (`seo`, `security`, `accessibility`, `performance`, `privacy`, `cms-framework`, …) claim the topic slugs they can enforce and ship a `SpecCheck` for each one. This package never implements domain checks itself. The one exception, `ArtisanPackUI\Specification\Checks\Foundations\DoctypeCheck`, exists so you have a working pattern to copy.

## The Contract

```php
interface SpecCheck
{
    public function slug(): string;                        // 'seo/xml-sitemaps'
    public function status(): SpecStatus;                  // Required, Recommended, Optional, Avoid
    public function scope(): CheckScope;                   // Site, Page, Section, Block, Server
    public function run( SpecContext $context ): SpecResult;
    public function autofix( SpecContext $context ): ?SpecFix; // null when only a human can fix it
}
```

### Rules

- **`slug()`** must match a specification.website topic and the pattern `category/topic` (lower-case kebab segments). The registry rejects other values with `InvalidCheckException`.
- **`status()`** must match the topic's upstream tier. Read it from the synced `Topic` if you want to follow upstream promotions automatically. The runner turns a result whose status differs from `status()` into an `Errored` result.
- **`scope()`** decides which contexts the check runs against. The runner only calls `run()` for contexts with the same scope.
- **`run()`** must return a result for its own slug, built with the `SpecResult` factories. Don't let expected failures throw. A thrown exception is caught, reported, and turned into an `Errored` result, which is a bug signal rather than a verdict.
- **`autofix()`** must not change anything. It describes the change, and the change happens only when the caller invokes `SpecFix::apply()`.

## Walkthrough: `foundations/doctype`

### 1. Declare the topic

```php
final class DoctypeCheck implements SpecCheck
{
    public const SLUG = 'foundations/doctype';

    public function slug(): string
    {
        return self::SLUG;
    }

    public function status(): SpecStatus
    {
        return SpecStatus::Required;
    }

    public function scope(): CheckScope
    {
        return CheckScope::Page;
    }
```

### 2. Skip when there is nothing to inspect

A missing input means "not applicable", not "failed". Skipped results don't count against any tier.

```php
    public function run( SpecContext $context ): SpecResult
    {
        if ( null === $context->html || '' === trim( $context->html ) ) {
            return SpecResult::skipped( $this, $context, __( 'No HTML was provided for this page.' ) );
        }
```

### 3. Pass or fail with structured details

Messages are for people, so translate them. `details` is for UIs and agents, so make it machine-readable.

```php
        return $valid
            ? SpecResult::passed( $this, $context, __( 'The page starts with the HTML doctype.' ), [ 'found' => $found ] )
            : SpecResult::failed( $this, $context, __( 'The page has no doctype, so browsers render in quirks mode.' ), [
                'found'    => null,
                'expected' => '<!doctype html>',
            ] );
    }
```

### 4. Offer an autofix only when you can apply it

A check knows *what* to change but rarely *where* it lives. The reference check asks the caller to pass a writer in the context data, and returns `null` without one:

```php
    public const HTML_WRITER = 'html_writer';

    public function autofix( SpecContext $context ): ?SpecFix
    {
        $writer = $context->get( self::HTML_WRITER );

        if ( ! is_callable( $writer ) || /* already passing */ ) {
            return null;
        }

        $fixed = /* corrected HTML */;

        return new SpecFix(
            self::SLUG,
            __( 'Add <!doctype html> to the start of the page.' ),
            static function () use ( $writer, $fixed ): void {
                $writer( $fixed );
            },
            [ 'before' => null, 'after' => '<!doctype html>', 'html' => $fixed ],
        );
    }
```

The caller decides whether and when to apply:

```php
$context = SpecContext::page( $url, $html, [], [
    DoctypeCheck::HTML_WRITER => fn ( string $html ) => $layout->update( [ 'html' => $html ] ),
] );

$fix = ( new DoctypeCheck() )->autofix( $context );

// Show $fix->description and $fix->details, get confirmation, then:
$fix?->apply();
```

Put enough in `details` for a UI to preview the change as a diff. Keep the blast radius in mind: an autofix that changes a site-wide setting should say so in its description, so that callers can require an admin role and explicit confirmation.

## Registering Checks

Register from your package's service provider. Class names are resolved lazily through the container, so checks can use constructor injection. Each class is registered at most once.

### Option 1: The registry

```php
use ArtisanPackUI\Specification\Checks\CheckRegistry;

public function boot(): void
{
    $this->callAfterResolving( CheckRegistry::class, function ( CheckRegistry $registry ): void {
        $registry->registerMany( [
            XmlSitemapCheck::class,
            RobotsTxtCheck::class,
        ] );
    } );
}
```

### Option 2: Container tags

```php
public function register(): void
{
    $this->app->tag( [ XmlSitemapCheck::class, RobotsTxtCheck::class ], CheckRegistry::TAG );
}
```

### Option 3: Application config

Applications can add checks without a provider:

```php
// config/artisanpack/specification.php
'checks' => [
    \ArtisanPackUI\Specification\Checks\Foundations\DoctypeCheck::class,
],
```

### Optional dependency

If `artisanpack-ui/specification` is only a suggested dependency of your package, guard the registration:

```php
if ( interface_exists( \ArtisanPackUI\Specification\Contracts\SpecCheck::class ) ) {
    // register checks
}
```

## Document Your Coverage

Each owner package should list the slugs it covers in its README:

| Slug | Status | Scope | Autofix |
|------|--------|-------|---------|
| `foundations/doctype` | Required | Page | Yes, with an HTML writer |

## Checklist

- [ ] `slug()` matches a real topic and `category/topic` format
- [ ] `status()` matches the upstream tier
- [ ] Missing inputs return `skipped`, not `failed`
- [ ] Messages are translatable; `details` are structured
- [ ] `autofix()` has no side effects and returns `null` when it cannot apply the fix
- [ ] Agent Readiness checks for experimental topics respect `artisanpack.specification.agent_readiness_experimental`
- [ ] Tests cover pass, fail, skip, and autofix paths (see [Testing SpecChecks](Advanced-Testing-Checks))
