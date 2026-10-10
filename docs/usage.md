---
title: Usage Overview
---

# Usage Overview

Everything in the package is reachable from the `specification()` helper or the `Specification` facade. Both resolve the same singleton.

```php
use ArtisanPackUI\Specification\Facades\Specification;

specification()->sync();
Specification::search( 'content security policy' );
```

## Core Concepts

### Topics and slugs

Each specification.website page is a **topic** with a `category/topic` slug such as `foundations/doctype` or `security/x-xss-protection`. Topics carry one of four status tiers:

| Status | Conformance | Meaning |
|--------|-------------|---------|
| `Required` | MUST | The platform contract breaks, or a clear group of users is harmed, without it. |
| `Recommended` | SHOULD | A modern site should do it. |
| `Optional` | MAY | Depends on context. |
| `Avoid` | MUST NOT | Outdated, harmful, or superseded. |

### The snapshot is authoritative

Topics come from the OKF bundle, which is vendored to disk and copied into the database. Checks, search, and rendering work offline from that snapshot. The MCP client only tells you how fresh the snapshot is.

### Checks live in owner packages

This package defines the `SpecCheck` contract and runs whatever checks are registered. Domain checks belong to the packages that can enforce them.

## Guides

- [Syncing the Specification](Usage-Syncing): download, vendor, and index the OKF bundle.
- [Searching Topics](Usage-Search): full-text search over the local index.
- [Rendering Topics](Usage-Rendering-Topics): output topic text with its CC BY 4.0 attribution.
- [Running Checks](Usage-Running-Checks): run registered checks against a site, page, or server.
- [Reports](Usage-Reports): roll results into four-tier scores.
- [MCP Client](Usage-Mcp-Client): query the upstream MCP server for freshness.
