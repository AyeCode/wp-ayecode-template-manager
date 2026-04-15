<?php
/**
 * Plugin Name: WP AyeCode Template Manager
 * Plugin URI: https://ayecode.io/
 * Description: Centralized template management hub for the AyeCode ecosystem.
 * Version: 3.0.3-beta
 * Author: AyeCode Ltd
 * Author URI: https://ayecode.io/
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain: wp-ayecode-template-manager
 * Domain Path: /languages
 * Requires at least: 5.0
 * Requires PHP: 7.4
 *
 * @package WP_AyeCode_Template_Manager
 */

// Exit if accessed directly.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// AyeCode Package Loader (v1.0.0)
( function () {
    // -------------------------------------------------------------------------
    // CONFIGURATION
    // -------------------------------------------------------------------------
    $registry_key    = 'ayecode_template_manager_registry';
    $this_version    = '3.0.3-beta';
    $this_path       = dirname( __FILE__ );
    $prefix          = 'AyeCode\\Templates\\';

    // Class Bootstrapping
    $loader_class    = 'AyeCode\\Templates\\Loader'; // Leave empty '' to disable
    $loader_hook     = 'plugins_loaded';
    $loader_priority = 10;

    // Constants to define ONLY if this package version wins the negotiation
    // Leave array empty if your package doesn't require any path/version constants
    $winning_constants = [
            'AYECODE_TEMPLATE_MANAGER_VERSION'     => $this_version,
            'AYECODE_TEMPLATE_MANAGER_PLUGIN_DIR'  => $this_path . '/',
            'AYECODE_TEMPLATE_MANAGER_PLUGIN_FILE' => $this_path . '/wp-ayecode-template-manager.php',
    ];
    // -------------------------------------------------------------------------
    // DO NOT EDIT BELOW THIS LINE. CORE PACKAGE NEGOTIATION LOGIC.
    // -------------------------------------------------------------------------

    /**
     * Step 1: Version Negotiation (Priority 1)
     */
    add_action( 'plugins_loaded', function () use ( $registry_key, $this_version, $this_path ) {
        if ( empty( $GLOBALS[$registry_key] ) || version_compare( $this_version, $GLOBALS[$registry_key]['version'], '>' ) ) {
            $GLOBALS[$registry_key] = [
                    'version' => $this_version,
                    'path'    => $this_path
            ];
        }
    }, 1 );

    /**
     * Step 2: Lazy Loading Registration (Priority 2)
     */
    add_action( 'plugins_loaded', function () use ( $registry_key, $this_path, $prefix ) {
        if ( empty( $GLOBALS[$registry_key] ) || $GLOBALS[$registry_key]['path'] !== $this_path ) {
            return;
        }

        $base_dir = $this_path . '/src/';

        spl_autoload_register( function ( $class ) use ( $prefix, $base_dir ) {
            if ( strpos( $class, $prefix ) !== 0 ) {
                return;
            }

            $relative_class = substr( $class, strlen( $prefix ) );
            $file = $base_dir . str_replace( '\\', '/', $relative_class ) . '.php';

            if ( file_exists( $file ) ) {
                require $file;
            }
        }, true, true );

    }, 2 );

    /**
     * Step 3: Package Initialization (Configurable Hook/Priority)
     */
    if ( ! empty( $loader_class ) ) {
        add_action( $loader_hook, function () use ( $registry_key, $this_path, $loader_class, $winning_constants ) {
            // Bail if we didn't win the version negotiation
            if ( empty( $GLOBALS[$registry_key] ) || $GLOBALS[$registry_key]['path'] !== $this_path ) {
                return;
            }

            // Define package constants dynamically for the winning version
            foreach ( $winning_constants as $name => $value ) {
                if ( ! defined( $name ) ) {
                    define( $name, $value );
                }
            }

            // class_exists() triggers the autoloader registered in Step 2
            if ( class_exists( $loader_class ) ) {
                // Boot the class with zero arguments required
                new $loader_class();
            }
        }, $loader_priority );
    }

} )();