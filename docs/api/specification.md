---
title: Specification Class
---

# Specification Class

`ArtisanPackUI\Specification\Specification` is the package entry point, resolved as a singleton by `specification()` and the `Specification` facade.

| Method | Returns | Description |
|--------|---------|-------------|
| `sync( bool $force = false, ?string $archivePath = null )` | `SyncResult` | Syncs the OKF snapshot, topics table, and search index |
| `search( string $query, int $limit = 10, ?string $category = null, ?SpecStatus $status = null )` | `array<SearchHit>` | Searches the local index |
| `topic( string $slug )` | `?Topic` | Finds a topic by slug |
| `checks()` | `CheckRegistry` | The check registry |
| `registerCheck( SpecCheck\|string $check )` | `static` | Registers a check instance or class name |
| `run( SpecContext $context, ?array $slugs = null, ?string $category = null )` | `array<SpecResult>` | Runs matching checks against a context |
| `report( iterable $results )` | `SpecReport` | Builds a four-tier report |
| `render( Topic $topic, RenderSurface $surface = RenderSurface::Admin )` | `RenderedTopic` | Renders a topic with its attribution |
| `mcp()` | `McpClient` | The MCP client (check `isEnabled()` first) |
| `agentReadinessExperimentalEnabled()` | `bool` | Whether checks for experimental Agent Readiness topics should run (`artisanpack.specification.agent_readiness_experimental`) |

## CheckRegistry

| Method | Description |
|--------|-------------|
| `register( SpecCheck\|string $check )` | Registers one check; each class at most once |
| `registerMany( iterable $checks )` | Registers several |
| `all()` | Every usable check, including tagged services. Checks that can't be resolved or have an invalid slug are reported and set aside |
| `invalid()` | `array<string, string>` of set-aside checks: the reason, keyed by class name |
| `reject( string $class, Throwable $exception )` | Reports a check as unusable and sets it aside |
| `forSlug( string $slug )` / `forCategory( string $category )` / `forScope( CheckScope $scope )` | Filtered lists |
| `has( string $slug )` | Whether any check enforces the slug |
| `slugs()` | Unique, sorted covered slugs |

`CheckRegistry::TAG` (`artisanpack.specification.checks`) is the container tag for check discovery, and `CheckRegistry::SLUG_PATTERN` is the slug validation pattern.

## Helper

```php
specification(): \ArtisanPackUI\Specification\Specification
```
