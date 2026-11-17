<?php
/**
 * AyeCode Template Manager Settings Framework Class
 *
 * Extends AyeCode Settings Framework to provide UI for template management.
 * Implements a list_table interface with left sidebar product filters and top status tabs.
 *
 * @package AyeCode\Templates
 */

namespace AyeCode\Templates;

/**
 * Settings class extending AyeCode Settings Framework.
 */
class Settings extends \AyeCode\SettingsFramework\Settings_Framework {

	/**
	 * Single instance of the class.
	 *
	 * @var Settings
	 */
	private static $instance = null;

	/**
	 * Framework configuration properties.
	 */
	protected $option_name   = 'ayecode-template-manager';
	protected $page_slug     = 'ayecode-templates';
	protected $plugin_name   = '<i class="fa-solid fa-layer-group me-2 text-primary-emphasis fs-4 mb-1"></i> Templates';
	protected $menu_title    = 'Templates';
	protected $page_title    = 'Template Manager';
	protected $menu_icon     = 'dashicons-layout';
	protected $menu_position = null;
	protected $parent_slug   = 'themes.php';

	/**
	 * Get the singleton instance.
	 *
	 * @return Settings
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
	public function __construct() {
		parent::__construct();

		// Hook into AJAX actions for list_table operations.
		add_action( 'asf_execute_tool_' . $this->page_slug, array( $this, 'ajax_actions' ), 10, 2 );
	}

	/**
	 * URL of the Templates screen.
	 *
	 * @return string
	 */
	public static function get_page_url() {
		return admin_url( 'themes.php?page=ayecode-templates' );
	}

	/**
	 * Get the settings configuration.
	 *
	 * @return array Configuration array with sections.
	 */
	public function get_config() {
		$registry             = Registry::instance();
		$registered_templates = $registry->get_registered_templates();
		$environment          = Environment::get();

		$sections = array();

		$sections[] = $this->build_section(
			'all_templates',
			__( 'All Templates', 'ayecode-connect' ),
			'fa-solid fa-layer-group',
			null,
			$environment,
			true
		);

		foreach ( $registered_templates as $product_slug => $product_data ) {
			$sections[] = $this->build_section(
				$product_slug . '_templates',
				$product_data['group_label'],
				$product_data['group_icon'],
				$product_data,
				$environment,
				false
			);
		}

		/**
		 * Filter the template manager sections.
		 *
		 * Allows other plugins to inject additional sections into the left sidebar.
		 *
		 * @param array $sections Array of section configurations.
		 */
		$sections = apply_filters( 'ayecode_template_manager_sections', $sections );

		return array(
			'sections' => $sections,
		);
	}

