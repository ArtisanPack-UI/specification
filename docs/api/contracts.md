---
title: Contracts
---

# Contracts

## SpecCheck

`ArtisanPackUI\Specification\Contracts\SpecCheck`

| Method | Returns | Description |
|--------|---------|-------------|
| `slug()` | `string` | The topic slug, e.g. `seo/xml-sitemaps` |
| `status()` | `SpecStatus` | The topic's tier |
| `scope()` | `CheckScope` | The level the check evaluates |
| `run( SpecContext $context )` | `SpecResult` | Runs the check |
| `autofix( SpecContext $context )` | `?SpecFix` | A fix, or `null` when only a human can resolve it |

See [Authoring SpecChecks](Advanced-Authoring-Checks).

## SearchIndex

`ArtisanPackUI\Specification\Contracts\SearchIndex`. Default implementation: `ArtisanPackUI\Specification\Search\SqliteFtsIndex`.

| Method | Returns | Description |
|--------|---------|-------------|
| `rebuild( iterable $topics, string $version )` | `void` | Replaces the index contents |
| `search( string $query, int $limit = 10, ?string $category = null, ?SpecStatus $status = null )` | `array<SearchHit>` | Hits ordered by relevance |
| `version()` | `?string` | The indexed snapshot version, or `null` if never built |

## McpClient

`ArtisanPackUI\Specification\Contracts\McpClient`. Default implementation: `ArtisanPackUI\Specification\Mcp\HttpMcpClient`.

| Method | Returns | Description |
|--------|---------|-------------|
| `isEnabled()` | `bool` | Whether the client may make requests |
| `listTopics( ?string $category = null, ?SpecStatus $status = null, ?int $limit = null )` | `array<McpTopic>` | `list_topics` (limit clamped to 1–200; `null` for all) |
| `getTopic( string $slug )` | `McpTopic` | `get_topic`; accepts local or upstream slugs |
| `getChanges( DateTimeInterface\|string\|null $since = null, ?ChangeType $type = null, ?int $limit = null )` | `McpChangeSet` | `get_changes`. A date-time is sent as its UTC day, and a string (`YYYY-MM-DD`) is sent as-is. Limit clamped to 1–100 |
| `search( string $query, int $limit = 5 )` | `array<McpTopic>` | `search` (limit clamped to 1–25) |

Topic URLs in results are normalized to the specification.website origin. Every method except `isEnabled()` throws `McpUnavailableException` when disabled and `McpRequestException` on failure. See [MCP Client](Usage-Mcp-Client).
