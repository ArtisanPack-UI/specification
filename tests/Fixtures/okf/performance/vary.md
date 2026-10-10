---
type: Check
title: "The Vary header"
description: "List every request header a response is negotiated on in Vary, and nothing more."
resource: https://specification.website/spec/performance/vary/
tags:
  - performance
  - recommended
source_updated_at: "2026-09-10T00:00:00.000Z"
sources:
  - id: rfc-9110-vary
    title: "RFC 9110 — Vary"
    resource: https://www.rfc-editor.org/rfc/rfc9110#field.vary
    author: "IETF"
category: performance
requirement: recommended
conformance: "SHOULD"
standard: ../references/rfc-9110-vary.md
---

# The Vary header

> List every request header a response is negotiated on in Vary.

## Why it matters

Vary extends the cache key; it does not govern freshness.
