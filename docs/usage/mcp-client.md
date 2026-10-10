---
title: MCP Client
---

# MCP Client

specification.website runs a stateless, unauthenticated [MCP server](https://mcp.specification.website/mcp). The package includes a client for it as an **optional** freshness source. The OKF snapshot stays authoritative, and the client is never used to run checks.

## Enabling

The client is disabled by default, in which case the package runs snapshot-only:

```dotenv
SPECIFICATION_MCP_ENABLED=true
# SPECIFICATION_MCP_URL=https://mcp.specification.website/mcp
# SPECIFICATION_MCP_TIMEOUT=10
```

While disabled, every call throws `McpUnavailableException`. Check first:

```php
$mcp = specification()->mcp(); // ArtisanPackUI\Specification\Contracts\McpClient

if ( $mcp->isEnabled() ) {
    // ...
}
```

## Tools

```php
use ArtisanPackUI\Specification\Enums\ChangeType;
use ArtisanPackUI\Specification\Enums\SpecStatus;

$topics  = $mcp->listTopics( category: 'seo', status: SpecStatus::Required );
$topic   = $mcp->getTopic( 'foundations/doctype' );
$changes = $mcp->getChanges( since: '2026-09-01' );
$changes = $mcp->getChanges( since: now()->subWeek(), type: ChangeType::Status, limit: 50 );
$hits    = $mcp->search( 'content security policy', limit: 10 );
```

| Method | MCP tool | Returns |
|--------|----------|---------|
| `listTopics()` | `list_topics` | `array<McpTopic>` in canonical spec order |
| `getTopic()` | `get_topic` | `McpTopic` including `markdown` and `updatedAt` |
| `getChanges()` | `get_changes` | `McpChangeSet` (newest first) |
| `search()` | `search` | `array<McpTopic>`, most relevant first. The limit is clamped to 1–25 |

### Slugs

The upstream server uses bare slugs (`doctype`). The client normalizes every slug to the local `category/topic` form (`foundations/doctype`), so results compare directly with `Topic` rows and registered checks. `getTopic()` accepts either form. If you pass a local slug and the server returns a topic from a different category, it throws.

### Change sets

`McpChangeSet` has `since`, `latest` (the day of the most recent change upstream), and `changes`. Each `McpChange` has `date`, `type` (`Added`, `Changed`, `Status`, `Removed`), `title`, `summary`, `topics`, and `unresolvedSlugs` (upstream slugs that are no longer live). Use `$changes->slugs()` to get every affected local slug.

### Attribution

Topic summaries and Markdown from the MCP server are CC BY 4.0 text too. `McpTopic::attribution()` returns the `Attribution` to display alongside them, and `McpTopic::toArray()` leaves the Markdown body out.

Topic URLs returned by the server are normalized with `Attribution::canonicalUrl()`. A missing URL, or one that isn't an `https://specification.website/` URL, is replaced with the URL derived from the topic slug, so attribution links always point at the upstream site.

## Errors

Any transport failure, non-2xx response, JSON-RPC error, tool error (`isError`), or malformed payload throws `McpRequestException`. The URL must use `https`, redirects are not followed, and responses larger than `HttpMcpClient::MAX_RESPONSE_BYTES` (5 MB) are refused.

## Binding Your Own Client

The client is bound to the `McpClient` contract, so you can swap in another implementation, for example a local mirror:

```php
$this->app->singleton( \ArtisanPackUI\Specification\Contracts\McpClient::class, LocalMirrorClient::class );
```
