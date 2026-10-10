---
title: Searching Topics
---

# Searching Topics

The local SQLite FTS5 index is rebuilt on every sync and works offline.

```php
use ArtisanPackUI\Specification\Enums\SpecStatus;

$hits = specification()->search( 'quirks mode' );
$hits = specification()->search( 'header', limit: 5, category: 'security' );
$hits = specification()->search( 'cache', status: SpecStatus::Recommended );
```

Each `SearchHit` carries `slug`, `category`, `status`, `title`, `summary`, `snippet`, `resourceUrl`, and `score`. Lower scores are more relevant (FTS5 bm25), and hits are already ordered by relevance. In the `snippet`, matched terms are wrapped in `[[ ]]`.

> The title, summary, and snippet are raw third-party text, and the snippet is unrendered Markdown. Escape them when you output HTML (Blade's `{{ }}` does this), and link to `resourceUrl` wherever you show them. To display a full topic body, use the renderer described in [Rendering Topics](Usage-Rendering-Topics).

## Looking Up a Topic by Slug

```php
$topic = specification()->topic( 'foundations/doctype' ); // Topic|null
```

## Querying the Model

```php
use ArtisanPackUI\Specification\Models\Topic;

Topic::query()->inCategory( 'security' )->withStatus( SpecStatus::Avoid )->get();
```

If the index file cannot be opened or SQLite lacks FTS5, search throws `SearchIndexUnavailableException`.
