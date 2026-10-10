---
type: Check
title: "X-XSS-Protection"
description: "Do not send the X-XSS-Protection header; no shipping browser reads it."
resource: https://specification.website/spec/security/x-xss-protection/
tags:
  - security
  - avoid
source_updated_at: "2026-08-08T00:00:00.000Z"
sources:
  - id: mdn-x-xss-protection
    title: "MDN — X-XSS-Protection"
    resource: https://developer.mozilla.org/en-US/docs/Web/HTTP/Headers/X-XSS-Protection
    author: "MDN"
category: security
requirement: avoid
conformance: "MUST NOT"
standard: ../references/mdn-x-xss-protection.md
---

# X-XSS-Protection

> Do not send the X-XSS-Protection header.

## Why it matters

The legacy XSS auditor header is ignored by every current browser and can harm safe pages.