	/**
	 * Build one list_table section.
	 *
	 * @param string     $id           Section ID.
	 * @param string     $name         Section name.
	 * @param string     $icon         Icon class.
	 * @param array|null $product_data Validated product group, null for the "all" view.
	 * @param array      $environment  Environment array.
	 * @param bool       $show_product Whether to include the Product column.
	 * @return array
	 */
	private function build_section( $id, $name, $icon, $product_data, $environment, $show_product ) {
		$columns = array(
			'name_desc' => array( 'label' => __( 'Template', 'ayecode-connect' ) ),
			'usage'     => array( 'label' => __( 'Applies to', 'ayecode-connect' ) ),
			'source'    => array( 'label' => __( 'Edited with', 'ayecode-connect' ) ),
			'state'     => array( 'label' => __( 'State', 'ayecode-connect' ) ),
		);

		if ( $show_product ) {
			$columns['product'] = array( 'label' => __( 'Product', 'ayecode-connect' ) );
		}

		$variants   = $product_data && ! empty( $product_data['variants'] ) ? $product_data['variants'] : array();
		$can_create = ! empty( $variants['bases'] ) && ! empty( $variants['post_types'] );

		$description = ! empty( $environment['block_theme'] )
			? __( 'Your theme is a block theme, so these templates are edited in the Site Editor. Pages listed here only provide the URL.', 'ayecode-connect' )
			: __( 'Each template is a page whose content is rendered inside your theme. Edit it with whichever builder you use.', 'ayecode-connect' );

		return array(
			'id'           => $id,
			'name'         => $name,
			'icon'         => $icon,
			'type'         => 'list_table',
			'description'  => $description,

			'table_config' => array(
				'singular'         => __( 'Template', 'ayecode-connect' ),
				'plural'           => __( 'Templates', 'ayecode-connect' ),
				'ajax_action_get'  => 'get_templates',
				'ajax_action_bulk' => 'bulk_template_action',
				'can_create'       => $can_create,
				'columns'          => $columns,

				'statuses'         => array(
					'status_key' => 'state_key',
					'labels'     => array(
						'customized' => __( 'Customized', 'ayecode-connect' ),
						'attention'  => __( 'Needs attention', 'ayecode-connect' ),
					),
				),

				'row_actions'      => array(
					'edit'     => array(
						'label'   => __( 'Edit', 'ayecode-connect' ),
						'icon'    => 'fa-solid fa-pen-to-square',
						'link'    => '{{edit_url}}',
						'target'  => '_blank',
						'show_if' => 'item.edit_url',
					),
					'view'     => array(
						'label'   => __( 'View page', 'ayecode-connect' ),
						'icon'    => 'fa-solid fa-eye',
						'link'    => '{{view_url}}',
						'target'  => '_blank',
						'show_if' => 'item.can_view',
					),
					'restore'  => array(
						'label'           => __( 'Restore default', 'ayecode-connect' ),
						'icon'            => 'fa-solid fa-rotate-left',
						'ajax_action'     => 'restore_template',
						'confirm_message' => __( 'Restore this template to its default? Your customizations will be lost.', 'ayecode-connect' ),
						'show_if'         => "item.state_key === 'customized'",
					),
					'recreate' => array(
						'label'           => __( 'Recreate page', 'ayecode-connect' ),
						'icon'            => 'fa-solid fa-wand-magic-sparkles',
						'ajax_action'     => 'recreate_template',
						'confirm_message' => __( 'Create a new page with the default content for this template?', 'ayecode-connect' ),
						'show_if'         => "item.state_key === 'missing' && item.can_recreate",
					),
					'settings' => array(
						'label'   => __( 'Settings', 'ayecode-connect' ),
						'icon'    => 'fa-solid fa-gear',
						'action'  => 'edit',
						'show_if' => "item.can_configure && item.state_key !== 'missing'",
					),
					'delete'   => array(
						'label'   => __( 'Delete', 'ayecode-connect' ),
						'icon'    => 'fa-solid fa-trash-can',
						'action'  => 'delete',
						'show_if' => 'item.is_override',
					),
				),
			),

			'modal_config' => array(
				'title_add'          => __( 'Add Template', 'ayecode-connect' ),
				'title_edit'         => __( 'Template Settings', 'ayecode-connect' ),
				'ajax_action_create' => 'create_override',
				'ajax_action_update' => 'update_template',
				'ajax_action_delete' => 'delete_override',
				'fields'             => $this->get_modal_fields( $variants, $environment ),
			),
		);
	}

