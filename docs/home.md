---
title: ArtisanPack UI Specification Documentation Home
---

# ArtisanPack UI Specification Documentation

Welcome to the documentation for **ArtisanPack UI Specification**. The package ingests [The Website Specification](https://specification.website)'s Open Knowledge Format (OKF) bundle into a local index and provides the `SpecCheck` registry that other ArtisanPack UI packages build their checks on.

The package owns OKF ingestion, the local search index, the `SpecCheck` contract and registry, result aggregation, and CC BY 4.0 attribution. It never implements domain checks itself. Those live in their owner packages (`seo`, `security`, `accessibility`, `performance`, `privacy`, `cms-framework`, …). The one exception is a reference `foundations/doctype` check that shows owner packages the pattern to copy.

## Table of Contents

- **Getting Started**
  - [Quick Start Guide](Getting-Started)

- **Installation**
  - [Installation Overview](Installation)
  - [Requirements](Installation-Requirements)
  - [Configuration](Installation-Configuration)

- **Usage**
  - [Usage Overview](Usage)
  - [Syncing the Specification](Usage-Syncing)
  - [Searching Topics](Usage-Search)
  - [Rendering Topics](Usage-Rendering-Topics)
  - [Running Checks](Usage-Running-Checks)
  - [Reports](Usage-Reports)
  - [MCP Client](Usage-Mcp-Client)

- **Advanced**
  - [Advanced Overview](Advanced)
  - [Authoring SpecChecks](Advanced-Authoring-Checks)
  - [Testing SpecChecks](Advanced-Testing-Checks)
  - [Attribution Requirements](Advanced-Attribution-Requirements)
  - [Artisan Commands](Advanced-Artisan-Commands)
  - [Deployment & Operations](Advanced-Deployment)

- **API Reference**
  - [API Overview](Api)
  - [Specification Class](Api-Specification)
  - [Contracts](Api-Contracts)
  - [Models](Api-Models)
  - [Value Objects & Enums](Api-Value-Objects)

- **[FAQ](Faq)**
- **[Troubleshooting](Troubleshooting)**

## Features

- **OKF ingestion**: Weekly, idempotent sync of `https://specification.website/okf.tar.gz` into an on-disk snapshot, a `specification_topics` table, and a search index. Archives are size-capped and validated.
- **Local search**: An embedded SQLite FTS5 index keeps topic lookups fast and working offline. It rebuilds itself from the database on servers that didn't run the sync.
- **SpecCheck registry**: A single contract (`SpecCheck`) that owner packages implement and register, with discovery from service providers, config, and container tags.
- **Result aggregation**: `SpecReport` scores the four tiers (Required, Recommended, Optional, Avoid) separately for the site, each category, and each page. It never produces a single "overall %".
- **CC BY 4.0 attribution**: `SpecTopicRenderer` is the supported way to output topic text. It always attaches attribution, refuses blocked surfaces, and makes the topic's relative links absolute.
- **MCP client**: An optional client for the specification.website MCP server, used to check upstream freshness. It is off by default, so the package runs snapshot-only.
- **Artisan commands**: `keystone:spec-sync`, `keystone:spec-diff`, and `keystone:spec-check`.

## License

This package's code is MIT licensed. Specification content is © The Website Specification contributors and licensed under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/). See [Attribution Requirements](Advanced-Attribution-Requirements).
