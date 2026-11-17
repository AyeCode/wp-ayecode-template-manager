# CLAUDE.md

## Commands

**Lint (PHP CodeSniffer):**
```bash
vendor/bin/phpcs
vendor/bin/phpcbf   # auto-fix
```

The ruleset is `phpcs.xml.dist` — enforces WordPress-Extra standards with PSR-4 filenames allowed in `src/`, text domain locked to `ayecode-connect`.

## Architecture

This is a **Composer library** (`ayecode/wp-ayecode-template-manager`) intended to be pulled into AyeCode plugins (GeoDirectory, UsersWP, Invoicing). It can also run as a standalone WordPress plugin.

### Package Loader (entry point)

`wp-ayecode-template-manager.php` contains the **AyeCode Package Loader** pattern. When multiple plugins include this package at different versions, a version negotiation runs at `plugins_loaded` priority 1 — only the highest version wins and registers its autoloader (priority 2) and boots `Loader` (priority 10). Do not modify the loader boilerplate below the `DO NOT EDIT` line.

### Class Map (`src/`, namespace `AyeCode\Templates\`)

| Class | Role |
|---|---|
| `Loader` | Boots the package; checks for `AyeCode\SettingsFramework\Settings_Framework` dependency, then initializes `TemplateManager` (always) and `Settings` plus `PagesList` (admin only). |
| `Registry` | Singleton. Fires the `ayecode_register_templates` filter to collect template definitions from host plugins, validates structure, and caches results. **Source of truth** for what templates exist. |
| `TemplateManager` | Singleton. Builds the display rows: derived state, source, edit URL, override info. Row IDs are `product:key`. |
| `State` | Static. Derives `default|customized|missing|draft` from the page content hash (classic) or the customized block template (block themes). Stamps `_ayecode_template_default_hash`. |
| `Fse` | Static. Block template lookup, Site Editor URLs, reset (delete customized copy) and creation of per post type `wp_template` overrides. |
| `Router` | Static. Edit URL and "edited with" label: theme builder override, Site Editor, or the page's builder detected from post meta. |
| `Overrides\Manager` + `Overrides\Provider` | Theme builder override detection. Providers: `ElementorPro`, `Bricks`, `Divi`. Filter `ayecode_template_override_providers`. |
| `Settings` | Extends `AyeCode\SettingsFramework\Settings_Framework`. Renders Appearance > Templates and handles AJAX (`get_templates`, `create_override`, `update_template`, `delete_override`, `restore_template`, `recreate_template`). |
| `PagesList` | wp-admin > Pages integration: hides layout pages from All, adds a "Templates" view, post state labels and a row action. Filter `ayecode_hide_layout_pages`. |
| `Environment` | Static singleton. Detects block theme vs classic theme, and which page builders are active. Results are cached and filterable via `ayecode_template_environment`. |
| `Helpers` | Static utility methods for host plugins: `create_template()`, `stamp_default_content()`, `maybe_stamp_default_content()`, `restore_template()`, `recreate_template()`. |

### How host plugins register templates

See `docs/plugin-integration-guide.md`. Items need `post_id` and/or `fse_slug`, `type` (`page` or `layout`), and should pass `location`, `page_key`, `post_type`, `parent_key`, `is_override` where relevant. Group level `variants` enables Add Template.

### Key hooks and filters

- `ayecode_register_templates` - register template definitions (host plugins)
- `ayecode_template_default_content` - default content for state detection and restore (host plugins)
- `ayecode_template_recreate` / `ayecode_template_recreated` / `ayecode_template_assigned` - page lifecycle callbacks
- `ayecode_template_override_created` / `ayecode_template_override_deleted` - per post type overrides
- `ayecode_template_override_providers` - add theme builder providers
- `ayecode_template_environment` - extend/override environment detection
- `ayecode_template_manager_sections` - inject additional sidebar sections into the Settings UI
- `ayecode_hide_layout_pages` - keep layout pages in the Pages "All" view
- `ayecode_before_restore_template` / `ayecode_template_restored` / `ayecode_template_created` - lifecycle actions
- `ayecode_template_edit_url` - override the edit URL for a specific template post

### Post meta keys

Template state is never a post status. Pages carry these meta keys:
- `_ayecode_template_key` — unique template identifier
- `_ayecode_template_type` — `page` or `layout`
- `_ayecode_template_product` — product slug
- `_ayecode_template_builder` — builder slug
- `_ayecode_template_default_hash` — hash of the default content, used to derive Default vs Customized
- `_ayecode_template_post_type` — post type of a per post type override page
