---
title: Testing SpecChecks
---

# Testing SpecChecks

Checks are plain classes that take a `SpecContext`, so most tests don't need HTTP, a database, or a synced snapshot.

## Unit-test the Check Directly

```php
use ArtisanPackUI\Specification\Checks\Foundations\DoctypeCheck;
use ArtisanPackUI\Specification\Checks\SpecContext;
use ArtisanPackUI\Specification\Enums\ResultOutcome;

it( 'fails pages without a doctype', function (): void {
    $result = ( new DoctypeCheck() )->run( SpecContext::page( 'https://example.test/', '<html></html>' ) );

    expect( $result->outcome )->toBe( ResultOutcome::Failed )
        ->and( $result->details['expected'] )->toBe( '<!doctype html>' );
} );
```

Use Pest datasets for the pass and fail matrix, and include the "weird" inputs: empty HTML, a byte order mark, leading comments, upper-case markup.

## Test Autofixes Without Side Effects

Pass a capturing writer and assert that nothing is written until `apply()`:

```php
$written = null;
$context = SpecContext::page( 'https://example.test/', '<html></html>', [], [
    DoctypeCheck::HTML_WRITER => function ( string $html ) use ( &$written ): void {
        $written = $html;
    },
] );

$fix = ( new DoctypeCheck() )->autofix( $context );

expect( $written )->toBeNull();

$fix->apply();

expect( ( new DoctypeCheck() )->run( SpecContext::page( 'https://example.test/', $written ) )->outcome )
    ->toBe( ResultOutcome::Passed );
```

## Test Registration

```php
it( 'registers its checks', function (): void {
    expect( specification()->checks()->has( 'seo/xml-sitemaps' ) )->toBeTrue();
} );
```

## Test Through the Runner

Running through `specification()->run()` also verifies that the slug and status your result reports match what the check declares:

```php
$results = specification()->run( SpecContext::page( 'https://example.test/', $html ) );

expect( collect( $results )->firstWhere( 'slug', 'seo/meta-description' )->outcome )->not->toBe( ResultOutcome::Errored );
```

## Fake the MCP Client

Write a small fake that implements `ArtisanPackUI\Specification\Contracts\McpClient` and bind it, so tests never reach the network:

```php
// FakeMcpClient is your own test double implementing McpClient.
app()->instance( \ArtisanPackUI\Specification\Contracts\McpClient::class, new FakeMcpClient( changes: $changeSet ) );
```

To exercise the real `HttpMcpClient`, use `Http::fake()` and return JSON-RPC envelopes whose `result.structuredContent` matches the tool's output.

## Sync Fixture Bundles

To test against real topic rows, build a small OKF `tar.gz` from fixture Markdown files and sync it from disk:

```php
specification()->sync( archivePath: $pathToFixtureArchive );
```
