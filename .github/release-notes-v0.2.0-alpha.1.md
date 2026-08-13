## Site Abilities MCP v0.2.0-alpha.1

This alpha release expands Site Abilities MCP to 85 guarded WordPress abilities while preserving a read-only default profile. It adds taxonomy, comment, Gutenberg synced-pattern, site-discovery, and privacy-preserving activity capabilities.

### Download

Download **`site-abilities-mcp-0.2.0-alpha.1.zip`** from the Assets section below. This is the WordPress-ready installer package.

Do not install GitHub's automatically generated **Source code (zip)** archive in WordPress. That archive contains the development repository and is intended for source review.

### Highlights

- 38 read abilities available in the default `read_only` profile.
- 47 guarded write abilities available only after explicit server-side opt-in.
- Public or REST-visible taxonomy discovery, term management, and content-term assignments.
- Comment reading, replies, recoverable moderation, and bounded snapshots.
- Gutenberg block analysis and guarded synced-pattern lifecycle operations.
- Safe site discovery and privacy-preserving ability activity metadata.
- WordPress runtime validation in CI in addition to PHP 7.4–8.4 lint and safety tests.

### Safety boundaries

- Write abilities remain hidden unless `SITE_ABILITIES_MCP_PROFILE` is set to `full_access` by a trusted site administrator.
- Native WordPress capability checks, explicit confirmations, stale-state guards, revisions, and bounded snapshots remain enforced.
- Permanent deletion, arbitrary option updates, user or role management, PHP execution, plugin installation or deletion, software updates, theme switching, and block-theme file editing are not exposed.
- Activity metadata excludes ability inputs, outputs, content, and credentials.

### Install or upgrade

1. Test on a staging site and back up WordPress files and the database.
2. Install and activate the official [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter).
3. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
4. Upload `site-abilities-mcp-0.2.0-alpha.1.zip` and activate or replace the existing plugin.
5. Use a dedicated WordPress user and a revocable Application Password or OAuth.
6. Keep the default read-only profile until the required write abilities and user capabilities have been reviewed.

### Requirements

- WordPress 6.9 or later
- PHP 7.4 or later
- Official WordPress MCP Adapter
- Node.js 22 or later only when using the optional remote bridge

### Verify the download

The release includes `site-abilities-mcp-0.2.0-alpha.1.zip.sha256` for integrity verification:

```bash
shasum -a 256 -c site-abilities-mcp-0.2.0-alpha.1.zip.sha256
```

See the [README](https://github.com/ademyavuzz/site-abilities-mcp#readme), [ability catalog](https://github.com/ademyavuzz/site-abilities-mcp/blob/main/docs/ABILITIES.md), and [security policy](https://github.com/ademyavuzz/site-abilities-mcp/blob/main/SECURITY.md) for configuration, scope, and reporting guidance.
