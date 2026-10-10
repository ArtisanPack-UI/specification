---
type: Check
title: "The HTML doctype"
description: "Every HTML document must start with <!doctype html> as its first line."
resource: https://specification.website/spec/foundations/doctype/
tags:
  - foundations
  - required
source_updated_at: "2026-07-27T00:00:00.000Z"
sources:
  - id: html-living-standard-the-doctype
    title: "HTML Living Standard — The DOCTYPE"
    resource: https://html.spec.whatwg.org/multipage/syntax.html#the-doctype
    author: "WHATWG"
    mirror: ../references/html-living-standard-the-doctype.md
category: foundations
requirement: required
conformance: "MUST"
standard: ../references/html-living-standard-the-doctype.md
---

# The HTML doctype

> Every HTML document must start with <!doctype html> as its first line.

## Why it matters

Without a doctype, browsers fall back to quirks mode, which emulates legacy rendering.

## Verification

Run `document.compatMode` in the console. It should return `"CSS1Compat"`.

Primary standard: [HTML Living Standard — The DOCTYPE](../references/html-living-standard-the-doctype.md).
