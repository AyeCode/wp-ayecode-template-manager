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
| `Loader` | Boots the package; checks for `AyeCode\SettingsFramework\Settings_Framework` dependency, then initializes `TemplateManager` (always) and `Settings` (admin only). |
| `Registry` | Singleton. Fires the `ayecode_register_templates` filter to collect template definitions from host plugins, validates structure, and caches results. **Source of truth** for what templates exist. |
| `TemplateManager` | Singleton. Registers custom post statuses (`customized`, `default`). Drives `get_templates_for_display()` which merges Registry data with live WordPress post status. |
| `Settings` | Extends `AyeCode\SettingsFramework\Settings_Framework`. Renders the admin UI under Appearance → Templates using a `list_table` interface. Handles all AJAX actions via `asf_execute_tool_ayecode-templates`. |
| `Router` | Static. Determines the correct edit URL for a template post — routes to FSE Site Editor for block themes, or detects active page builder via post meta for classic themes. |
| `Environment` | Static singleton. Detects block theme vs classic theme, and which page builders are active (Elementor, Divi, Beaver Builder, Bricks, Oxygen, Brizy, Thrive Architect, Breakdance, WPBakery, SiteOrigin). Results are cached and filterable via `ayecode_template_environment`. |
| `Helpers` | Static utility methods for host plugins: `create_template()`, `get_template_id_by_key()`, `mark_template_customized()`, `restore_template()`, `get_templates_by_product()`. |

### How host plugins register templates

Host plugins hook into `ayecode_register_templates` and return a structured array:

```php
add_filter( 'ayecode_register_templates', function( $templates, $environment ) {
    $templates['my_plugin'] = [
        'group_label' => 'My Plugin',
        'group_icon'  => 'dashicons-admin-generic',
        'items' => [
            'my_template_key' => [
                'title'    => 'My Template',
                'post_id'  => 123,          // for classic themes
                'fse_slug' => 'my-template', // for block themes
                'fse_type' => 'wp_template',
                'type'     => 'page',        // or 'layout'
                'usage'    => 'Archive page',
                'global'   => false,
            ],
        ],
    ];
    return $templates;
}, 10, 2 );
```

A template item must have either `post_id` or `fse_slug` to pass validation.

### Key hooks and filters

- `ayecode_register_templates` — register template definitions (host plugins)
- `ayecode_template_environment` — extend/override environment detection
- `ayecode_template_manager_sections` — inject additional sidebar sections into the Settings UI
- `ayecode_before_create_template` — filter `wp_insert_post` args before template creation
- `ayecode_template_created` / `ayecode_before_restore_template` / `ayecode_template_restored` — lifecycle actions
- `ayecode_template_default_content` — provide default content when restoring a template
- `ayecode_template_edit_url` — override the edit URL for a specific template post

### Post meta keys

Templates are stored as WordPress `page` posts with these meta keys:
- `_ayecode_template_key` — unique template identifier
- `_ayecode_template_type` — `page` or `layout`
- `_ayecode_template_product` — product slug
- `_ayecode_template_builder` — builder slug
- `_ayecode_original_status` — stored before marking customized, used for restore