	/**
	 * Modal fields shared by the Add Template and Template Settings dialogs.
	 *
	 * The create fields show when the item has no ID, the settings fields when it does.
	 *
	 * @param array $variants    Validated variants for the product.
	 * @param array $environment Environment array.
	 * @return array
	 */
	private function get_modal_fields( $variants, $environment ) {
		$is_create = "[%id%] == null || [%id%] == ''";
		$is_edit   = "[%id%] != null && [%id%] != ''";

		$bases      = array( '' => __( 'Select a template', 'ayecode-connect' ) );
		$post_types = array( '' => __( 'Select a post type', 'ayecode-connect' ) );

		if ( ! empty( $variants['bases'] ) ) {
			$bases += $variants['bases'];
		}
		if ( ! empty( $variants['post_types'] ) ) {
			$post_types += $variants['post_types'];
		}

		$create_help = ! empty( $environment['block_theme'] )
			? __( 'Creates a copy of a template for one post type in the Site Editor. The original keeps serving every other post type.', 'ayecode-connect' )
			: __( 'Creates a copy of a template page for one post type. The original keeps serving every other post type.', 'ayecode-connect' );

		return array(
			array(
				'id'          => 'create_info',
				'type'        => 'alert',
				'alert_type'  => 'info',
				'description' => $create_help,
				'show_if'     => $is_create,
			),
			array(
				'id'               => 'base',
				'type'             => 'select',
				'label'            => __( 'Based on', 'ayecode-connect' ),
				'options'          => $bases,
				'extra_attributes' => array( 'required' => true ),
				'show_if'          => $is_create,
			),
			array(
				'id'               => 'post_type',
				'type'             => 'select',
				'label'            => __( 'Post type', 'ayecode-connect' ),
				'options'          => $post_types,
				'extra_attributes' => array( 'required' => true ),
				'show_if'          => $is_create,
			),
			array(
				'id'               => 'name',
				'type'             => 'text',
				'label'            => __( 'Name', 'ayecode-connect' ),
				'placeholder'      => __( 'Places Single Layout', 'ayecode-connect' ),
				'extra_attributes' => array( 'required' => true ),
				'show_if'          => $is_create,
			),
			array(
				'id'          => 'edit_info',
				'type'        => 'alert',
				'alert_type'  => 'info',
				'description' => __( 'Choose which page this template uses. The page you replace is not deleted, so you can switch back later.', 'ayecode-connect' ),
				'show_if'     => $is_edit . ' && [%uses_page%] == true',
			),
			array(
				'id'      => 'assign_page_id',
				'type'    => 'select',
				'label'   => __( 'Page', 'ayecode-connect' ),
				'options' => $this->get_page_options(),
				'show_if' => $is_edit . ' && [%uses_page%] == true',
			),
			array(
				'id'          => 'page_slug',
				'type'        => 'slug',
				'label'       => __( 'Page slug', 'ayecode-connect' ),
				'description' => __( 'The part of the URL that identifies this page.', 'ayecode-connect' ),
				'show_if'     => $is_edit . " && [%type%] == 'page'",
			),
		);
	}

	/**
	 * Pages available for assignment.
	 *
	 * @return array ID => title.
	 */
	private function get_page_options() {
		$options = array( '' => __( 'Select a page', 'ayecode-connect' ) );

		$pages = get_pages(
			array(
				'post_status' => array( 'publish', 'private', 'draft' ),
				'sort_column' => 'post_title',
			)
		);

		foreach ( (array) $pages as $page ) {
			$options[ $page->ID ] = $page->post_title . ' (#' . $page->ID . ')';
		}

		return $options;
	}

	/**
	 * Central handler for all AJAX actions on this page.
	 *
	 * The framework has already verified the nonce and capability.
	 *
	 * @param string $tool_action The 'ajax_action' from the field config.
	 * @param array  $post_data   The full $_POST data from the request.
	 */
	public function ajax_actions( $tool_action, $post_data ) {
		switch ( $tool_action ) {
			case 'get_templates':
				$this->handle_get_templates( $post_data );
				break;

			case 'create_override':
				$this->handle_create_override( $post_data );
				break;

			case 'update_template':
				$this->handle_update_template( $post_data );
				break;

			case 'delete_override':
				$this->handle_delete_override( $post_data );
				break;

			case 'restore_template':
				$this->handle_restore_template( $post_data );
				break;

			case 'recreate_template':
				$this->handle_recreate_template( $post_data );
				break;

			default:
				wp_send_json_error( array( 'message' => __( 'Unknown action.', 'ayecode-connect' ) ) );
		}
	}

