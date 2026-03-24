<?php
/**
 * Template Manager Class
 *
 * Handles AJAX operations and template management functionality.
 *
 * @package AyeCode\Templates
 */

namespace AyeCode\Templates;

/**
 * TemplateManager class.
 *
 * Manages template CRUD operations and AJAX handlers.
 */
class TemplateManager {

	/**
	 * Single instance of the class.
	 *
	 * @var TemplateManager
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return TemplateManager
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
		$this->init_hooks();
	}

	/**
	 * Initialize hooks.
	 */
	private function init_hooks() {
		// Register custom post statuses
		add_action( 'init', array( $this, 'register_post_statuses' ) );

		// AJAX handlers
		add_action( 'wp_ajax_ayecode_restore_template', array( $this, 'ajax_restore_template' ) );
		add_action( 'wp_ajax_ayecode_get_template_info', array( $this, 'ajax_get_template_info' ) );
	}

	/**
	 * Register custom post statuses for templates.
	 */
	public function register_post_statuses() {
		// Customized status
		register_post_status(
			'customized',
			array(
				'label'                     => _x( 'Customized', 'post status', 'wp-ayecode-template-manager' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of customized templates */
				'label_count'               => _n_noop( 'Customized <span class="count">(%s)</span>', 'Customized <span class="count">(%s)</span>', 'wp-ayecode-template-manager' ),
			)
		);

		// Default status
		register_post_status(
			'default',
			array(
				'label'                     => _x( 'Default', 'post status', 'wp-ayecode-template-manager' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of default templates */
				'label_count'               => _n_noop( 'Default <span class="count">(%s)</span>', 'Default <span class="count">(%s)</span>', 'wp-ayecode-template-manager' ),
			)
		);
	}

	/**
	 * AJAX handler to restore a template to default.
	 */
	public function ajax_restore_template() {
		check_ajax_referer( 'ayecode-template-manager', 'nonce' );

		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-ayecode-template-manager' ) ) );
		}

		$post_id = ! empty( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid template ID.', 'wp-ayecode-template-manager' ) ) );
		}

		$result = Helpers::restore_template( $post_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Template restored successfully.', 'wp-ayecode-template-manager' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to restore template.', 'wp-ayecode-template-manager' ) ) );
		}
	}

	/**
	 * AJAX handler to get template information.
	 */
	public function ajax_get_template_info() {
		check_ajax_referer( 'ayecode-template-manager', 'nonce' );

		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'wp-ayecode-template-manager' ) ) );
		}

		$post_id = ! empty( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid template ID.', 'wp-ayecode-template-manager' ) ) );
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			wp_send_json_error( array( 'message' => __( 'Template not found.', 'wp-ayecode-template-manager' ) ) );
		}

		$info = array(
			'id'           => $post_id,
			'title'        => $post->post_title,
			'status'       => $post->post_status,
			'type'         => get_post_meta( $post_id, '_ayecode_template_type', true ),
			'builder'      => get_post_meta( $post_id, '_ayecode_template_builder', true ),
			'product'      => get_post_meta( $post_id, '_ayecode_template_product', true ),
			'template_key' => get_post_meta( $post_id, '_ayecode_template_key', true ),
			'edit_url'     => Helpers::get_template_edit_url( $post_id ),
		);

		wp_send_json_success( $info );
	}

	/**
	 * Get template status counts for the current view.
	 *
	 * @param string $product_slug Optional. Filter by product.
	 * @return array Status counts.
	 */
	public function get_status_counts( $product_slug = '' ) {
		$args = array(
			'post_type'      => array( 'page', PostTypes\TemplateCPT::POST_TYPE ),
			'post_status'    => 'any',
			'posts_per_page' => -1,
			'fields'         => 'ids',
		);

		if ( ! empty( $product_slug ) ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_ayecode_template_product',
					'value' => $product_slug,
				),
			);
		}

		$query = new \WP_Query( $args );

		$counts = array(
			'all'        => 0,
			'publish'    => 0,
			'draft'      => 0,
			'customized' => 0,
			'default'    => 0,
		);

		foreach ( $query->posts as $post_id ) {
			$status = get_post_status( $post_id );

			$counts['all']++;

			if ( isset( $counts[ $status ] ) ) {
				$counts[ $status ]++;
			}
		}

		return $counts;
	}

	/**
	 * Get templates formatted for list table display.
	 *
	 * Uses Registry as the source of truth - only displays templates that are registered.
	 *
	 * @param array $args Query arguments.
	 * @return array Templates data with items and counts.
	 */
	public function get_templates_for_display( $args = array() ) {
		$defaults = array(
			'status'  => 'all',
			'product' => '',
		);

		$args = wp_parse_args( $args, $defaults );

		// Get registered templates from Registry (source of truth)
		$registry             = Registry::instance();
		$registered_templates = $registry->get_registered_templates();

		$items  = array();
		$counts = array(
			'all'        => 0,
			'publish'    => 0,
			'draft'      => 0,
			'customized' => 0,
			'default'    => 0,
		);

		// Loop through registered templates
		foreach ( $registered_templates as $product_slug => $product_data ) {
			// Filter by product if specified
			if ( ! empty( $args['product'] ) && $args['product'] !== $product_slug ) {
				continue;
			}

			foreach ( $product_data['items'] as $template_key => $template_data ) {
				$post_id = $template_data['post_id'];

				// Only get post status, nothing else
				$post_status = get_post_status( $post_id );

				// Skip if post doesn't exist
				if ( ! $post_status ) {
					continue;
				}

				// Count all statuses
				$counts['all']++;
				if ( isset( $counts[ $post_status ] ) ) {
					$counts[ $post_status ]++;
				}

				// Filter by status
				if ( 'all' !== $args['status'] && $post_status !== $args['status'] ) {
					continue;
				}

				// Build name with description below
				$name_html = '<strong>' . esc_html( $template_data['title'] ) . '</strong>';
				if ( ! empty( $template_data['description'] ) ) {
					$name_html .= '<br><small class="text-muted">' . esc_html( $template_data['description'] ) . '</small>';
				}

				// Get conditions from template data (if provided by plugin)
				$conditions = ! empty( $template_data['conditions'] ) ? $template_data['conditions'] : '';

				$items[] = array(
					'id'           => $post_id,
					'name'         => $name_html,
					'conditions'   => $conditions,
					'builder'      => ucfirst( $template_data['builder'] ),
					'product'      => ucfirst( $product_slug ),
					'status'       => $post_status,
					'edit_url'     => Helpers::get_template_edit_url( $post_id ),
					'template_key' => $template_key,
				);
			}
		}

		return array(
			'items'  => $items,
			'counts' => $counts,
		);
	}
}
