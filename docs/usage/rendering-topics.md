---
title: Rendering Topics
---

# Rendering Topics

Topic text is © The Website Specification contributors under CC BY 4.0. The renderer is the supported, attribution-safe way to output it. A rendered topic always carries its attribution, and `RenderedTopic` has no accessor that returns the body on its own.

> **Warning:** `Topic::$body_md` is still readable as a model attribute. Printing it directly bypasses the renderer and outputs the text without attribution, so don't.

## Rendering

```php
use ArtisanPackUI\Specification\Enums\RenderSurface;

$topic    = specification()->topic( 'foundations/doctype' );
$rendered = specification()->render( $topic );                      // Admin surface
$rendered = specification()->render( $topic, RenderSurface::Paid ); // attribution placed first
$rendered = $topic->render( RenderSurface::Agent );                 // same thing, via the model
```

A `RenderedTopic` can be output in several formats, and every one includes the attribution:

| Method | Output |
|--------|--------|
| `toHtml()` / `(string)` | `<div class="spec-topic" lang="en" dir="ltr" data-spec-slug="…">` containing the body HTML and a `<p class="spec-attribution">` |
| `toMarkdown()` | Body and attribution separated by `---` |
| `toArray()` / `json_encode()` | `slug`, `surface`, `placement`, `body_md`, and the attribution fields |

`RenderedTopic` implements `Htmlable`, so in Blade you can write:

```blade
{{ specification()->render( $topic ) }}
```

Topic Markdown comes from a third party. The renderer escapes raw HTML in it and drops unsafe link protocols such as `javascript:`.

## Headings and Language

Every topic body starts with a `#` title. In HTML output, body headings are demoted by `artisanpack.specification.render.heading_offset` levels (default `1`, so the title becomes an `<h2>`) to keep your page's heading outline intact. Set it to `0` to keep the original levels. Markdown output is unchanged.

The wrapper carries `lang="en"` and `dir="ltr"` because topic text is English, so screen readers pronounce it correctly in a localized app.

## Links

OKF topic bodies link relative to the bundle and to the upstream site, which would break once the text is displayed on your site. The renderer makes every link absolute in both `toHtml()` and `toMarkdown()`:

| Link in the topic body | Rendered as |
|------------------------|-------------|
| `../references/{source-id}.md` | The cited source's own URL (from the topic's `sources`) |
| `/spec/security/hsts/`, `/llms.txt` | `https://specification.website/spec/security/hsts/`, `https://specification.website/llms.txt` |
| `hsts.md`, `../security/hsts.md` | `https://specification.website/spec/security/hsts/` |
| `https://…`, `mailto:…`, `#fragment` | Unchanged |

A link that can't be resolved (an unknown reference, a path outside the bundle, or an unsafe scheme) is dropped and its text is kept. Code blocks and inline code are left alone. `TopicLinkResolver` does the resolving, and you can use it directly when you handle topic Markdown yourself:

```php
use ArtisanPackUI\Specification\Attribution\TopicLinkResolver;

$links = TopicLinkResolver::forTopic( $topic );

$links->resolve( '../references/html-living-standard-the-doctype.md' );
$links->rewriteMarkdown( $markdown );
```

## Surfaces

The `RenderSurface` you pass tells the `LicenseGuard` where the text will appear:

| Surface | Default behaviour |
|---------|-------------------|
| `Admin`, `Editor`, `Agent`, `Public` | Rendered with attribution after the body |
| `Paid` | Rendered with attribution **before** the body, so it reads as part of the result rather than a footer |
| `Marketing` | **Blocked**: throws `LicenseViolationException` |

You can change both lists in `artisanpack.specification.license`.

## Serialization Safety

`Topic::$body_md` is hidden from `toArray()` and `toJson()`, so returning a `Topic` from a controller never leaks topic text without its attribution. Return a `RenderedTopic` instead.

## Attribution on Its Own

When you show only a title or summary, for example in a search result, attach the attribution yourself:

```php
use ArtisanPackUI\Specification\Attribution\Attribution;

$attribution = Attribution::forTopic( $topic );

$attribution->toHtml();     // linked title, source, and license
$attribution->toMarkdown();
$attribution->toText();
```

Attribution output only links `http` and `https` URLs. Any other scheme (for example `javascript:`) is rendered as plain text. Use `Attribution::canonicalUrl( $slug, $url )` to normalize a URL from an untrusted source to the upstream origin.

See [Attribution Requirements](Advanced-Attribution-Requirements) for what consumers must honour.
