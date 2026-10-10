---
title: Running Checks
---

# Running Checks

A check enforces one topic against a **context**: the subject being checked. The runner executes every registered check whose scope matches the context's scope.

## Contexts

```php
use ArtisanPackUI\Specification\Checks\SpecContext;

$site   = SpecContext::site( 'https://example.com' );
$page   = SpecContext::page( 'https://example.com/about', $html, $headers );
$server = SpecContext::server( 'https://example.com', $headers );
```

| Scope | Typical subject |
|-------|-----------------|
| `Site` | Site-wide settings (sitemaps, `robots.txt`, feeds) |
| `Page` | One rendered page's HTML and headers |
| `Section` / `Block` | Part of a page in the editor. Carries the page URL so its results roll up into that page |
| `Server` | Response headers, TLS, DNS |

Owner packages read whatever they need from `$context->html`, `$context->header( 'content-security-policy' )`, and `$context->get( 'some.key' )`. The last one reads the `data` array passed to the constructor.

## Running

```php
$results = specification()->run( $page );
$results = specification()->run( $page, slugs: [ 'foundations/doctype' ] );
$results = specification()->run( $page, category: 'security' );
```

Each `SpecResult` has `slug`, `status`, `outcome` (`Passed`, `Failed`, `Skipped`, `Errored`), `scope`, `subject`, `message`, and `details`. For an Avoid-status check, `Passed` means the avoided practice is absent.

The runner isolates failures:

- A check that throws produces an `Errored` result (and the exception is reported) instead of aborting the run.
- A check that returns a result for a different slug, or a different status than it declares, produces an `Errored` result.
- A registered check that can't be used, because the container can't build it or its slug is invalid, is reported and set aside. The other checks still run. `specification()->checks()->invalid()` lists the checks that were set aside, with the reason.

## Autofixes

A check may offer a `SpecFix` for a failing subject:

```php
foreach ( specification()->checks()->forSlug( 'foundations/doctype' ) as $check ) {
    $fix = $check->autofix( $page );

    if ( null !== $fix ) {
        $fix->description; // human-readable summary to show before applying
        $fix->details;     // structured preview, e.g. before/after
        $fix->apply();     // only now does anything change
    }
}
```

Building a fix never changes anything. Show the description and details, then call `apply()` once the user, or an authorized role, has confirmed.

## From the Command Line

```bash
php artisan keystone:spec-check --category=foundations --url=https://example.com
```

See [Artisan Commands](Advanced-Artisan-Commands#keystonespec-check).

## Inspecting the Registry

```php
$registry = specification()->checks();

$registry->all();                       // every registered check
$registry->forSlug( 'foundations/doctype' );
$registry->forCategory( 'security' );
$registry->forScope( CheckScope::Page );
$registry->has( 'seo/xml-sitemaps' );
$registry->slugs();                     // unique, sorted slugs covered
```
