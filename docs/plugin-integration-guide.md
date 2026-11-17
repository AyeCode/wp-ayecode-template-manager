# Plugin Integration Guide

How to plug an AyeCode plugin (GeoDirectory, UsersWP, Invoicing, ...) into the Template Manager.

## What the Template Manager does

It gives users one screen (Appearance > Templates) that answers, for every template your plugin uses, "what renders this and where do I edit it". It works across three setups:

| Setup | What renders | Where Edit goes |
|---|---|---|
| Block theme | Your plugin's `wp_template` / `wp_template_part` files, or the user's customized copy | Site Editor |
| Classic theme | A `page` post whose content is your template | The page, in whichever builder built it (block editor, Elementor, Divi, Bricks...) |
| Classic theme plus a theme builder template with a matching condition | Elementor Pro, Bricks or Divi Theme Builder template | That builder's editor, with a warning that the page is bypassed |

State is derived, never stored as a post status:

- **Default**: page content matches your default (or the hash stamped at install), or no customized block template exists.
- **Customized**: content differs, or a customized block template exists.
- **Missing**: the page is gone or in the trash. Offers Recreate.
- **Draft**: a page-type template is not published. The URL would 404.

The package also tidies wp-admin > Pages: layout pages leave the "All" view and get their own "Templates" view, and every managed page gets a label like "GeoDirectory: Single Layout".

## Two kinds of template

- **page**: has a real front end URL (Add Listing, Search). Users manage it in Pages, put it in menus, change its slug.
- **layout**: a storage container that is never visited directly (Single Layout, Archive Item). Hidden from the "All" view of Pages.

## 1. Register templates

```php
add_filter( 'ayecode_register_templates', function ( $templates, $environment ) {
    $templates['my_plugin'] = [
        'group_label' => __( 'My Plugin', 'my-plugin' ),
        'group_icon'  => 'fa-solid fa-cube',

        // Optional: enables the "Add Template" button (per post type copies).
        'variants'    => [
            'bases'      => [ 'single' => __( 'Single Layout', 'my-plugin' ) ],
            'post_types' => [ 'my_cpt' => __( 'Items', 'my-plugin' ) ],
        ],

        'items' => [
            'single' => [
                'title'       => __( 'Single Layout', 'my-plugin' ),
                'description' => __( 'Displays one item.', 'my-plugin' ),
                'post_id'     => (int) get_option( 'my_plugin_page_single' ), // 0 when missing
                'fse_slug'    => 'my-single',       // block theme template slug
                'fse_type'    => 'wp_template',     // or 'wp_template_part'
                'type'        => 'layout',          // or 'page'
                'usage'       => __( 'Global', 'my-plugin' ),
                'global'      => true,
                'location'    => 'single',          // 'single' | 'archive' | '' (theme builder override detection)
                'page_key'    => 'single',          // opaque, handed back to you in callbacks
                'icon_class'  => 'fa-solid fa-file-lines',
            ],

            // A per post type override row, shown indented under its parent.
            'single_my_cpt' => [
                'title'       => __( 'Single Layout', 'my-plugin' ),
                'post_id'     => 456,
                'fse_slug'    => 'single-my_cpt',
                'type'        => 'layout',
                'usage'       => __( 'Items', 'my-plugin' ),
                'post_type'   => 'my_cpt',
                'parent_key'  => 'single',
                'is_override' => true,              // only override rows get Delete
                'location'    => 'single',
                'page_key'    => 'single',
            ],
        ],
    ];

    return $templates;
}, 10, 2 );
```

Register a row even when its page is missing (pass `post_id => 0`). The row shows as Missing with a Recreate action. Set `expected => false` on rows that should simply not appear when nothing exists.

The validated item also carries `product` (your group key) so callbacks can tell your templates from other plugins'.

## 2. Answer the callbacks

### Default content (required for state detection and Restore)

```php
add_filter( 'ayecode_template_default_content', function ( $content, $post_id, $template_key, $template_data ) {
    if ( 'my_plugin' !== $template_data['product'] ) {
        return $content;
    }

    return my_plugin_default_content( $template_data['page_key'] );
}, 10, 4 );
```

### Stamp pages when you create them

Call this right after creating a template page so the package knows what "untouched" looks like, even after you change your defaults in a later release:

```php
\AyeCode\Templates\Helpers::maybe_stamp_default_content( $page_id, $default_content );
```

