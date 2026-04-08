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
	}

	/**
	 * Register custom post statuses for templates.
	 */
	public function register_post_statuses() {
		// Customized status
		register_post_status(
			'customized',
			array(
				'label'                     => _x( 'Customized', 'post status', 'ayecode-connect' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of customized templates */
				'label_count'               => _n_noop( 'Customized <span class="count">(%s)</span>', 'Customized <span class="count">(%s)</span>', 'ayecode-connect' ),
			)
		);

		// Default status
		register_post_status(
			'default',
			array(
				'label'                     => _x( 'Default', 'post status', 'ayecode-connect' ),
				'public'                    => true,
				'exclude_from_search'       => false,
				'show_in_admin_all_list'    => true,
				'show_in_admin_status_list' => true,
				/* translators: %s: number of default templates */
				'label_count'               => _n_noop( 'Default <span class="count">(%s)</span>', 'Default <span class="count">(%s)</span>', 'ayecode-connect' ),
			)
		);
	}

	/**
	 * AJAX handler to restore a template to default.
	 */
	public function ajax_restore_template() {
		check_ajax_referer( 'ayecode-template-manager', 'nonce' );

		if ( ! current_user_can( 'edit_pages' ) ) {
			wp_send_json_error( array( 'message' => __( 'Insufficient permissions.', 'ayecode-connect' ) ) );
		}

		$post_id = ! empty( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;

		if ( ! $post_id ) {
			wp_send_json_error( array( 'message' => __( 'Invalid template ID.', 'ayecode-connect' ) ) );
		}

		$result = Helpers::restore_template( $post_id );

		if ( $result ) {
			wp_send_json_success( array( 'message' => __( 'Template restored successfully.', 'ayecode-connect' ) ) );
		} else {
			wp_send_json_error( array( 'message' => __( 'Failed to restore template.', 'ayecode-connect' ) ) );
		}
	}

	/**
	 * Get template status counts for the current view.
	 *
	 * @param string $product_slug Optional. Filter by product.
	 * @return array Status counts.
	 */
	public function get_status_counts( $product_slug = '' ) {
		$args = array(
			'post_type'      => 'page',
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
                $name_html =  $template_data['icon_class'] ? '<i class="' . esc_attr( $template_data['icon_class'] ) . '"></i> ' : '';
				$name_html .= '<strong>' . esc_html( $template_data['title'] ) . '</strong>';
				if ( ! empty( $template_data['description'] ) ) {
					$name_html .= '<br><small class="text-muted">' . esc_html( $template_data['description'] ) . '</small>';
				}

                // maybe add the page title and slug to the name_html
                if( $post_id ){
                    $page = get_post( $post_id );
                    if( $page ){
                        // if $template_data['type'] === 'page then make the slug a link to the frontend page permalink (if it exists)
                        $permalink = $template_data['type'] === 'page' && $page->post_status === 'publish' ? get_permalink( $post_id ) : '';
                        $slug_text = $permalink ? '<a href="'.esc_url($permalink).'" target="_blank">' . esc_attr( $page->post_name ) .'</a>'  : esc_attr( $page->post_name ) ;
                        $name_html .=  '<br><small class="text-muted">' . esc_html( $page->post_title ) . ' (' . $slug_text. ')</small>';

                    }
                }

				// Get usage from template data (if provided by plugin)
                $usage = ! empty( $template_data['usage'] ) ? $template_data['usage'] : '';
                if( $usage && ! empty( $template_data['global'] )){
                    $usage = '<span class="badge rounded-pill text-bg-secondary">' . esc_attr( $usage ) . '</span>';
                }elseif(! empty( $usage )){
                    $usage = '<span class="badge rounded-pill text-bg-primary">' . esc_attr( $usage ) . '</span>';
                }


                // title
                $title = $template_data['title'] ? esc_attr( $template_data['title'] ) : '';
                if( $template_data['icon_class'] ){
                    $title = '<i class="' . esc_attr( $template_data['icon_class'] ) . '"></i> ' . $title;
                }


				// Get environment for Router
				$environment = Environment::get();

				// Detect actual builder being used
				$detected_builder = Router::get_detected_builder_name( $post_id, $environment );

				// Get edit URL using Router
				$edit_url = Router::get_edit_url( $post_id, $template_data, $environment );

				$items[] = array(
					'id'           => absint( $post_id ),
                    'name'         => esc_attr( $template_data['title'] ),
                    'type'         => esc_attr( $template_data['type'] ),
                    'name_desc'    => wp_kses_post( $name_html ),
					'usage'   => $usage,
					'builder'      => $detected_builder,
					'product'      => ucfirst( $product_slug ),
					'status'       => $post_status,
					'edit_url'     => $edit_url,
					'template_key' => $template_key,
                    'fse_slug'     => $template_data['fse_slug'],
                    'page_slug'    => $post_id ? $slug = get_post_field( 'post_name', $post_id ) : '',
				);
			}
		}

		return array(
			'items'  => $items,
			'counts' => $counts,
		);
	}
}
