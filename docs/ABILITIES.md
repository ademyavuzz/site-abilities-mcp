# Ability Catalog

Site Abilities MCP registers 85 abilities. The default `read_only` profile
publishes the 38 read abilities marked **Read** below and keeps all 47 write
abilities private. The `full_access` profile makes the guarded write abilities
discoverable, but WordPress capabilities still determine whether a request may
run.

## Pages

- **Read:** `list-pages`, `get-page`, `list-page-revisions`, `get-page-revision`
- **Write:** `create-page-draft`, `update-page-draft`, `update-published-page`, `publish-page-draft`, `restore-page-revision`, `trash-page`, `restore-trashed-page`

## Content

- **Read:** `list-content`, `get-content`, `list-content-snapshots`
- **Write:** `create-content-draft`, `update-content-draft`, `update-published-content`, `publish-content-draft`, `trash-content`, `restore-trashed-content`, `restore-content-snapshot`

## Media

- **Read:** `list-media`, `get-media`
- **Write:** `upload-media-base64`, `import-media-url`, `update-media`, `trash-media`, `restore-trashed-media`

## Classic Menus

- **Read:** `list-menus`, `get-menu`, `list-menu-snapshots`
- **Write:** `add-menu-item`, `update-menu-item`, `remove-menu-item`, `restore-menu-snapshot`

## Native SEO

- **Read:** `get-seo`, `list-seo-snapshots`
- **Write:** `update-seo`, `restore-seo-snapshot`

## WooCommerce Simple Products

- **Read:** `list-products`, `get-product`, `list-product-snapshots`
- **Write:** `create-product-draft`, `update-product`, `publish-product-draft`, `trash-product`, `restore-trashed-product`, `restore-product-snapshot`

## Site Administration

- **Read:** `get-site-settings`, `list-plugins`, `list-themes`
- **Write:** `update-site-settings`, `restore-site-settings`, `set-plugin-state`

## WPBakery Analysis

- **Read:** `analyze-wpbakery-content`, `preview-wpbakery-replacement`
- **Write:** none

## Taxonomies and Terms

- **Read:** `list-taxonomies`, `list-terms`, `get-term`, `list-term-snapshots`
- **Write:** `create-term`, `update-term`, `assign-content-terms`, `restore-term-snapshot`

Taxonomies are restricted to public or REST-visible, UI-enabled taxonomies. Sites may further restrict this list with the `samcp_allowed_taxonomies` filter. Term deletion is intentionally unavailable.

## Comments

- **Read:** `list-comments`, `get-comment`, `list-comment-snapshots`
- **Write:** `reply-to-comment`, `moderate-comment`, `update-comment`, `restore-comment-snapshot`

Comment changes require the native `moderate_comments` capability. Moderation and content updates create bounded snapshots. Permanent comment deletion is intentionally unavailable.

## Gutenberg and Synced Patterns

- **Read:** `list-block-types`, `analyze-block-content`, `preview-block-replacement`, `list-synced-patterns`, `get-synced-pattern`
- **Write:** `create-synced-pattern-draft`, `update-synced-pattern`, `publish-synced-pattern-draft`, `trash-synced-pattern`, `restore-trashed-pattern`

Pattern writes validate block markup, use stale-write guards and create revisions when supported. The module does not edit theme files, block templates, template parts or global styles.

## Site Discovery and Activity

- **Read:** `get-site-overview`, `list-content-types`, `get-content-terms`, `list-ability-activity`
- **Write:** none

The activity list is restricted to administrators. It stores only the ability name, UTC execution time and WordPress user ID. Ability inputs, outputs, content and credentials are never recorded.

All registration names use the `site-abilities/` namespace. The MCP Adapter's
default server exposes them through its discovery, information, and execution
tools. It does not register every ability as an individual top-level MCP tool.

The plugin intentionally provides no permanent deletion, arbitrary option
editing, arbitrary PHP execution, plugin installation or deletion, software
updates, theme switching, user/role management, or block-theme file editing ability.
