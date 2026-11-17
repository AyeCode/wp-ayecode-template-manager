<?php
/**
 * Theme builder override provider contract.
 *
 * @package AyeCode\Templates
 */

namespace AyeCode\Templates\Overrides;

/**
 * A provider knows how one theme builder stores its templates and display
 * conditions, and can say whether a template of that builder takes over
 * rendering for a given post type and location.
 */
interface Provider {

	/**
	 * Provider slug, e.g. 'elementor_pro'.
	 *
	 * @return string
	 */
	public function get_slug();

	/**
	 * Whether the builder is active in this environment.
	 *
	 * @param array $environment Environment array.
	 * @return bool
	 */
	public function is_available( $environment );

	/**
	 * Find a builder template that overrides the given location for a post type.
	 *
	 * @param string $post_type Post type slug, e.g. 'gd_place'.
	 * @param string $location  'single' or 'archive'.
	 * @return array|null Array with 'builder', 'id', 'title', 'label', 'edit_url', or null.
	 */
	public function find( $post_type, $location );
}
