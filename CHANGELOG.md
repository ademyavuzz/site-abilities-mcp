# Changelog

This project follows Semantic Versioning once it reaches a stable `1.0.0` release. Alpha releases may still change ability names and schemas.

## 0.2.0-alpha.1 - Unreleased

### Added

- Taxonomy discovery, term creation, recoverable term updates and guarded content-term assignments.
- Comment reading, replies, moderation, content updates and bounded recovery snapshots.
- Gutenberg block type discovery, structural analysis, replacement preview and synced-pattern lifecycle abilities.
- Safe site/content-type discovery and privacy-preserving ability activity metadata.

### Security

- All new writes remain hidden in the default read-only profile.
- Term, comment and pattern mutations use native WordPress capabilities, explicit confirmations and stale-state guards.
- Activity records deliberately exclude ability inputs, outputs, content and credentials.
- Permanent deletion, arbitrary options, user/role management and code/theme-file editing remain unavailable.

## 0.1.0-alpha.1 - 2026-08-14

- Published the first independent open-source alpha.
- Added 56 guarded WordPress abilities across eight modules.
- Made read-only MCP exposure the default.
- Added explicit server-side opt-in for guarded write abilities.
- Added WordPress capability checks, stale-write detection, revisions, bounded snapshots, and confirmation tokens.
- Removed organization-specific names, content types, URLs, and configuration.
- Added Codex setup instructions, security policy, contribution guide, automated tests, and release packaging.
