<?php
/**
 * Plugin Name: WP AyeCode Template Manager
 * Plugin URI: https://ayecode.io/
 * Description: Centralized template management hub for the AyeCode ecosystem.
 * Version: 1.0.0
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

// Define plugin constants.
if ( ! defined( 'AYECODE_TEMPLATE_MANAGER_VERSION' ) ) {
	define( 'AYECODE_TEMPLATE_MANAGER_VERSION', '1.0.0' );
}
if ( ! defined( 'AYECODE_TEMPLATE_MANAGER_PLUGIN_FILE' ) ) {
	define( 'AYECODE_TEMPLATE_MANAGER_PLUGIN_FILE', __FILE__ );
}
if ( ! defined( 'AYECODE_TEMPLATE_MANAGER_PLUGIN_DIR' ) ) {
	define( 'AYECODE_TEMPLATE_MANAGER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
}

// Load plugin classes.
require_once AYECODE_TEMPLATE_MANAGER_PLUGIN_DIR . 'src/Environment.php';
require_once AYECODE_TEMPLATE_MANAGER_PLUGIN_DIR . 'src/Helpers.php';
require_once AYECODE_TEMPLATE_MANAGER_PLUGIN_DIR . 'src/Registry.php';
require_once AYECODE_TEMPLATE_MANAGER_PLUGIN_DIR . 'src/TemplateManager.php';
require_once AYECODE_TEMPLATE_MANAGER_PLUGIN_DIR . 'src/PostTypes/TemplateCPT.php';
require_once AYECODE_TEMPLATE_MANAGER_PLUGIN_DIR . 'src/Settings.php';
require_once AYECODE_TEMPLATE_MANAGER_PLUGIN_DIR . 'src/Loader.php';

/**
 * Initialize the plugin.
 *
 * Instantiate the main Loader class on the plugins_loaded hook to ensure
 * WordPress core and other plugins are fully loaded.
 */
function ayecode_template_manager_init() {
	// Check if the AyeCode Settings Framework is available.
	if ( ! class_exists( '\AyeCode\SettingsFramework\Settings_Framework' ) ) {
		add_action( 'admin_notices', 'ayecode_template_manager_missing_framework_notice' );
		return;
	}

	// Initialize the plugin loader.
	\AyeCode\Templates\Loader::instance();
}
add_action( 'plugins_loaded', 'ayecode_template_manager_init' );

/**
 * Display admin notice if the Settings Framework is missing.
 */
function ayecode_template_manager_missing_framework_notice() {
	?>
	<div class="notice notice-error">
		<p>
			<?php
			echo wp_kses_post(
				sprintf(
					/* translators: %s: plugin name */
					__( '<strong>%s</strong> requires the AyeCode Settings Framework to be installed and activated.', 'wp-ayecode-template-manager' ),
					'WP AyeCode Template Manager'
				)
			);
			?>
		</p>
	</div>
	<?php
}

// ============================================================================
// Global Helper Functions
// ============================================================================

/**
 * Get the current environment configuration.
 *
 * Returns detected theme type and active page builders.
 *
 * @param bool $force_refresh Force refresh the environment cache.
 * @return array Environment array with detected features.
 */
function ayecode_get_environment( $force_refresh = false ) {
	return \AyeCode\Templates\Helpers::get_environment( $force_refresh );
}

/**
 * Create a template (page or layout CPT).
 *
 * @param string $template_key Unique key for the template.
 * @param array  $config       Template configuration array.
 * @return int|false Post ID on success, false on failure.
 */
function ayecode_create_template( $template_key, $config ) {
	return \AyeCode\Templates\Helpers::create_template( $template_key, $config );
}

/**
 * Get a template post ID by its key.
 *
 * @param string $template_key The template key.
 * @return int|false Post ID if found, false otherwise.
 */
function ayecode_get_template_id( $template_key ) {
	return \AyeCode\Templates\Helpers::get_template_id_by_key( $template_key );
}

/**
 * Get the edit URL for a template.
 *
 * @param int $post_id The post ID.
 * @return string The edit URL.
 */
function ayecode_get_template_edit_url( $post_id ) {
	return \AyeCode\Templates\Helpers::get_template_edit_url( $post_id );
}

/**
 * Restore a template to its default state.
 *
 * @param int $post_id The post ID.
 * @return bool True on success, false on failure.
 */
function ayecode_restore_template( $post_id ) {
	return \AyeCode\Templates\Helpers::restore_template( $post_id );
}

/**
 * Delete a template by key.
 *
 * @param string $template_key The template key.
 * @return bool True on success, false on failure.
 */
function ayecode_delete_template( $template_key ) {
	return \AyeCode\Templates\Helpers::delete_template( $template_key );
}
