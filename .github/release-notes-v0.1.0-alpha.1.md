## Site Abilities MCP v0.1.0-alpha.1

This is the first public evaluation build of Site Abilities MCP. It exposes 56 guarded WordPress abilities through the official WordPress Abilities API and MCP Adapter.

### Download

Download **`site-abilities-mcp-0.1.0-alpha.1.zip`** from the Assets section below. This is the WordPress-ready installer package.

Do not install GitHub's automatically generated **Source code (zip)** archive in WordPress. That archive contains development files and is intended for source review.

### Install

1. Test on a staging site and back up WordPress files and the database.
2. Install and activate the official [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter).
3. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
4. Upload `site-abilities-mcp-0.1.0-alpha.1.zip` and activate the plugin.
5. Use a dedicated WordPress user and a revocable Application Password or OAuth.

The plugin starts in the **read-only** MCP profile. Write abilities remain hidden until a site administrator deliberately enables the documented server-side opt-in.

### Requirements

- WordPress 6.9 or later
- PHP 7.4 or later
- Official WordPress MCP Adapter
- Node.js 22 or later only when using the optional remote bridge

### Verify the download

The release includes `site-abilities-mcp-0.1.0-alpha.1.zip.sha256` for integrity verification:

```bash
shasum -a 256 -c site-abilities-mcp-0.1.0-alpha.1.zip.sha256
```

Expected SHA-256:

```text
8f48c3ea9c8a7b2f3eceb57583f703db848fe175943a353c00e7caedfb56cde2
```

See the [README](https://github.com/ademyavuzz/site-abilities-mcp#readme) for client configuration, security guidance, supported operations, and known limitations.
