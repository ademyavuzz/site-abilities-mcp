# Site Abilities MCP

Site Abilities MCP exposes guarded WordPress content-management abilities through the official WordPress Abilities API and MCP Adapter.

It is designed for MCP clients such as Codex, VS Code, Claude, and Cursor. The plugin is independent, self-hosted, and does not send telemetry to the project maintainer.

> **Alpha software:** Install and evaluate this release on a staging site first. The default profile is read-only. Write abilities require deliberate server-side opt-in.

## Safety model

- Read-only MCP exposure by default.
- Native WordPress capability checks on every ability.
- Exact modified-time and SHA-256 checks before content mutations.
- WordPress revisions or bounded snapshots before recoverable changes.
- Explicit confirmation tokens for high-impact operations.
- No permanent content or media deletion.
- No arbitrary option changes, PHP editing, plugin installation, plugin deletion, updates, or theme switching.
- No credential storage and no project-hosted relay service.

MCP annotations are provided for client user experience, but server-side WordPress capabilities and validation remain the security boundary.

## Ability modules

The current alpha registers 56 abilities across these modules. The complete
inventory is available in [docs/ABILITIES.md](docs/ABILITIES.md).

- Pages and revisions
- Posts and opt-in custom post types
- Images and PDF media
- Classic navigation menus
- Native fallback SEO metadata
- WooCommerce simple products
- Allowlisted site settings and extension inventory
- WPBakery shortcode analysis

Only read-only abilities are MCP-public in the default profile. Optional integrations return a clear error when their dependency is unavailable.

## Requirements

- WordPress 6.9 or later.
- PHP 7.4 or later.
- [WordPress MCP Adapter](https://github.com/WordPress/mcp-adapter) installed and active.
- Node.js 22 or later when using the optional `@automattic/mcp-wordpress-remote` bridge.
- A dedicated WordPress user and revocable Application Password, or OAuth when available.

## Install

1. Back up the WordPress database and files.
2. Download `site-abilities-mcp-0.1.0-alpha.1.zip` from the GitHub release assets. Do not use GitHub's automatic “Source code” archive as the WordPress installer package.
3. In WordPress, open **Plugins → Add New Plugin → Upload Plugin**.
4. Upload the release ZIP and activate **Site Abilities MCP**.
5. Install and activate the official MCP Adapter. Its official release asset is named `mcp-adapter.zip`.
6. Create a dedicated WordPress user with the minimum native capabilities needed.
7. Create an Application Password for that user, or configure OAuth when supported.
8. Connect the MCP client and discover abilities.

Activation only registers abilities. It does not edit, publish, trash, or delete site data.

## Codex configuration

Back up `~/.codex/config.toml`, then add a server with site-specific values. Do not commit this configuration.

```toml
[mcp_servers.my_wordpress]
command = "npx"
args = ["-y", "@automattic/mcp-wordpress-remote@0.4.0"]

[mcp_servers.my_wordpress.env]
WP_API_URL = "https://example.com/wp-json/mcp/mcp-adapter-default-server"
WP_API_USERNAME = "mcp-user"
WP_API_PASSWORD = "application-password"
OAUTH_ENABLED = "false"
```

Restart or reload the client, then verify:

```bash
codex mcp list
```

The default MCP Adapter exposes discovery through its adapter tools. Use `discover-abilities`, inspect the chosen ability, and execute it only after validating its arguments and expected state.

The three adapter tools are expected. Site Abilities MCP abilities are discovered
through those tools rather than appearing as 56 separate entries in `tools/list`.

## Enabling write abilities

The default `read_only` profile hides every write ability from MCP discovery. To expose guarded writes, add this explicit opt-in to `wp-config.php` above the “stop editing” line:

```php
define( 'SITE_ABILITIES_MCP_PROFILE', 'full_access' );
```

Before enabling it:

- Use a staging site first.
- Use a dedicated MCP user, not a primary administrator account.
- Review that user's WordPress role and object-level capabilities.
- Keep database and file backups.
- Revoke the Application Password when access is no longer needed.

The profile can also be supplied by trusted server-side code with the `samcp_profile` filter.

## Custom post types

Only standard posts are included in the generic content module by default. A trusted site plugin may explicitly allow additional public post types:

```php
add_filter(
    'samcp_allowed_post_types',
    static function ( $types ) {
        $types[] = 'event';
        return $types;
    }
);
```

The MCP user must still possess the relevant native capabilities for each object.

## Protected plugins

The MCP Adapter, Site Abilities MCP, WooCommerce, and WPBakery are protected from plugin-state changes by default. Trusted server-side code may extend the protection list:

```php
add_filter(
    'samcp_protected_plugins',
    static function ( $needles ) {
        $needles[] = 'my-critical-plugin/';
        return $needles;
    }
);
```

## Development

Run the dependency-free safety tests:

```bash
php tests/smoke.php
php tests/read-only-profile.php
```

Lint all PHP files:

```bash
find . -name '*.php' -print0 | xargs -0 -n1 php -l
```

See [CONTRIBUTING.md](CONTRIBUTING.md) for the contribution workflow and [SECURITY.md](SECURITY.md) for private vulnerability reporting.

## Scope and compatibility

- Classic menus are supported; block-navigation management is not yet included.
- WooCommerce support is limited to simple products.
- WPBakery support analyzes shortcode content and previews replacements; it does not remotely operate the visual editor.
- Native SEO output disables itself when Yoast SEO, Rank Math, or All in One SEO is detected. Direct manipulation of those plugins' private metadata is intentionally not included.
- Multisite and production-scale load testing remain planned work for the alpha series.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

WordPress, WooCommerce, WPBakery, Codex, Claude, Cursor, and VS Code are trademarks of their respective owners. This independent project is not endorsed by or affiliated with those trademark owners.
