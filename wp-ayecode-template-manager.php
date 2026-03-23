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
