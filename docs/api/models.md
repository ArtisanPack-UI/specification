---
title: Models
---

# Models

## Topic

`ArtisanPackUI\Specification\Models\Topic` (table `specification_topics`)

| Attribute | Type | Description |
|-----------|------|-------------|
| `slug` | `string` | Unique `category/topic` slug |
| `category` | `string` | Category |
| `status` | `SpecStatus` | Status tier |
| `conformance` | `?string` | RFC 2119 keyword (`MUST`, `SHOULD`, `MAY`, `MUST NOT`) |
| `title` | `string` | Title |
| `summary` | `string` | One-paragraph summary |
| `body_md` | `string` | Markdown body. **Hidden from serialization**; output it with `render()` |
| `sources_json` | `array` | Cited sources |
| `resource_url` | `string` | Canonical specification.website URL |
| `snapshot_version` | `string` | Version of the snapshot the row came from |
| `attribution` | `string` | Canonical CC BY 4.0 attribution |
| `source_updated_at` | `?Carbon` | When the topic was last updated upstream |

| Method | Description |
|--------|-------------|
| `render( RenderSurface $surface = RenderSurface::Admin )` | Renders the topic with its attribution |
| `scopeInCategory( $query, string $category )` | `Topic::query()->inCategory( 'seo' )` |
| `scopeWithStatus( $query, SpecStatus $status )` | `Topic::query()->withStatus( SpecStatus::Required )` |

## Snapshot

`ArtisanPackUI\Specification\Models\Snapshot` (table `specification_snapshots`)

| Attribute | Type | Description |
|-----------|------|-------------|
| `version` | `string` | SHA-256 of the archive |
| `okf_version` | `?string` | The bundle's declared `okf_version` |
| `topic_count` | `int` | Topics in the snapshot |
| `changes` | `?array` | `created`, `updated`, and `deleted` slugs from the sync that produced it |
| `synced_at` | `Carbon` | When it was last synced |

| Method | Description |
|--------|-------------|
| `Snapshot::current()` | The most recently synced snapshot, or `null` |
