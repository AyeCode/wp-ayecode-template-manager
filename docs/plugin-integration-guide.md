# Plugin Integration Guide

This guide explains how to integrate the WP AyeCode Template Manager into your AyeCode plugins (GeoDirectory, UsersWP, Invoicing, etc.).

## Table of Contents

1. [Installation](#installation)
2. [Environment Detection](#environment-detection)
3. [Creating Templates](#creating-templates)
4. [Registering Templates for Display](#registering-templates-for-display)
5. [Template Restoration](#template-restoration)
6. [Complete Example](#complete-example)
7. [Available Hooks & Filters](#available-hooks--filters)

---

## Installation

### As a Composer Package

Add to your plugin's `composer.json`:

```json
{
  "require": {
    "ayecode/wp-ayecode-template-manager": "^1.0"
  }
}
```

Then run:
```bash
composer install
```

---

## Environment Detection

Before creating templates, detect the current environment to determine which page builder the user has active:

```php
// Get environment information
$environment = ayecode_get_environment();

// Example environment array:
// [
//   'block_theme'      => false,
//   'classic_theme'    => true,
//   'gutenberg'        => true,
//   'elementor'        => true,
//   'elementor_pro'    => true,
//   'wpbakery'         => false,
//   'divi'             => false,
//   'beaver_builder'   => false,
//   'brizy'            => false,
//   'oxygen'           => false,
//   'thrive_architect' => false,
//   'breakdance'       => false,
//   'siteorigin'       => false,
//   'bricks'           => false,
// ]

// Check for specific builder
if ( $environment['elementor'] ) {
    // Use Elementor templates
}
```

---

## Creating Templates

Templates should be created by your plugin (not the framework). The framework provides helper functions to make this easy.

### Step 1: Create Templates on Plugin Activation

```php
/**
 * Create templates for GeoDirectory plugin.
 */
function geodirectory_create_templates() {
    $environment = ayecode_get_environment();

    // Decide which builder to use (priority: Elementor > Divi > Gutenberg)
    $builder = 'gutenberg';
    if ( $environment['elementor'] ) {
        $builder = 'elementor';
    } elseif ( $environment['divi'] ) {
        $builder = 'divi';
    }

    // Create "Add Listing" page
    $add_listing_id = ayecode_create_template(
        'gd_add_listing',
        array(
            'type'      => 'page',
            'builder'   => $builder,
            'product'   => 'geodirectory',
            'page_args' => array(
                'post_title'   => __( 'Add Listing', 'geodirectory' ),
                'post_name'    => 'add-listing',
                'post_content' => '<!-- wp:geodir/add-listing /-->',
                'post_status'  => 'publish',
            ),
        )
    );

    // Store the ID for later use
    update_option( 'geodir_page_add_listing', $add_listing_id );

    // Create "Single Listing" layout
    $single_listing_id = ayecode_create_template(
        'gd_single_listing',
        array(
            'type'      => 'layout',
            'builder'   => $builder,
            'product'   => 'geodirectory',
            'page_args' => array(
                'post_title'   => __( 'Single Listing Layout', 'geodirectory' ),
                'post_content' => '', // Empty for builder-based design
                'post_status'  => 'publish',
            ),
        )
    );

    update_option( 'geodir_layout_single', $single_listing_id );
}
register_activation_hook( __FILE__, 'geodirectory_create_templates' );

// Also run on admin init to ensure templates exist
add_action( 'admin_init', 'geodirectory_create_templates' );
```

### Template Types

**`type: 'page'`** - Creates a real WordPress page
- Use for functional pages (Add Listing, User Dashboard, Search Results)
- Creates `post_type='page'` entry
- Requires `page_args` with `post_name` (slug)

**`type: 'layout'`** - Creates a hidden CPT entry
- Use for structural templates (Single Listing, Archive Layout)
- Creates `post_type='ayecode_template'` entry
- Not visible in WordPress admin

---

## Registering Templates for Display

After creating templates, register them so they appear in the Template Manager UI:

```php
/**
 * Register GeoDirectory templates in the Template Manager.
 */
add_filter( 'ayecode_register_templates', function( $templates, $environment ) {

    // Get the stored post IDs
    $add_listing_id = get_option( 'geodir_page_add_listing' );
    $single_listing_id = get_option( 'geodir_layout_single' );
    $archive_id = get_option( 'geodir_layout_archive' );

    // Determine which builder is active
    $builder = 'gutenberg';
    if ( $environment['elementor'] ) {
        $builder = 'elementor';
    } elseif ( $environment['divi'] ) {
        $builder = 'divi';
    }

    // Register GeoDirectory templates
    $templates['geodirectory'] = array(
        'group_label' => __( 'GeoDirectory', 'geodirectory' ),
        'group_icon'  => 'dashicons-location',

        'items' => array(
            'gd_add_listing' => array(
                'title'         => __( 'Add Listing Page', 'geodirectory' ),
                'description'   => __( 'Frontend submission form for users to add listings.', 'geodirectory' ),
                'post_id'       => $add_listing_id,
                'type'          => 'page',
                'builder'       => $builder,
                'preview_image' => plugins_url( 'assets/img/preview-add-listing.jpg', __FILE__ ),
                'capabilities'  => 'manage_options',
            ),

            'gd_single_listing' => array(
                'title'         => __( 'Single Listing Layout', 'geodirectory' ),
                'description'   => __( 'The structural design for individual listing pages.', 'geodirectory' ),
                'post_id'       => $single_listing_id,
                'type'          => 'layout',
                'builder'       => $builder,
                'preview_image' => plugins_url( 'assets/img/preview-single.jpg', __FILE__ ),
                'capabilities'  => 'manage_options',
            ),

            'gd_archive' => array(
                'title'         => __( 'Archive/Search Layout', 'geodirectory' ),
                'description'   => __( 'Listing grid and search results page.', 'geodirectory' ),
                'post_id'       => $archive_id,
                'type'          => 'layout',
                'builder'       => $builder,
                'preview_image' => plugins_url( 'assets/img/preview-archive.jpg', __FILE__ ),
                'capabilities'  => 'manage_options',
            ),
        ),
    );

    return $templates;
}, 10, 2 );
```

### Registration Array Structure

```php
[
  'product_slug' => [                    // Your plugin slug
    'group_label' => 'Product Name',     // Display name in sidebar
    'group_icon'  => 'dashicons-icon',   // WordPress dashicon or Font Awesome

    'items' => [
      'template_key' => [                // Unique key for this template
        'title'         => 'Template Title',
        'description'   => 'Template description',
        'post_id'       => 123,          // REQUIRED: The actual post ID
        'type'          => 'page',       // 'page' or 'layout'
        'builder'       => 'elementor',  // Which builder this uses
        'preview_image' => 'https://...', // Optional preview image URL
        'capabilities'  => 'manage_options', // Who can edit (default: 'edit_pages')
      ]
    ]
  ]
]
```

---

## Template Restoration

Allow users to restore templates to default state:

```php
/**
 * Provide default content when restoring templates.
 */
add_filter( 'ayecode_template_default_content', function( $content, $post_id, $template_key ) {

    // Check if this is our template
    if ( $template_key === 'gd_add_listing' ) {
        return '<!-- wp:geodir/add-listing /-->';
    }

    if ( $template_key === 'gd_single_listing' ) {
        // Return default Elementor/Gutenberg blocks
        return geodirectory_get_default_single_layout();
    }

    return $content;
}, 10, 3 );

/**
 * Custom restore logic (alternative approach).
 */
add_action( 'ayecode_before_restore_template', function( $post_id, $template_key ) {

    if ( $template_key === 'gd_add_listing' ) {
        // Custom restore logic
        wp_update_post( array(
            'ID'           => $post_id,
            'post_content' => geodirectory_get_default_content( $template_key ),
        ) );

        // Restore Elementor data if applicable
        if ( class_exists( '\Elementor\Plugin' ) ) {
            update_post_meta( $post_id, '_elementor_data', geodirectory_get_default_elementor_data() );
        }
    }
}, 10, 2 );
```

---

## Complete Example

Here's a complete integration example for a fictional plugin:

```php
<?php
/**
 * Plugin Name: My AyeCode Plugin
 * Description: Example plugin using Template Manager
 */

/**
 * Create templates on activation and admin_init.
 */
function myac_ensure_templates() {
    $environment = ayecode_get_environment();

    // Determine builder
    $builder = 'gutenberg';
    if ( $environment['elementor'] ) {
        $builder = 'elementor';
    }

    // Create main page
    $page_id = ayecode_create_template(
        'myac_main_page',
        array(
            'type'    => 'page',
            'builder' => $builder,
            'product' => 'my_plugin',
            'page_args' => array(
                'post_title'   => 'My Plugin Page',
                'post_name'    => 'my-plugin',
                'post_content' => '[my_plugin_shortcode]',
                'post_status'  => 'publish',
            ),
        )
    );

    update_option( 'myac_main_page_id', $page_id );
}
register_activation_hook( __FILE__, 'myac_ensure_templates' );
add_action( 'admin_init', 'myac_ensure_templates' );

/**
 * Register templates for display.
 */
add_filter( 'ayecode_register_templates', function( $templates, $environment ) {

    $page_id = get_option( 'myac_main_page_id' );
    $builder = $environment['elementor'] ? 'elementor' : 'gutenberg';

    $templates['my_plugin'] = array(
        'group_label' => 'My Plugin',
        'group_icon'  => 'dashicons-admin-generic',
        'items' => array(
            'myac_main_page' => array(
                'title'       => 'Main Page',
                'description' => 'The main plugin page',
                'post_id'     => $page_id,
                'type'        => 'page',
                'builder'     => $builder,
            ),
        ),
    );

    return $templates;
}, 10, 2 );

/**
 * Handle restoration.
 */
add_filter( 'ayecode_template_default_content', function( $content, $post_id, $template_key ) {
    if ( $template_key === 'myac_main_page' ) {
        return '[my_plugin_shortcode]';
    }
    return $content;
}, 10, 3 );
```

---

## Available Hooks & Filters

### Filters

**`ayecode_register_templates`** - Register your templates
- `@param array $templates` - Empty array to populate
- `@param array $environment` - Current environment detection

**`ayecode_before_create_template`** - Modify page args before creation
- `@param array $page_args` - WP page arguments
- `@param string $template_key` - Template key
- `@param array $config` - Full config array

**`ayecode_template_default_content`** - Provide default content for restoration
- `@param string $content` - Default content (empty)
- `@param int $post_id` - Post ID
- `@param string $template_key` - Template key

**`ayecode_template_edit_url`** - Override the edit URL
- `@param string $url` - Empty by default
- `@param int $post_id` - Post ID
- `@param string $builder` - Builder name
- `@param string $template_type` - 'page' or 'layout'

**`ayecode_template_environment`** - Extend environment detection
- `@param array $environment` - Detected environment

### Actions

**`ayecode_template_created`** - Fires after template creation
- `@param int $post_id` - Created post ID
- `@param string $template_key` - Template key
- `@param array $config` - Full config

**`ayecode_before_restore_template`** - Before restoration
- `@param int $post_id` - Post ID
- `@param string $template_key` - Template key

**`ayecode_template_restored`** - After restoration
- `@param int $post_id` - Post ID
- `@param string $template_key` - Template key

---

## Helper Functions Reference

```php
// Get environment
ayecode_get_environment( $force_refresh = false );

// Create template
ayecode_create_template( $template_key, $config );

// Get template ID by key
ayecode_get_template_id( $template_key );

// Get edit URL
ayecode_get_template_edit_url( $post_id );

// Restore template
ayecode_restore_template( $post_id );

// Delete template
ayecode_delete_template( $template_key );
```

---

## Best Practices

1. **Always check environment** before creating templates
2. **Store template IDs** in options for quick retrieval
3. **Use unique template keys** (prefix with plugin slug)
4. **Provide preview images** for better UX
5. **Implement restoration logic** to help users recover from mistakes
6. **Check if template exists** before creating (helper does this automatically)
7. **Use appropriate capabilities** for template editing permissions

---

## Troubleshooting

**Templates not showing in UI?**
- Ensure you're passing valid `post_id` in the registration filter
- Check that the post actually exists
- Verify the filter is running after templates are created

**Edit button not working?**
- Check that the builder is actually active
- Verify `post_id` is correct
- Check console for JavaScript errors

**Restore not working?**
- Implement `ayecode_template_default_content` filter
- Or use `ayecode_before_restore_template` action
- Check user capabilities

---

## Support

For issues or questions, please visit:
https://github.com/ayecode/wp-ayecode-template-manager/issues
