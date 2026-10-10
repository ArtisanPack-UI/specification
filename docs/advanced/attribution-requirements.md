---
title: Attribution Requirements
---

# Attribution Requirements

The Website Specification's content is © The Website Specification contributors and licensed under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/). This package's code is MIT licensed. The two licenses cover different things: you can use, modify, and ship the code under MIT terms, but any **topic text** you display (titles, summaries, bodies, MCP excerpts) is covered by CC BY 4.0.

## What CC BY 4.0 Requires

Wherever topic text appears, you must give:

1. **The title** of the material (the topic title)
2. **The creator**: The Website Specification
3. **A link to the source**: the topic's canonical `https://specification.website/spec/…` URL
4. **The license**: CC BY 4.0, with a link to `https://creativecommons.org/licenses/by/4.0/`
5. **An indication of changes**, if you modified the text

You may not imply that The Website Specification endorses you or your product.

The `Attribution` class produces all four required elements:

> “[The HTML doctype](https://specification.website/spec/foundations/doctype/)” from [The Website Specification](https://specification.website), licensed under [CC BY 4.0](https://creativecommons.org/licenses/by/4.0/).

## How the Package Enforces It

- **At the renderer boundary.** `SpecTopicRenderer` is the only way to output a topic body, and every `RenderedTopic` format (HTML, Markdown, array, JSON, string cast) includes the attribution. See [Rendering Topics](Usage-Rendering-Topics).
- **In serialization.** `Topic::$body_md` is hidden from `toArray()`/`toJson()`, and `McpTopic::toArray()` leaves out the Markdown and includes the attribution.
- **Per row.** Each topic row stores its canonical attribution string in the `attribution` column.
- **By surface.** The `LicenseGuard` blocks rendering on `marketing` surfaces and places attribution first on `paid` surfaces. You can configure both lists under `artisanpack.specification.license`.

## What Consumers Must Do

| Situation | Requirement |
|-----------|-------------|
| Showing a topic body | Render through `specification()->render()`. Never echo `$topic->body_md`. |
| Showing only a title, summary, or search snippet | Link it to the topic's `resource_url` and show `Attribution::forTopic( $topic )` nearby. |
| An admin dashboard (e.g. Site Readiness) | Show the attribution on each topic drilldown and in the dashboard footer. |
| A paid product (e.g. an AI-powered audit) | Make attribution a first-class part of each result, not a footer. Render with `RenderSurface::Paid`. |
| Marketing pages | Do not embed topic text. Describe your product in your own words and link to specification.website. |
| AI agent output | Cite topic slugs and URLs; render quoted text with `RenderSurface::Agent`. |
| Translating or editing topic text | Say that the text was modified, in addition to the attribution. |
| Exporting data (JSON, CSV, API) | Include the attribution fields alongside any topic text. |

## Customising the Attribution Wording

The HTML, Markdown, and plain-text sentences use translation strings, so you can localise them. Keep every element (title, link, creator, license, and license link) in the translated sentence.

```php
// lang/de.json
{
    ":title from :source, licensed under :license.": ":title von :source, lizenziert unter :license."
}
```

## Further Reading

- [CC BY 4.0 legal code](https://creativecommons.org/licenses/by/4.0/legalcode)
- [Creative Commons: Recommended practices for attribution](https://wiki.creativecommons.org/wiki/Recommended_practices_for_attribution)
- [About The Website Specification](https://specification.website/about/)
