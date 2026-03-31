<?php
/**
 * Template Custom Post Type registration.
 *
 * Registers a hidden custom post type for managing templates across the AyeCode ecosystem.
 * This CPT is not visible in the WordPress admin UI as it is managed entirely through
 * the custom Settings Framework interface.
 *
 * @package AyeCode\Templates\PostTypes
 */

namespace AyeCode\Templates\PostTypes;

/**
 * TemplateCPT class.
 *
 * Handles registration and configuration of the ayecode_template custom post type.
 */
class TemplateCPT {

	/**
	 * Single instance of the class.
	 *
	 * @var TemplateCPT
	 */
	private static $instance = null;

	/**
	 * Post type slug.
	 *
	 * @var string
	 */
	const POST_TYPE = 'ayecode_template';

	/**
	 * Get the singleton instance.
	 *
	 * @return TemplateCPT
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
	 * Hook into WordPress init to register the custom post type.
	 */
	private function __construct() {
		add_action( 'init', array( $this, 'register_post_type' ) );
	}

	/**
	 * Register the custom post type.
	 *
	 * Registers a hidden CPT that supports title, editor, thumbnail, and custom fields.
	 * The CPT is completely hidden from the WordPress admin UI.
	 */
	public function register_post_type() {
		$labels = array(
			'name'               => _x( 'Templates', 'post type general name', 'ayecode-connect' ),
			'singular_name'      => _x( 'Template', 'post type singular name', 'ayecode-connect' ),
			'menu_name'          => _x( 'Templates', 'admin menu', 'ayecode-connect' ),
			'name_admin_bar'     => _x( 'Template', 'add new on admin bar', 'ayecode-connect' ),
			'add_new'            => _x( 'Add New', 'template', 'ayecode-connect' ),
			'add_new_item'       => __( 'Add New Template', 'ayecode-connect' ),
			'new_item'           => __( 'New Template', 'ayecode-connect' ),
			'edit_item'          => __( 'Edit Template', 'ayecode-connect' ),
			'view_item'          => __( 'View Template', 'ayecode-connect' ),
			'all_items'          => __( 'All Templates', 'ayecode-connect' ),
			'search_items'       => __( 'Search Templates', 'ayecode-connect' ),
			'parent_item_colon'  => __( 'Parent Templates:', 'ayecode-connect' ),
			'not_found'          => __( 'No templates found.', 'ayecode-connect' ),
			'not_found_in_trash' => __( 'No templates found in Trash.', 'ayecode-connect' ),
		);

		$args = array(
			'labels'              => $labels,
			'description'         => __( 'AyeCode template management', 'ayecode-connect' ),
			'public'              => false,
			'publicly_queryable'  => false,
			'show_ui'             => false,
			'show_in_menu'        => false,
			'show_in_nav_menus'   => false,
			'show_in_admin_bar'   => false,
			'query_var'           => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'has_archive'         => false,
			'hierarchical'        => false,
			'menu_position'       => null,
			'supports'            => array( 'title', 'editor', 'thumbnail', 'custom-fields' ),
			'show_in_rest'        => false,
		);

		register_post_type( self::POST_TYPE, $args );
	}

	/**
	 * Get all templates.
	 *
	 * Query helper method to retrieve all template posts.
	 *
	 * @param array $args Optional. Additional WP_Query arguments.
	 * @return array Array of template post objects.
	 */
	public static function get_templates( $args = array() ) {
		$defaults = array(
			'post_type'      => self::POST_TYPE,
			'posts_per_page' => -1,
			'post_status'    => 'any',
			'orderby'        => 'title',
			'order'          => 'ASC',
		);

		$args = wp_parse_args( $args, $defaults );

		$query = new \WP_Query( $args );

		return $query->posts;
	}

	/**
	 * Get template by ID.
	 *
	 * @param int $template_id Template post ID.
	 * @return \WP_Post|null Template post object or null if not found.
	 */
	public static function get_template( $template_id ) {
		$post = get_post( $template_id );

		if ( $post && self::POST_TYPE === $post->post_type ) {
			return $post;
		}

		return null;
	}
}