	/**
	 * Decode the JSON "data" payload the list table sends.
	 *
	 * @param array $post_data POST data.
	 * @return array
	 */
	private function get_request_data( $post_data ) {
		if ( empty( $post_data['data'] ) ) {
			return array();
		}

		$data = json_decode( wp_unslash( $post_data['data'] ), true );

		return is_array( $data ) ? $data : array();
	}

	/**
	 * Resolve the row the request is about, or send an error.
	 *
	 * @param array $data Decoded request data.
	 * @return array Resolved template (product, key, data, group).
	 */
	private function require_row( $data ) {
		$row_id = ! empty( $data['id'] ) ? sanitize_text_field( $data['id'] ) : '';
		$row    = $row_id ? TemplateManager::resolve_row_id( $row_id ) : false;

		if ( ! $row ) {
			wp_send_json_error( array( 'message' => __( 'Template not found.', 'ayecode-connect' ) ) );
		}

		return $row;
	}

	/**
	 * Handle get_templates AJAX action.
	 *
	 * @param array $post_data POST data from the AJAX request.
	 */
	private function handle_get_templates( $post_data ) {
		$status = 'all';
		if ( ! empty( $post_data['status'] ) ) {
			$status = sanitize_key( wp_unslash( $post_data['status'] ) );
		}

		$section_id = ! empty( $post_data['section_id'] ) ? sanitize_key( wp_unslash( $post_data['section_id'] ) ) : 'all_templates';

		$product = '';
		if ( 'all_templates' !== $section_id ) {
			$product = preg_replace( '/_templates$/', '', $section_id );
		}

		$response = TemplateManager::instance()->get_templates_for_display(
			array(
				'status'  => $status,
				'product' => $product,
			)
		);

		wp_send_json_success( $response );
	}

