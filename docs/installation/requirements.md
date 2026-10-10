---
title: Requirements
---

# Requirements

| Requirement | Version |
|-------------|---------|
| PHP | 8.3+ |
| Laravel | 13 |
| `ext-pdo_sqlite` | Required. SQLite with FTS5 powers the local search index |
| `ext-zlib` | Required. Used to decompress the OKF `tar.gz` bundle |

## Composer Dependencies

The package installs these dependencies automatically:

- `artisanpack-ui/core`
- `league/commonmark`: renders topic Markdown, escaping raw HTML
- `symfony/yaml`: parses OKF front matter

## Network Access

- Syncing downloads `https://specification.website/okf.tar.gz` over HTTPS. To sync offline, point `keystone:spec-sync --archive=` at a local copy of the archive.
- The MCP client calls `https://mcp.specification.website/mcp` and only does so when you enable it.

## Database

Topic and snapshot rows live on your application's default database connection. The search index is a separate SQLite file, independent of that connection, so it works whether your app uses MySQL, PostgreSQL, or SQLite.
