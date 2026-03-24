<?php
/**
 * Template Registry Class
 *
 * Manages the registration and retrieval of templates from plugins.
 *
 * @package AyeCode\Templates
 */

namespace AyeCode\Templates;

/**
 * Registry class.
 *
 * Central registry for all plugin-registered templates.
 */
class Registry {

	/**
	 * Single instance of the class.
	 *
	 * @var Registry
	 */
	private static $instance = null;

	/**
	 * Cached registered templates.
	 *
	 * @var array|null
	 */
	private $templates = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return Registry
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		// Private constructor for singleton
	}

	/**
	 * Get all registered templates.
	 *
	 * Calls the 'ayecode_register_templates' filter and caches the result.
	 *
	 * @param bool $force_refresh Force refresh the cache.
	 * @return array Registered templates array.
	 */
	public function get_registered_templates( $force_refresh = false ) {
		if ( null === $this->templates || $force_refresh ) {
			$this->templates = $this->fetch_templates();
		}

		return $this->templates;
	}

	/**
	 * Fetch templates from plugins via filter.
	 *
	 * @return array Registered templates.
	 */
	private function fetch_templates() {
		$environment = Environment::get();

		/**
		 * Filter to register templates from plugins.
		 *
		 * Plugins should return their template definitions in this format:
		 *
		 * [
		 *   'product_slug' => [
		 *     'group_label' => 'Product Name',
		 *     'group_icon'  => 'dashicons-icon',
		 *     'items' => [
		 *       'template_key' => [
		 *         'title'       => 'Template Title',
		 *         'description' => 'Template description',
		 *         'post_id'     => 123, // REQUIRED: The actual post ID
		 *         'type'        => 'page', // or 'layout'
		 *         'builder'     => 'elementor', // Which builder this template uses
		 *         'conditions'  => 'Singular: Listing', // Optional: When this template applies
		 *       ]
		 *     ]
		 *   ]
		 * ]
		 *
		 * @param array $templates   Empty array to be populated by plugins.
		 * @param array $environment The current environment configuration.
		 */
		$templates = apply_filters( 'ayecode_register_templates', array(), $environment );

		// Validate and sanitize
		$templates = $this->validate_templates( $templates );

		return $templates;
	}

	/**
	 * Validate the registered templates structure.
	 *
	 * @param array $templates The templates array to validate.
	 * @return array Validated templates array.
	 */
	private function validate_templates( $templates ) {
		if ( ! is_array( $templates ) ) {
			return array();
		}

		$validated = array();

		foreach ( $templates as $product_slug => $product_data ) {
			if ( ! is_array( $product_data ) || empty( $product_data['items'] ) ) {
				continue;
			}

			// Validate product-level data
			$validated[ $product_slug ] = array(
				'group_label' => ! empty( $product_data['group_label'] ) ? $product_data['group_label'] : ucfirst( $product_slug ),
				'group_icon'  => ! empty( $product_data['group_icon'] ) ? $product_data['group_icon'] : 'dashicons-admin-generic',
				'items'       => array(),
			);

			// Validate each template item
			foreach ( $product_data['items'] as $template_key => $template_data ) {
				if ( ! is_array( $template_data ) || empty( $template_data['post_id'] ) ) {
					continue;
				}

				$validated[ $product_slug ]['items'][ $template_key ] = array(
					'title'       => ! empty( $template_data['title'] ) ? $template_data['title'] : ucwords( str_replace( '_', ' ', $template_key ) ),
					'description' => ! empty( $template_data['description'] ) ? $template_data['description'] : '',
					'post_id'     => absint( $template_data['post_id'] ),
					'type'        => ! empty( $template_data['type'] ) ? $template_data['type'] : 'layout',
					'builder'     => ! empty( $template_data['builder'] ) ? sanitize_key( $template_data['builder'] ) : 'gutenberg',
					'conditions'  => ! empty( $template_data['conditions'] ) ? wp_kses_post( $template_data['conditions'] ) : '',
				);
			}

			// Remove products with no valid items
			if ( empty( $validated[ $product_slug ]['items'] ) ) {
				unset( $validated[ $product_slug ] );
			}
		}

		return $validated;
	}

	/**
	 * Get templates for a specific product.
	 *
	 * @param string $product_slug The product slug.
	 * @return array|false Product templates or false if not found.
	 */
	public function get_product_templates( $product_slug ) {
		$templates = $this->get_registered_templates();

		if ( isset( $templates[ $product_slug ] ) ) {
			return $templates[ $product_slug ];
		}

		return false;
	}

	/**
	 * Get a specific template by product and key.
	 *
	 * @param string $product_slug  The product slug.
	 * @param string $template_key  The template key.
	 * @return array|false Template data or false if not found.
	 */
	public function get_template( $product_slug, $template_key ) {
		$product = $this->get_product_templates( $product_slug );

		if ( $product && isset( $product['items'][ $template_key ] ) ) {
			return $product['items'][ $template_key ];
		}

		return false;
	}

	/**
	 * Get all products that have registered templates.
	 *
	 * @return array Array of product slugs.
	 */
	public function get_products() {
		$templates = $this->get_registered_templates();
		return array_keys( $templates );
	}

	/**
	 * Clear the template cache.
	 *
	 * Forces templates to be re-fetched on next request.
	 */
	public function clear_cache() {
		$this->templates = null;
	}

	/**
	 * Check if a user can edit a specific template.
	 *
	 * @param int   $user_id       The user ID.
	 * @param string $product_slug The product slug.
	 * @param string $template_key The template key.
	 * @return bool True if user can edit, false otherwise.
	 */
	public function user_can_edit_template( $user_id, $product_slug, $template_key ) {
		$template = $this->get_template( $product_slug, $template_key );

		if ( ! $template ) {
			return false;
		}

		$capability = ! empty( $template['capabilities'] ) ? $template['capabilities'] : 'edit_pages';

		return user_can( $user_id, $capability );
	}
}