	/**
	 * Handle create_override AJAX action.
	 *
	 * Creates a per post type copy of a base template.
	 *
	 * @param array $post_data POST data from the AJAX request.
	 */
	private function handle_create_override( $post_data ) {
		$data = $this->get_request_data( $post_data );

		$section_id = ! empty( $post_data['section_id'] ) ? sanitize_key( wp_unslash( $post_data['section_id'] ) ) : '';
		$product    = preg_replace( '/_templates$/', '', $section_id );
		$base_key   = ! empty( $data['base'] ) ? sanitize_text_field( $data['base'] ) : '';
		$post_type  = ! empty( $data['post_type'] ) ? sanitize_key( $data['post_type'] ) : '';
		$name       = ! empty( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';

		$registry = Registry::instance();
		$group    = $registry->get_product_templates( $product );
		$base     = $group ? $registry->get_template( $product, $base_key ) : false;

		if ( ! $group || ! $base || empty( $group['variants']['bases'][ $base_key ] ) || empty( $group['variants']['post_types'][ $post_type ] ) ) {
			wp_send_json_error( array( 'message' => __( 'Choose a template and a post type.', 'ayecode-connect' ) ) );
		}

		if ( empty( $name ) ) {
			$name = sprintf( '%1$s %2$s', $group['variants']['post_types'][ $post_type ], $base['title'] );
		}

		$environment = Environment::get();

		if ( ! empty( $environment['block_theme'] ) && ! empty( $base['fse_slug'] ) ) {
			$post_id = $this->create_fse_override( $base, $post_type, $name );
		} else {
			$post_id = $this->create_page_override( $base, $post_type, $name );
		}

		if ( is_wp_error( $post_id ) ) {
			wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
		}

		/**
		 * Action fired after a per post type override was created.
		 *
		 * Host plugins should record the new page (or block template) against
		 * the post type so it is used on the front end and registered as a row.
		 *
		 * @param int    $post_id   New page ID, or wp_template post ID on block themes.
		 * @param string $base_key  Key of the template it is based on.
		 * @param string $post_type Post type the override applies to.
		 * @param string $product   Product slug.
		 * @param array  $base      Validated base template data.
		 * @param bool   $is_fse    True when a block template was created.
		 */
		do_action( 'ayecode_template_override_created', $post_id, $base_key, $post_type, $product, $base, ! empty( $environment['block_theme'] ) && ! empty( $base['fse_slug'] ) );

		$registry->clear_cache();

		wp_send_json_success( array( 'message' => __( 'Template created.', 'ayecode-connect' ) ) );
	}

	/**
	 * Create a page override by cloning the base template page.
	 *
	 * @param array  $base      Base template data.
	 * @param string $post_type Post type.
	 * @param string $name      Page title.
	 * @return int|\WP_Error
	 */
	private function create_page_override( $base, $post_type, $name ) {
		$source = ! empty( $base['post_id'] ) ? get_post( $base['post_id'] ) : null;

		$content = $source ? $source->post_content : State::get_default_content( 0, '', $base );

		$post_id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => $name,
				'post_content'   => $content,
				'comment_status' => 'closed',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// Builders store the design in meta, so carry it across too.
		if ( $source ) {
			$skip = array( '_edit_lock', '_edit_last', '_wp_old_slug', State::META_DEFAULT_HASH, '_ayecode_template_key' );

			foreach ( (array) get_post_meta( $source->ID ) as $meta_key => $values ) {
				if ( in_array( $meta_key, $skip, true ) ) {
					continue;
				}
				foreach ( (array) $values as $value ) {
					add_post_meta( $post_id, $meta_key, maybe_unserialize( $value ) );
				}
			}
		}

		update_post_meta( $post_id, '_ayecode_template_type', $base['type'] );
		update_post_meta( $post_id, '_ayecode_template_post_type', $post_type );

		State::stamp( $post_id, $content );

		return $post_id;
	}

	/**
	 * Create a block template override such as 'single-gd_place'.
	 *
	 * @param array  $base      Base template data.
	 * @param string $post_type Post type.
	 * @param string $name      Template title.
	 * @return int|\WP_Error
	 */
	private function create_fse_override( $base, $post_type, $name ) {
		if ( empty( $base['location'] ) || 'wp_template' !== $base['fse_type'] ) {
			return new \WP_Error( 'ayecode_template_no_fse_variant', __( 'Only single and archive templates can have a per post type version on block themes.', 'ayecode-connect' ) );
		}

		$slug    = $base['location'] . '-' . $post_type;
		$content = Fse::get_content( $base['fse_slug'], $base['fse_type'] );

		return Fse::create_template( $slug, $name, $content, 'wp_template' );
	}

	/**
	 * Handle update_template AJAX action.
	 *
	 * Assigns a different page and/or changes the page slug.
	 *
	 * @param array $post_data POST data from the AJAX request.
	 */
	private function handle_update_template( $post_data ) {
		$data = $this->get_request_data( $post_data );
		$row  = $this->require_row( $data );

		$template_data = $row['data'];
		$current_id    = $template_data['post_id'];
		$new_id        = isset( $data['assign_page_id'] ) ? absint( $data['assign_page_id'] ) : $current_id;
		$slug          = isset( $data['page_slug'] ) ? sanitize_title( $data['page_slug'] ) : '';
		$messages      = array();

		if ( $new_id && $new_id !== $current_id ) {
			$page = get_post( $new_id );

			if ( ! $page || 'page' !== $page->post_type ) {
				wp_send_json_error( array( 'message' => __( 'Please choose a valid page.', 'ayecode-connect' ) ) );
			}

			/**
			 * Action fired when a different page is assigned to a template.
			 *
			 * Host plugins must update whatever option or setting resolves the
			 * page for this template.
			 *
			 * @param int    $new_id        Newly assigned page ID.
			 * @param string $template_key  Template key.
			 * @param array  $template_data Validated template data (with the previous post_id).
			 * @param string $product       Product slug.
			 */
			do_action( 'ayecode_template_assigned', $new_id, $row['key'], $template_data, $row['product'] );

			$current_id = $new_id;
			$messages[] = __( 'Page assigned.', 'ayecode-connect' );
		}

		if ( $current_id && '' !== $slug && 'page' === $template_data['type'] ) {
			$page = get_post( $current_id );

			if ( $page && $page->post_name !== $slug ) {
				$result = wp_update_post(
					array(
						'ID'        => $current_id,
						'post_name' => $slug,
					),
					true
				);

				if ( is_wp_error( $result ) ) {
					wp_send_json_error( array( 'message' => $result->get_error_message() ) );
				}

				$messages[] = __( 'Slug updated.', 'ayecode-connect' );
			}
		}

		Registry::instance()->clear_cache();

		wp_send_json_success(
			array(
				'message' => $messages ? implode( ' ', $messages ) : __( 'No changes made.', 'ayecode-connect' ),
			)
		);
	}

	/**
	 * Handle delete_override AJAX action.
	 *
	 * Only per post type overrides can be deleted.
	 *
	 * @param array $post_data POST data from the AJAX request.
	 */
	private function handle_delete_override( $post_data ) {
		$data = $this->get_request_data( $post_data );
		$row  = $this->require_row( $data );

		$template_data = $row['data'];

		if ( empty( $template_data['is_override'] ) ) {
			wp_send_json_error( array( 'message' => __( 'Core templates cannot be deleted. Restore it to default instead.', 'ayecode-connect' ) ) );
		}

		$environment = Environment::get();
		$is_fse      = ! empty( $environment['block_theme'] ) && ! empty( $template_data['fse_slug'] );

		if ( $is_fse ) {
			$deleted = Fse::reset( $template_data['fse_slug'], $template_data['fse_type'] );
		} else {
			$deleted = ! empty( $template_data['post_id'] ) ? (bool) wp_delete_post( $template_data['post_id'], true ) : true;
		}

		if ( ! $deleted ) {
			wp_send_json_error( array( 'message' => __( 'Failed to delete template.', 'ayecode-connect' ) ) );
		}

		/**
		 * Action fired after a per post type override was deleted.
		 *
		 * Host plugins should clear the setting that pointed at it.
		 *
		 * @param int    $post_id       Deleted page ID (0 for block templates).
		 * @param string $template_key  Template key.
		 * @param array  $template_data Validated template data.
		 * @param string $product       Product slug.
		 * @param bool   $is_fse        True when a block template was deleted.
		 */
		do_action( 'ayecode_template_override_deleted', $template_data['post_id'], $row['key'], $template_data, $row['product'], $is_fse );

		Registry::instance()->clear_cache();

		wp_send_json_success( array( 'message' => __( 'Template deleted.', 'ayecode-connect' ) ) );
	}

	/**
	 * Handle restore_template action.
	 *
	 * @param array $post_data POST data from the AJAX request.
	 */
	private function handle_restore_template( $post_data ) {
		$data = $this->get_request_data( $post_data );
		$row  = $this->require_row( $data );

		$result = Helpers::restore_template( $row['data']['post_id'], $row['key'], $row['data'] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Template restored to default.', 'ayecode-connect' ) ) );
	}

	/**
	 * Handle recreate_template action.
	 *
	 * @param array $post_data POST data from the AJAX request.
	 */
	private function handle_recreate_template( $post_data ) {
		$data = $this->get_request_data( $post_data );
		$row  = $this->require_row( $data );

		$result = Helpers::recreate_template( $row['key'], $row['data'], $row['product'] );

		if ( is_wp_error( $result ) ) {
			wp_send_json_error( array( 'message' => $result->get_error_message() ) );
		}

		wp_send_json_success( array( 'message' => __( 'Page recreated.', 'ayecode-connect' ) ) );
	}
}
