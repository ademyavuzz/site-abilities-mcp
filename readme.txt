=== Site Abilities MCP ===
Contributors: ademyavuzz
Tags: mcp, abilities-api, ai, content, automation
Requires at least: 6.8
Requires PHP: 7.4
Stable tag: 0.1.0-alpha
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Guarded WordPress abilities for MCP clients, with read-only defaults, native permissions, stale-write checks, and recoverable changes.

== Description ==

Site Abilities MCP exposes a bounded WordPress administration surface through the official WordPress Abilities API and MCP Adapter.

The default profile publishes only read-only abilities. Guarded write abilities require deliberate server-side opt-in. Every ability also checks the authenticated WordPress user's native capabilities.

= Safety features =

* Read-only MCP exposure by default.
* Modified-time and SHA-256 stale-write checks.
* WordPress revisions or bounded snapshots before recoverable changes.
* Explicit confirmation tokens for high-impact operations.
* No permanent content or media deletion.
* No arbitrary options, PHP editing, extension installation/deletion/update, or theme switching.
* No stored credentials, telemetry, or project-hosted relay service.

= Modules =

* Pages, drafts, publishing, trash, revisions, and revision restoration.
* Posts and explicitly allowlisted custom post types.
* Image and PDF media management with MIME, URL, and size restrictions.
* Classic menus with checksums and snapshots.
* Native fallback SEO metadata with conflict detection.
* WooCommerce simple products when WooCommerce is active.
* Allowlisted site settings and extension inventory.
* WPBakery shortcode analysis and replacement preview.

== Requirements ==

* The official WordPress MCP Adapter must be installed and active.
* WordPress 6.9 or later is recommended because the Abilities API is included in core.
* WordPress 6.8 requires a compatible Abilities API installation.
* Node.js 22 or later is required only when using the optional @automattic/mcp-wordpress-remote bridge.
* Use a dedicated WordPress user and a revocable Application Password, or OAuth when supported.

== Installation ==

1. Back up WordPress files and the database.
2. Upload the official release ZIP through Plugins > Add New Plugin > Upload Plugin.
3. Activate Site Abilities MCP and the official MCP Adapter.
4. Create a dedicated, least-privilege WordPress user for MCP access.
5. Configure your MCP client with the full MCP Adapter endpoint.

Activation only registers abilities and safe frontend filters. It does not mutate site data.

== Frequently Asked Questions ==

= Are write abilities enabled immediately? =

No. The default profile exposes only read-only abilities. Review the security guidance in the GitHub README before deliberately enabling the full-access profile.

= Does the plugin store my MCP password? =

No. MCP client credentials are not stored by this plugin. Never commit credentials to Git.

= Can it permanently delete content? =

No. Permanent content and media deletion abilities are intentionally unavailable.

= Does it operate the WPBakery visual editor? =

No. It analyzes shortcode structure and previews content replacement. It does not remotely control the browser-based drag-and-drop UI.

== Privacy ==

The plugin does not send telemetry or contact a project-hosted service. It responds to authenticated requests sent to the site's MCP Adapter endpoint. The optional media URL importer contacts only the HTTPS URL explicitly supplied by an authenticated caller.

An MCP client may separately run the open-source `@automattic/mcp-wordpress-remote` bridge on the administrator's computer. That bridge is not bundled with or operated by this plugin.

== Changelog ==

= 0.1.0-alpha =

* Initial public alpha.
* Added 56 guarded abilities across eight modules.
* Added read-only default and explicit full-access opt-in.
* Added revisions, bounded snapshots, checksums, and confirmation guards.
* Removed site-specific names, content types, URLs, and configuration.
