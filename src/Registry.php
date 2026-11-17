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
		 *     'group_icon'  => 'fa-solid fa-icon',
		 *     'variants'    => [                       // Optional: enables "Add Template" (per post type overrides)
		 *       'bases'      => [ 'template_key' => 'Single Layout' ],
		 *       'post_types' => [ 'gd_place' => 'Places' ],
		 *     ],
		 *     'items' => [
		 *       'template_key' => [
		 *         'title'       => 'Template Title',
		 *         'description' => 'Template description',
		 *         'post_id'     => 123,               // Page ID for classic themes (0 when missing)
		 *         'fse_slug'    => 'single-listing',  // Block template slug for block themes
		 *         'fse_type'    => 'wp_template',     // 'wp_template' or 'wp_template_part'
		 *         'type'        => 'page',            // 'page' (has a URL) or 'layout' (never visited directly)
		 *         'usage'       => 'Global',          // Short "applies to" label
		 *         'global'      => true,              // Applies to all post types
		 *         'post_type'   => '',                // Post type when the row is a per post type override
		 *         'location'    => 'single',          // 'single' or 'archive': where theme builders can override it
		 *         'parent_key'  => '',                // Key of the global template an override is based on
		 *         'is_override' => false,             // Per post type override rows can be deleted
		 *         'expected'    => true,              // Row shows as Missing rather than disappearing when the page is gone
		 *         'page_key'    => 'details',         // Opaque value handed back to the host plugin in actions
		 *       ]                                    // 'product' is filled in automatically
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

			$product_slug = sanitize_key( $product_slug );

			// Validate product-level data
			$validated[ $product_slug ] = array(
				'group_label' => ! empty( $product_data['group_label'] ) ? $product_data['group_label'] : ucfirst( $product_slug ),
				'group_icon'  => ! empty( $product_data['group_icon'] ) ? $product_data['group_icon'] : 'fa-solid fa-layer-group',
				'variants'    => $this->validate_variants( ! empty( $product_data['variants'] ) ? $product_data['variants'] : array() ),
				'items'       => array(),
			);

			// Validate each template item
			foreach ( $product_data['items'] as $template_key => $template_data ) {
				if ( ! is_array( $template_data ) ) {
					continue;
				}

				$post_id  = isset( $template_data['post_id'] ) ? absint( $template_data['post_id'] ) : 0;
				$fse_slug = ! empty( $template_data['fse_slug'] ) ? sanitize_key( $template_data['fse_slug'] ) : '';
				$expected = ! isset( $template_data['expected'] ) || ! empty( $template_data['expected'] );

				// A row needs something to point at, unless the host says the
				// page should exist (then it is shown as Missing).
				if ( ! $post_id && ! $fse_slug && ! $expected ) {
					continue;
				}

				$location = ! empty( $template_data['location'] ) ? sanitize_key( $template_data['location'] ) : '';
				if ( ! in_array( $location, array( 'single', 'archive' ), true ) ) {
					$location = '';
				}

				$validated[ $product_slug ]['items'][ $template_key ] = array(
					'title'       => ! empty( $template_data['title'] ) ? $template_data['title'] : ucwords( str_replace( array( '_', '-' ), ' ', $template_key ) ),
					'description' => ! empty( $template_data['description'] ) ? $template_data['description'] : '',
					'post_id'     => $post_id,
					'fse_slug'    => $fse_slug,
					'fse_type'    => ! empty( $template_data['fse_type'] ) && 'wp_template_part' === $template_data['fse_type'] ? 'wp_template_part' : 'wp_template',
					'type'        => ! empty( $template_data['type'] ) && 'page' === $template_data['type'] ? 'page' : 'layout',
					'usage'       => ! empty( $template_data['usage'] ) ? wp_kses_post( $template_data['usage'] ) : '',
					'icon_class'  => ! empty( $template_data['icon_class'] ) ? esc_attr( $template_data['icon_class'] ) : '',
					'global'      => ! empty( $template_data['global'] ),
					'post_type'   => ! empty( $template_data['post_type'] ) ? sanitize_key( $template_data['post_type'] ) : '',
					'location'    => $location,
					'parent_key'  => ! empty( $template_data['parent_key'] ) ? (string) $template_data['parent_key'] : '',
					'is_override' => ! empty( $template_data['is_override'] ),
					'expected'    => $expected,
					'page_key'    => ! empty( $template_data['page_key'] ) ? (string) $template_data['page_key'] : '',
					'product'     => $product_slug,
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
	 * Validate the "variants" config that powers the Add Template modal.
	 *
	 * @param array $variants Raw variants array.
	 * @return array
	 */
	private function validate_variants( $variants ) {
		$clean = array(
			'bases'      => array(),
			'post_types' => array(),
		);

		if ( ! is_array( $variants ) ) {
			return $clean;
		}

		foreach ( array( 'bases', 'post_types' ) as $list ) {
			if ( empty( $variants[ $list ] ) || ! is_array( $variants[ $list ] ) ) {
				continue;
			}

			foreach ( $variants[ $list ] as $key => $label ) {
				$clean[ $list ][ (string) $key ] = sanitize_text_field( (string) $label );
			}
		}

		return $clean;
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
	 * Find a registered template by the page post it points at.
	 *
	 * @param int $post_id Page ID.
	 * @return array|false Array with 'product', 'key' and 'data', or false.
	 */
	public function find_by_post_id( $post_id ) {
		$post_id = absint( $post_id );

		if ( ! $post_id ) {
			return false;
		}

		foreach ( $this->get_registered_templates() as $product_slug => $product_data ) {
			foreach ( $product_data['items'] as $template_key => $template_data ) {
				if ( $template_data['post_id'] === $post_id ) {
					return array(
						'product' => $product_slug,
						'key'     => $template_key,
						'data'    => $template_data,
					);
				}
			}
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
		Fse::clear_cache();
		Overrides\Manager::clear_cache();
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
