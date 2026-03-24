<?php
/**
 * Environment Detection Class
 *
 * Detects the current WordPress environment including block theme status
 * and active page builders.
 *
 * @package AyeCode\Templates
 */

namespace AyeCode\Templates;

/**
 * Environment class.
 *
 * Detects and reports on the WordPress environment for template management.
 */
class Environment {

	/**
	 * Cached environment data.
	 *
	 * @var array|null
	 */
	private static $cache = null;

	/**
	 * Get the current environment configuration.
	 *
	 * Returns an array of detected features including theme type and active builders.
	 *
	 * @param bool $force_refresh Force refresh the cache.
	 * @return array Environment configuration array.
	 */
	public static function get( $force_refresh = false ) {
		if ( null === self::$cache || $force_refresh ) {
			self::$cache = self::detect();
		}

		return self::$cache;
	}

	/**
	 * Detect the current environment.
	 *
	 * @return array Environment configuration.
	 */
	private static function detect() {
		$environment = array(
			// Theme detection
			'block_theme'   => self::is_block_theme(),
			'classic_theme' => ! self::is_block_theme(),

			// Page Builders
			'gutenberg'         => self::has_gutenberg(),
			'elementor'         => self::has_elementor(),
			'elementor_pro'     => self::has_elementor_pro(),
			'wpbakery'          => self::has_wpbakery(),
			'divi'              => self::has_divi(),
			'beaver_builder'    => self::has_beaver_builder(),
			'brizy'             => self::has_brizy(),
			'oxygen'            => self::has_oxygen(),
			'thrive_architect'  => self::has_thrive_architect(),
			'breakdance'        => self::has_breakdance(),
			'siteorigin'        => self::has_siteorigin(),
			'bricks'            => self::has_bricks(),
		);

		/**
		 * Filter the detected environment.
		 *
		 * Allows plugins to add custom environment checks.
		 *
		 * @param array $environment The detected environment array.
		 */
		return apply_filters( 'ayecode_template_environment', $environment );
	}

	/**
	 * Check if the current theme is a block theme (FSE).
	 *
	 * @return bool True if block theme, false otherwise.
	 */
	private static function is_block_theme() {
		return function_exists( 'wp_is_block_theme' ) && wp_is_block_theme();
	}

	/**
	 * Check if Gutenberg block editor is available.
	 *
	 * @return bool True if Gutenberg is available.
	 */
	private static function has_gutenberg() {
		// Gutenberg is available by default in WordPress 5.0+
		global $wp_version;
		return version_compare( $wp_version, '5.0', '>=' );
	}

	/**
	 * Check if Elementor is active.
	 *
	 * @return bool True if Elementor is active.
	 */
	private static function has_elementor() {
		return defined( 'ELEMENTOR_VERSION' ) || class_exists( 'Elementor\Plugin' );
	}

	/**
	 * Check if Elementor Pro is active.
	 *
	 * @return bool True if Elementor Pro is active.
	 */
	private static function has_elementor_pro() {
		return defined( 'ELEMENTOR_PRO_VERSION' );
	}

	/**
	 * Check if WPBakery Page Builder is active.
	 *
	 * @return bool True if WPBakery is active.
	 */
	private static function has_wpbakery() {
		return defined( 'WPB_VC_VERSION' ) || class_exists( 'Vc_Manager' );
	}

	/**
	 * Check if Divi Builder is active.
	 *
	 * @return bool True if Divi is active.
	 */
	private static function has_divi() {
		return function_exists( 'et_setup_theme' ) || defined( 'ET_BUILDER_VERSION' ) || class_exists( 'ET_Builder_Plugin' );
	}

	/**
	 * Check if Beaver Builder is active.
	 *
	 * @return bool True if Beaver Builder is active.
	 */
	private static function has_beaver_builder() {
		return class_exists( 'FLBuilder' ) || class_exists( 'FLBuilderModel' );
	}

	/**
	 * Check if Brizy is active.
	 *
	 * @return bool True if Brizy is active.
	 */
	private static function has_brizy() {
		return defined( 'BRIZY_VERSION' ) || class_exists( 'Brizy_Editor' );
	}

	/**
	 * Check if Oxygen Builder is active.
	 *
	 * @return bool True if Oxygen is active.
	 */
	private static function has_oxygen() {
		return defined( 'CT_VERSION' ) || class_exists( 'CT_Component' );
	}

	/**
	 * Check if Thrive Architect is active.
	 *
	 * @return bool True if Thrive Architect is active.
	 */
	private static function has_thrive_architect() {
		return defined( 'TVE_VERSION' ) || function_exists( 'tve_init' );
	}

	/**
	 * Check if Breakdance is active.
	 *
	 * @return bool True if Breakdance is active.
	 */
	private static function has_breakdance() {
		return defined( 'BREAKDANCE_VERSION' ) || function_exists( 'breakdance_init' );
	}

	/**
	 * Check if SiteOrigin Page Builder is active.
	 *
	 * @return bool True if SiteOrigin is active.
	 */
	private static function has_siteorigin() {
		return class_exists( 'SiteOrigin_Panels' ) || defined( 'SITEORIGIN_PANELS_VERSION' );
	}

	/**
	 * Check if Bricks Builder is active.
	 *
	 * @return bool True if Bricks is active.
	 */
	private static function has_bricks() {
		return defined( 'BRICKS_VERSION' ) || class_exists( 'Bricks\Theme' );
	}

	/**
	 * Get a list of active page builders.
	 *
	 * @return array Array of active builder names.
	 */
	public static function get_active_builders() {
		$environment = self::get();
		$builders    = array();

		$builder_keys = array(
			'gutenberg',
			'elementor',
			'elementor_pro',
			'wpbakery',
			'divi',
			'beaver_builder',
			'brizy',
			'oxygen',
			'thrive_architect',
			'breakdance',
			'siteorigin',
			'bricks',
		);

		foreach ( $builder_keys as $key ) {
			if ( ! empty( $environment[ $key ] ) ) {
				$builders[] = $key;
			}
		}

		return $builders;
	}

	/**
	 * Check if a specific builder is active.
	 *
	 * @param string $builder Builder key (e.g., 'elementor', 'divi').
	 * @return bool True if the builder is active.
	 */
	public static function has_builder( $builder ) {
		$environment = self::get();
		return ! empty( $environment[ $builder ] );
	}
}
