<?php
/**
 * Main loader class for the WP AyeCode Template Manager plugin.
 *
 * Orchestrates the initialization of all plugin components including CPT registration
 * and settings framework integration.
 *
 * @package AyeCode\Templates
 */

namespace AyeCode\Templates;

/**
 * Loader class.
 *
 * Main plugin initialization and component orchestration.
 */
class Loader {

	/**
	 * Single instance of the class.
	 *
	 * @var Loader
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Loader
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 *
	 * Initialize hooks and load plugin components.
	 */
	private function __construct() {
		$this->init_hooks();
	}

	/**
	 * Initialize plugin hooks.
	 *
	 * Hook into WordPress actions and filters to set up plugin functionality.
	 */
	private function init_hooks() {
		// Initialize custom post type.
		PostTypes\TemplateCPT::instance();

		// Initialize template manager (registers post statuses, AJAX handlers).
		TemplateManager::instance();

		// Initialize settings framework.
		if ( is_admin() ) {
			Settings::instance();
		}

		// Load text domain for translations.
		add_action( 'init', array( $this, 'load_textdomain' ) );
	}

	/**
	 * Load plugin text domain for translations.
	 */
	public function load_textdomain() {
		load_plugin_textdomain(
			'wp-ayecode-template-manager',
			false,
			dirname( plugin_basename( AYECODE_TEMPLATE_MANAGER_PLUGIN_FILE ) ) . '/languages'
		);
	}
}