It only stamps when the page still matches the default, so it is safe on every install run. Pages without a stamp are compared against the live default and stamped the first time they match.

### Page assignment and recreate

```php
// The user picked a different page in Template Settings, or the package recreated a missing one.
add_action( 'ayecode_template_assigned', 'my_plugin_store_page', 10, 4 );
add_action( 'ayecode_template_recreated', 'my_plugin_store_page', 10, 4 );

function my_plugin_store_page( $post_id, $template_key, $template_data, $product ) {
    if ( 'my_plugin' !== $product ) {
        return;
    }
    update_option( 'my_plugin_page_' . $template_data['page_key'], $post_id );
}

// Optional: recreate the page yourself (right slug, right option) and return its ID.
add_filter( 'ayecode_template_recreate', function ( $post_id, $template_key, $template_data, $product ) {
    if ( 'my_plugin' !== $product ) {
        return $post_id;
    }
    return my_plugin_install_page( $template_data['page_key'] );
}, 10, 4 );
```

### Per post type overrides (Add Template / Delete)

```php
add_action( 'ayecode_template_override_created', function ( $post_id, $base_key, $post_type, $product, $base, $is_fse ) {
    if ( 'my_plugin' !== $product || $is_fse ) {
        return; // block theme overrides (single-{cpt}) resolve through WordPress itself
    }
    my_plugin_set_cpt_page( $post_type, $base['page_key'], $post_id );
}, 10, 6 );

add_action( 'ayecode_template_override_deleted', function ( $post_id, $template_key, $template_data, $product, $is_fse ) {
    if ( 'my_plugin' !== $product || $is_fse ) {
        return;
    }
    my_plugin_set_cpt_page( $template_data['post_type'], $template_data['page_key'], 0 );
}, 10, 5 );
```

On classic themes the package clones the base page (content and builder meta) into a new page. On block themes it creates a `wp_template` post named `{location}-{post_type}` from the base template's content, so only bases with a `location` can be varied there.

## 3. Optional: theme builder providers

Elementor Pro, Bricks and Divi Theme Builder are detected out of the box. Add another builder by implementing `AyeCode\Templates\Overrides\Provider` and registering it:

```php
add_filter( 'ayecode_template_override_providers', function ( $providers, $environment ) {
    $providers[] = new My_Builder_Provider();
    return $providers;
}, 10, 2 );
```

## Hooks reference

Filters:

- `ayecode_register_templates( $templates, $environment )`
- `ayecode_template_default_content( $content, $post_id, $template_key, $template_data )`
- `ayecode_template_recreate( $post_id, $template_key, $template_data, $product )`
- `ayecode_template_override_providers( $providers, $environment )`
- `ayecode_template_environment( $environment )`
- `ayecode_template_manager_sections( $sections )`
- `ayecode_template_edit_url( $url, $post_id, $builder, $template_type )`
- `ayecode_before_create_template( $page_args, $template_key, $config )`
- `ayecode_hide_layout_pages( $hide )`

Actions:

- `ayecode_template_assigned( $post_id, $template_key, $template_data, $product )`
- `ayecode_template_recreated( $post_id, $template_key, $template_data, $product )`
- `ayecode_template_override_created( $post_id, $base_key, $post_type, $product, $base, $is_fse )`
- `ayecode_template_override_deleted( $post_id, $template_key, $template_data, $product, $is_fse )`
- `ayecode_before_restore_template( $post_id, $template_key, $template_data )`
- `ayecode_template_restored( $post_id, $template_key, $template_data )`
- `ayecode_template_created( $post_id, $template_key, $config )`

## Helpers

```php
use AyeCode\Templates\Helpers;
use AyeCode\Templates\Fse;
use AyeCode\Templates\State;

Helpers::get_environment();
Helpers::create_template( $key, $config );
Helpers::get_template_id_by_key( $key );
Helpers::get_template_edit_url( $post_id );
Helpers::stamp_default_content( $post_id, $content );
Helpers::maybe_stamp_default_content( $post_id, $content );
Helpers::restore_template( $post_id, $template_key, $template_data );
Helpers::recreate_template( $template_key, $template_data, $product );

Fse::find_template( $slug, $type );
Fse::is_customized( $template );
Fse::get_edit_url( $slug, $type );
Fse::reset( $slug, $type );

State::get( $template_key, $template_data, $environment );
```
