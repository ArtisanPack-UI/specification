---
title: Rendering Topics
---

# Rendering Topics

Topic text is © The Website Specification contributors under CC BY 4.0. The package enforces attribution at the renderer boundary. A rendered topic always carries its attribution, and there is no accessor that returns the body on its own.

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
| `toHtml()` / `(string)` | `<div class="spec-topic" data-spec-slug="…">` containing the body HTML and a `<p class="spec-attribution">` |
| `toMarkdown()` | Body and attribution separated by `---` |
| `toArray()` / `json_encode()` | `slug`, `surface`, `placement`, `body_md`, and the attribution fields |

`RenderedTopic` implements `Htmlable`, so in Blade you can write:

```blade
{{ specification()->render( $topic ) }}
```

Topic Markdown comes from a third party. The renderer escapes raw HTML in it and drops unsafe link protocols such as `javascript:`.

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

See [Attribution Requirements](Advanced-Attribution-Requirements) for what consumers must honour.
