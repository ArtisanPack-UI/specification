---
title: Advanced Overview
---

# Advanced Overview

This section is for owner packages that implement checks, and for applications that display specification content or automate the package from the command line.

## Features

### Authoring SpecChecks

Implement the `SpecCheck` contract, register it from your service provider, and optionally offer an autofix. The guide walks through the in-package `foundations/doctype` reference check.

[Learn more about Authoring SpecChecks →](Advanced-Authoring-Checks)

### Testing SpecChecks

Patterns for testing checks with deterministic contexts, testing autofixes without side effects, and faking the MCP client.

[Learn more about Testing SpecChecks →](Advanced-Testing-Checks)

### Attribution Requirements

What CC BY 4.0 requires of every consumer that displays specification text, and how the package enforces it.

[Learn more about Attribution Requirements →](Advanced-Attribution-Requirements)

### Artisan Commands

`keystone:spec-sync`, `keystone:spec-diff`, and `keystone:spec-check`.

[Learn more about Artisan Commands →](Advanced-Artisan-Commands)

### Deployment & Operations

Where the package keeps its data, the scheduled sync, multi-server and ephemeral-filesystem deployments, offline syncs, and monitoring.

[Learn more about Deployment & Operations →](Advanced-Deployment)
