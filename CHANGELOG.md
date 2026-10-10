# ArtisanPack UI — Specification Changelog

## [Unreleased]

## [1.0.0] - 2026-10-10

### Added
- OKF ingest from specification.website: a bounded in-memory `tar.gz` reader (header checksums, size and entry caps, links and devices ignored), an allow-list for archive paths, a YAML front-matter topic parser, and an atomic on-disk snapshot store.
- Idempotent sync versioned by the archive's SHA-256, with a cache lock against overlapping runs, a weekly scheduled sync, and `specification()->sync()`.
- `specification_topics` and `specification_snapshots` tables with the `Topic` and `Snapshot` models. Each snapshot records the slugs its sync created, updated, and deleted.
- Local SQLite FTS5 search index (`SearchIndex` contract, `SqliteFtsIndex`) that is independent of the app's database connection. It rebuilds itself from the topic rows when it doesn't match the current snapshot, so multi-server and ephemeral-filesystem deployments work.
- `SpecCheck` contract, `CheckRegistry` (direct registration, config, and container tag), `CheckRunner`, `SpecContext`, `SpecResult`, `SpecFix`, and the opt-in `foundations/doctype` reference check.
- `SpecReport`, `TierBreakdown`, and `TierScore` four-tier reporting (Required, Recommended, and Optional percentages; Avoid as a count of violations).
- CC BY 4.0 attribution: `Attribution`, `LicenseGuard`, `SpecTopicRenderer`, `RenderedTopic`, and `RenderSurface`. Marketing surfaces are blocked, and paid surfaces put the attribution first.
- `TopicLinkResolver`, which makes relative topic links (`../references/*.md`, `/spec/...`, sibling `*.md`) absolute in rendered HTML and Markdown.
- `render.heading_offset` config (default `1`). Rendered topic headings are demoted so topics don't add an `<h1>` to the host page, and the wrapper carries `lang="en"`.
- Optional `HttpMcpClient` for the specification.website MCP server (`list_topics`, `get_topic`, `get_changes`, `search`), disabled by default.
- `specification()->agentReadinessExperimentalEnabled()` for owner packages gating experimental Agent Readiness checks.
- Artisan commands `keystone:spec-sync`, `keystone:spec-diff`, and `keystone:spec-check`.
- Package scaffold from `package-blueprint`: service provider, facade, `specification()` helper, publishable `artisanpack.specification` config and migrations, `artisanpack-ui/code-style` linting, GitHub Actions CI, and the tag-driven release workflow.

### Security
- Attribution output only links `http(s)` URLs, and topic URLs from the MCP server are normalized to the upstream origin with `Attribution::canonicalUrl()`, so a `javascript:` URL can't reach an `href`.
- Topic Markdown is rendered with raw HTML escaped and unsafe link protocols dropped.
- Downloads and MCP calls are `https`-only with response size caps enforced while streaming. Console output from upstream text is stripped of control characters and escaped.

### Fixed (pre-release audit)
- `DoctypeCheck` no longer treats doctype-like text inside comments or `script`, `style`, `textarea`, and `title` elements as a misplaced doctype, and its autofix no longer deletes that text.
- One unusable check (an invalid slug or an unresolvable dependency) is now reported and set aside (`CheckRegistry::invalid()`) instead of aborting every check run or breaking the package. `keystone:spec-check` lists it and exits with `1`.
- Sync updates the database first and wraps apply failures in `SyncFailedException`. Oversized slugs or titles become per-topic errors, unknown conformance keywords are stored as `null`, and an unchanged archive repairs a stale on-disk snapshot.
- The SQLite index uses a busy timeout and WAL, so searches survive concurrent rebuilds, and search tokens keep combining marks.
- MCP list and change limits are clamped to the documented ranges, and a non-string JSON-RPC error message no longer raises an `ErrorException`.
- `keystone:spec-diff` sends UTC days to the MCP server and passes `--since` through as typed. `keystone:spec-check` warns when the URL returns an error status.
- Attribution quote marks are now part of the translatable strings.
