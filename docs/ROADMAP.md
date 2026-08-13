# Capability Roadmap

Site Abilities MCP prioritizes guarded, composable workflows over raw tool count. Every new write surface must define native WordPress capability checks, bounded inputs, expected-state validation, confirmation requirements, readback, and a recovery path where WordPress can provide one.

## Current development surface

Version 0.2 development expands the plugin from 56 to 85 abilities:

- Public or REST-visible taxonomies, recoverable term changes, and guarded content-term assignments
- Comment reading, replies, moderation, recoverable content changes, and comment snapshots
- Gutenberg block discovery, structural comparison, and revision-backed synced-pattern management
- Safe site and content-type discovery
- Privacy-preserving ability activity metadata

The design follows the official [WordPress Abilities API](https://developer.wordpress.org/apis/abilities-api/) registration, schema, permission, and execution model. MCP exposure continues through the official [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter).

## Evaluated ecosystem patterns

The project reviewed current open-source WordPress MCP implementations, including:

- [Automattic/wordpress-mcp](https://github.com/Automattic/wordpress-mcp), now directing ongoing adapter work to the official WordPress project
- [AgriciDaniel/wp-mcp-ultimate](https://github.com/AgriciDaniel/wp-mcp-ultimate), which demonstrates broad core administration coverage
- [anotherpanacea-eng/anotherpanacea-wordpress-mcp](https://github.com/anotherpanacea-eng/anotherpanacea-wordpress-mcp), which demonstrates editorial, taxonomy, comment, block, and audit workflows
- [cosmincraciun97/stonewright-wp-mcp](https://github.com/cosmincraciun97/stonewright-wp-mcp), which emphasizes plan, approval, backup, readback, audit, and restore contracts
- [5unnykum4r/wordpress-mcp](https://github.com/5unnykum4r/wordpress-mcp), which demonstrates taxonomy, comments, redirects, patterns, and extension integrations

Site Abilities MCP adopts compatible ideas only when they fit its smaller security boundary. It does not copy third-party code and does not use tool count as a quality target.

## Candidate next modules

These are candidates for later releases, not promises:

1. Scheduled publishing with timezone-aware validation and explicit rescheduling confirmation
2. Featured-image assignment with content and media checksums
3. Block navigation entities with revision-backed writes when core APIs provide a stable recovery contract
4. Redirect management through an explicit provider interface rather than private plugin metadata
5. WooCommerce variable products, categories, attributes, and inventory workflows
6. Read-only site health, update availability, cron, and cache diagnostics
7. Optional audit retention controls and export without storing ability payloads
8. Multisite-aware discovery and per-site safety boundaries

## Deliberate exclusions

The following remain outside the normal MCP surface:

- Permanent content, media, term, or comment deletion
- Arbitrary WordPress option mutation
- User, role, capability, or Application Password management
- PHP execution and arbitrary database queries
- Plugin or theme installation, deletion, updating, or file editing
- Theme switching, template file writes, and unrestricted global-style mutation
- Unbounded filesystem, process, shell, or network access

These exclusions reduce the impact of compromised credentials, ambiguous agent instructions, and accidental destructive operations.

## Acceptance criteria for a new ability

A proposed ability should not be merged unless it has:

- A narrow action-oriented name and complete input/output schemas
- A native WordPress capability check against the target object or subsystem
- An allowlist for extensible object types, taxonomies, settings, MIME types, or providers
- Stale-state validation for updates to existing data
- Explicit confirmation for destructive or externally visible changes
- A revision, snapshot, trash, or other recovery mechanism where feasible
- A readback result that allows the client to verify the final state
- Read-only profile coverage and a regression test
- Documentation of its limits and failure behavior
