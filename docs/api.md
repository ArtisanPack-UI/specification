---
title: API Overview
---

# API Overview

This section documents the package's programmatic API.

## Quick Reference

### Entry Point

| Access | Resolves |
|--------|----------|
| `specification()` | `ArtisanPackUI\Specification\Specification` singleton |
| `Specification` facade | Same instance |

[View Specification Class Documentation →](Api-Specification)

### Contracts

| Contract | Purpose |
|----------|---------|
| `SpecCheck` | A check against one topic, implemented by owner packages |
| `SearchIndex` | The local full-text index (default: SQLite FTS5) |
| `McpClient` | The specification.website MCP client (default: `HttpMcpClient`) |

[View Contracts Documentation →](Api-Contracts)

### Models

| Model | Table |
|-------|-------|
| `Topic` | `specification_topics` |
| `Snapshot` | `specification_snapshots` |

[View Models Documentation →](Api-Models)

### Value Objects & Enums

`SpecContext`, `SpecResult`, `SpecFix`, `SpecReport`, `TierBreakdown`, `TierScore`, `SyncResult`, `SearchHit`, `Attribution`, `RenderedTopic`, `McpTopic`, `McpChange`, `McpChangeSet`, and the `SpecStatus`, `CheckScope`, `ResultOutcome`, `RenderSurface`, `AttributionPlacement`, and `ChangeType` enums.

[View Value Objects & Enums Documentation →](Api-Value-Objects)

## Exceptions

Every exception extends `ArtisanPackUI\Specification\Exceptions\SpecificationException` (a `RuntimeException`):

| Exception | Thrown when |
|-----------|-------------|
| `SyncFailedException` | The archive can't be downloaded or read, or a sync is already running |
| `InvalidArchiveException` | The archive is malformed, oversized, or has no valid topics |
| `SearchIndexUnavailableException` | The SQLite FTS5 index can't be opened, built, or queried |
| `InvalidCheckException` | A registered class isn't a `SpecCheck`, or a check reports an invalid slug |
| `LicenseViolationException` | Topic text is rendered on a blocked surface |
| `McpUnavailableException` | The MCP client is used while disabled |
| `McpRequestException` | An MCP request fails or returns an error or malformed data |
