---
title: FAQ
---

# Frequently Asked Questions

## General

### Does this package check my site out of the box?

Mostly no. The package provides the registry, runner, reports, and content. The checks come from owner packages such as `seo`, `security`, and `accessibility`. The reference `foundations/doctype` check is included but opt-in. See [Authoring SpecChecks](Advanced-Authoring-Checks).

### Why is there no overall score?

The four tiers are not comparable. A missing Required item and a missing Optional item should never average out. `SpecReport` reports each tier separately, plus a count of Avoid practices present. See [Reports](Usage-Reports).

### Does it work offline?

Yes. Checks, search, and rendering all use the local snapshot. Only syncing (unless you pass `--archive=`) and the optional MCP client need the network.

## Syncing

### How often should I sync?

The package schedules a weekly sync. Run `keystone:spec-diff` with the MCP client enabled to see whether upstream has moved since then.

### Is it safe to run the sync on cron?

Yes. An unchanged archive is a no-op, unchanged rows are never rewritten, and a lock prevents overlapping runs. See [Syncing the Specification](Usage-Syncing#idempotency).

### What happens if a topic fails to parse?

It is reported in the sync result's `errors`, and its previously synced row is kept.

## MCP

### Do I need the MCP client?

No. It is off by default and is only used to tell you what has changed upstream since your last sync. The OKF snapshot is always authoritative.

### Why are MCP slugs different from local slugs?

The upstream server uses bare slugs (`doctype`). The client converts them to the local `category/topic` form (`foundations/doctype`) for you.

## Licensing

### Can I use the package in a commercial product?

The code is MIT licensed. The specification content is CC BY 4.0, which allows commercial use as long as you give attribution. See [Attribution Requirements](Advanced-Attribution-Requirements).

### Why does rendering on a marketing surface throw?

Topic text is not to be remixed into marketing copy. The `LicenseGuard` blocks the `marketing` surface by default. Describe your product in your own words and link to specification.website instead.
