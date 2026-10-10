---
title: Value Objects & Enums
---

# Value Objects & Enums

All value objects are immutable.

## Checks

### SpecContext

`ArtisanPackUI\Specification\Checks\SpecContext`

| Member | Description |
|--------|-------------|
| `SpecContext::site( $url, $data = [] )` | Site-level context |
| `SpecContext::page( $url, $html = null, $headers = [], $data = [] )` | Page-level context |
| `SpecContext::server( $url, $headers = [], $data = [] )` | Server-level context |
| `new SpecContext( $scope, $url, $html, $headers, $data )` | Any scope, including Section and Block |
| `$scope`, `$url`, `$html`, `$headers`, `$data` | Public properties; header names are lower-cased |
| `header( string $name )` | First value of a header, case-insensitively |
| `get( string $key, $default = null )` | Dot-notation read from `$data` |
| `subject()` | The URL, else `data.subject`, else the scope |

### SpecResult

`passed()`, `failed()`, `skipped()`, and `errored()` factories take `( SpecCheck $check, SpecContext $context, ?string $message = null, array $details = [] )`. The public properties are `slug`, `status`, `outcome`, `scope`, `subject`, `message`, and `details`. Methods: `category()`, `isMet()`, `toArray()`.

### SpecFix

`new SpecFix( string $slug, string $description, Closure $applier, array $details = [] )`. The public properties are `slug`, `description`, and `details`. `apply()` runs the applier.

## Reporting

`SpecReport`, `TierBreakdown`, and `TierScore` are covered in [Reports](Usage-Reports).

## Sync & Search

- `SyncResult`: see [Syncing the Specification](Usage-Syncing#the-sync-result).
- `SearchHit`: see [Searching Topics](Usage-Search).

## Attribution

- `Attribution`: public `title` and `url`; `forTopic()`, `canonical()`, `toText()`, `toHtml()`, `toMarkdown()`, `toArray()`, and the static helpers `canonicalUrl()` (normalizes a URL to the upstream origin) and `isHttpUrl()`. The constants `SOURCE_NAME`, `SOURCE_URL`, `LICENSE_NAME`, `LICENSE_URL`, and `MAX_URL_LENGTH` hold the fixed parts.
- `RenderedTopic`: public `slug`, `attribution`, `placement`, and `surface`; `toHtml()`, `toMarkdown()`, `toArray()`, `jsonSerialize()`, `__toString()`. Links in both outputs are made absolute, and HTML headings are demoted by `render.heading_offset`.
- `TopicLinkResolver`: `forTopic()`, `resolve()` (returns the absolute URL, or `null` when the link should be dropped), `rewriteMarkdown()`. Resolved URLs are percent-encoded so they're always safe in a Markdown destination or an HTML attribute.

## MCP

- `McpTopic`: `slug` (local form), `category`, `status`, `title`, `summary`, `url`, `markdown`, `updatedAt`; `attribution()`, `toArray()`, `McpTopic::localSlug( $category, $slug )`.
- `McpChange`: `date`, `type`, `title`, `summary`, `topics`, `unresolvedSlugs`; `slugs()`, `toArray()`.
- `McpChangeSet`: `since`, `latest`, `changes`; `isEmpty()`, `slugs()`, `toArray()`.

## Enums

| Enum | Cases |
|------|-------|
| `SpecStatus` | `Required`, `Recommended`, `Optional`, `Avoid`. Methods: `label()`, `conformance()` |
| `CheckScope` | `Site`, `Page`, `Section`, `Block`, `Server`. Method: `isPageLevel()` |
| `ResultOutcome` | `Passed`, `Failed`, `Skipped`, `Errored` |
| `RenderSurface` | `Admin`, `Editor`, `Agent`, `Public`, `Paid`, `Marketing` |
| `AttributionPlacement` | `Before`, `After` |
| `ChangeType` | `Added`, `Changed`, `Status`, `Removed`. Method: `label()` |
