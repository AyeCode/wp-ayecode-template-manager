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
			'name'               => _x( 'Templates', 'post type general name', 'wp-ayecode-template-manager' ),
			'singular_name'      => _x( 'Template', 'post type singular name', 'wp-ayecode-template-manager' ),
			'menu_name'          => _x( 'Templates', 'admin menu', 'wp-ayecode-template-manager' ),
			'name_admin_bar'     => _x( 'Template', 'add new on admin bar', 'wp-ayecode-template-manager' ),
			'add_new'            => _x( 'Add New', 'template', 'wp-ayecode-template-manager' ),
			'add_new_item'       => __( 'Add New Template', 'wp-ayecode-template-manager' ),
			'new_item'           => __( 'New Template', 'wp-ayecode-template-manager' ),
			'edit_item'          => __( 'Edit Template', 'wp-ayecode-template-manager' ),
			'view_item'          => __( 'View Template', 'wp-ayecode-template-manager' ),
			'all_items'          => __( 'All Templates', 'wp-ayecode-template-manager' ),
			'search_items'       => __( 'Search Templates', 'wp-ayecode-template-manager' ),
			'parent_item_colon'  => __( 'Parent Templates:', 'wp-ayecode-template-manager' ),
			'not_found'          => __( 'No templates found.', 'wp-ayecode-template-manager' ),
			'not_found_in_trash' => __( 'No templates found in Trash.', 'wp-ayecode-template-manager' ),
		);

		$args = array(
			'labels'              => $labels,
			'description'         => __( 'AyeCode template management', 'wp-ayecode-template-manager' ),
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
