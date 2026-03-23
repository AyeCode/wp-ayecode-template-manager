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
     * Get the settings configuration.
     *
     * Defines the list_table interface with filterable sections for product contexts.
     *
     * @return array Configuration array with sections and fields.
     */
    public function get_config() {
        // Build the base sections array with filterable product contexts.
        $sections = array(
            array(
                'id'   => 'all_templates',
                'name' => __( 'All Templates', 'wp-ayecode-template-manager' ),
                'icon' => 'fa-solid fa-layer-group',
                'type' => 'list_table',

                'table_config' => array(
                    'singular'         => __( 'Template', 'wp-ayecode-template-manager' ),
                    'plural'           => __( 'Templates', 'wp-ayecode-template-manager' ),
                    'ajax_action_get'  => 'get_templates',
                    'ajax_action_bulk' => 'bulk_template_action',

                    'columns' => array(
                        'image'    => array( 'label' => __( 'Preview', 'wp-ayecode-template-manager' ) ),
                        'name'     => array( 'label' => __( 'Template Name', 'wp-ayecode-template-manager' ) ),
                        'builder'  => array( 'label' => __( 'Builder', 'wp-ayecode-template-manager' ) ),
                        'product'  => array( 'label' => __( 'Product', 'wp-ayecode-template-manager' ) ),
                    ),

                    'statuses' => array(
                        'status_key'     => 'status',
                        'labels'         => array(
//							'all'        => __( 'All', 'wp-ayecode-template-manager' ),
                            'active'     => __( 'Active', 'wp-ayecode-template-manager' ),
                            'draft'      => __( 'Drafts', 'wp-ayecode-template-manager' ),
                            'customized' => __( 'Customized', 'wp-ayecode-template-manager' ),
                            'default'    => __( 'Default', 'wp-ayecode-template-manager' ),
                        ),
//						'default_status' => 'all',
                    ),

                    'bulk_actions' => array(
                        'delete'   => __( 'Delete', 'wp-ayecode-template-manager' ),
                        'activate' => __( 'Activate', 'wp-ayecode-template-manager' ),
                        'draft'    => __( 'Set to Draft', 'wp-ayecode-template-manager' ),
                    ),
                ),

                'modal_config' => array(
                    'title_add'          => __( 'Add New Template', 'wp-ayecode-template-manager' ),
                    'title_edit'         => __( 'Edit Template', 'wp-ayecode-template-manager' ),
                    'ajax_action_create' => 'create_template',
                    'ajax_action_update' => 'update_template',
                    'ajax_action_delete' => 'delete_template',

                    'fields' => array(
                        array(
                            'id'          => 'name',
                            'type'        => 'text',
                            'label'       => __( 'Template Name', 'wp-ayecode-template-manager' ),
                            'description' => __( 'Enter a descriptive name for this template', 'wp-ayecode-template-manager' ),
                            'extra_attributes' => array( 'required' => true ),
                        ),
                        array(
                            'id'          => 'builder',
                            'type'        => 'select',
                            'label'       => __( 'Page Builder', 'wp-ayecode-template-manager' ),
                            'description' => __( 'Select the page builder for this template', 'wp-ayecode-template-manager' ),
                            'options'     => array(
                                'gutenberg'  => __( 'Gutenberg', 'wp-ayecode-template-manager' ),
                                'elementor'  => __( 'Elementor', 'wp-ayecode-template-manager' ),
                                'beaver'     => __( 'Beaver Builder', 'wp-ayecode-template-manager' ),
                                'divi'       => __( 'Divi', 'wp-ayecode-template-manager' ),
                            ),
                            'default' => 'gutenberg',
                        ),
                        array(
                            'id'          => 'product',
                            'type'        => 'select',
                            'label'       => __( 'Product', 'wp-ayecode-template-manager' ),
                            'description' => __( 'Assign this template to a product', 'wp-ayecode-template-manager' ),
                            'options'     => array(
                                'geodirectory' => __( 'GeoDirectory', 'wp-ayecode-template-manager' ),
                                'userswp'      => __( 'UsersWP', 'wp-ayecode-template-manager' ),
                                'invoicing'    => __( 'Invoicing', 'wp-ayecode-template-manager' ),
                            ),
                            'default' => 'geodirectory',
                        ),
                        array(
                            'id'          => 'status',
                            'type'        => 'select',
                            'label'       => __( 'Status', 'wp-ayecode-template-manager' ),
                            'options'     => array(
                                'active'     => __( 'Active', 'wp-ayecode-template-manager' ),
                                'draft'      => __( 'Draft', 'wp-ayecode-template-manager' ),
                                'customized' => __( 'Customized', 'wp-ayecode-template-manager' ),
                                'default'    => __( 'Default', 'wp-ayecode-template-manager' ),
                            ),
                            'default' => 'draft',
                        ),
                        array(
                            'id'          => 'description',
                            'type'        => 'textarea',
                            'label'       => __( 'Description', 'wp-ayecode-template-manager' ),
                            'description' => __( 'Optional description for this template', 'wp-ayecode-template-manager' ),
                            'rows'        => 4,
                        ),
                    ),
                ),
            ),
        );

        /**
         * Filter the template manager sections.
         *
         * Allows other plugins to inject product-specific sections into the left sidebar.
         *
         * @param array $sections Array of section configurations.
         */
        $sections = apply_filters( 'ayecode_template_manager_sections', $sections );

        return array(
            'sections' => $sections,
        );
    }

    /**
     * Central handler for all AJAX actions on this page.
     *
     * Handles CRUD operations for the template list_table interface.
     *
     * @param string $tool_action The 'ajax_action' from the field config.
     * @param array  $post_data   The full $_POST data from the request.
     */
    public function ajax_actions( $tool_action, $post_data ) {
        // Nonce already verified by Settings Framweork

        switch ( $tool_action ) {
            case 'get_templates':
                $this->handle_get_templates( $post_data );
                break;

            case 'create_template':
                $this->handle_create_template( $post_data );
                break;

            case 'update_template':
                $this->handle_update_template( $post_data );
                break;

            case 'delete_template':
                $this->handle_delete_template( $post_data );
                break;

            case 'bulk_template_action':
                $this->handle_bulk_action( $post_data );
                break;

            default:
                wp_send_json_error( array( 'message' => __( 'Unknown action.', 'wp-ayecode-template-manager' ) ) );
        }
    }

    /**
     * Handle get_templates AJAX action.
     *
     * Retrieves all templates with optional status filtering.
     *
     * @param array $post_data POST data from the AJAX request.
     */
    private function handle_get_templates( $post_data ) {
        $data   = ! empty( $post_data['data'] ) ? json_decode( stripslashes( $post_data['data'] ), true ) : array();
        $status = ! empty( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'all';

        // Query templates from the custom post type.
        $args = array(
            'post_type'      => PostTypes\TemplateCPT::POST_TYPE,
            'posts_per_page' => -1,
            'post_status'    => 'any',
        );

        $templates = PostTypes\TemplateCPT::get_templates( $args );

        // Format templates for the list_table.
        $items = array();
        $counts = array(
            'all'        => 0,
            'active'     => 0,
            'draft'      => 0,
            'customized' => 0,
            'default'    => 0,
        );

        foreach ( $templates as $template ) {
            $template_status = get_post_meta( $template->ID, '_template_status', true ) ?: 'draft';
            $template_builder = get_post_meta( $template->ID, '_template_builder', true ) ?: 'gutenberg';
            $template_product = get_post_meta( $template->ID, '_template_product', true ) ?: '';

            // Get thumbnail.
            $thumbnail = get_the_post_thumbnail_url( $template->ID, 'thumbnail' );
            if ( ! $thumbnail ) {
                $thumbnail = '';
            }

            $item = array(
                'id'      => $template->ID,
                'image'   => $thumbnail ? '<img src="' . esc_url( $thumbnail ) . '" alt="' . esc_attr( $template->post_title ) . '" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px;">' : '',
                'name'    => $template->post_title,
                'builder' => ucfirst( $template_builder ),
                'product' => ucfirst( $template_product ),
                'status'  => $template_status,
            );

            // Filter by status.
            if ( 'all' === $status || $template_status === $status ) {
                $items[] = $item;
            }

            // Count by status.
            $counts['all']++;
            if ( isset( $counts[ $template_status ] ) ) {
                $counts[ $template_status ]++;
            }
        }

        $response = array(
            'items'  => $items,
            'counts' => $counts,
        );

        wp_send_json_success( $response );
    }

    /**
     * Handle create_template AJAX action.
     *
     * Creates a new template post.
     *
     * @param array $post_data POST data from the AJAX request.
     */
    private function handle_create_template( $post_data ) {
        $data = ! empty( $post_data['data'] ) ? json_decode( stripslashes( $post_data['data'] ), true ) : array();

        $name        = ! empty( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
        $builder     = ! empty( $data['builder'] ) ? sanitize_text_field( $data['builder'] ) : 'gutenberg';
        $product     = ! empty( $data['product'] ) ? sanitize_text_field( $data['product'] ) : '';
        $status      = ! empty( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'draft';
        $description = ! empty( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '';

        if ( empty( $name ) ) {
            wp_send_json_error( array( 'message' => __( 'Template name is required.', 'wp-ayecode-template-manager' ) ) );
        }

        // Create the template post.
        $post_id = wp_insert_post(
            array(
                'post_title'   => $name,
                'post_content' => $description,
                'post_type'    => PostTypes\TemplateCPT::POST_TYPE,
                'post_status'  => 'publish',
            )
        );

        if ( is_wp_error( $post_id ) ) {
            wp_send_json_error( array( 'message' => $post_id->get_error_message() ) );
        }

        // Save meta fields.
        update_post_meta( $post_id, '_template_status', $status );
        update_post_meta( $post_id, '_template_builder', $builder );
        update_post_meta( $post_id, '_template_product', $product );

        // Get thumbnail.
        $thumbnail = get_the_post_thumbnail_url( $post_id, 'thumbnail' );
        if ( ! $thumbnail ) {
            $thumbnail = plugins_url( 'assets/img/placeholder.png', AYECODE_TEMPLATE_MANAGER_PLUGIN_FILE );
        }

        // Return the created template.
        $result = array(
            'id'      => $post_id,
            'image'   => $thumbnail ? '<img src="' . esc_url( $thumbnail ) . '" alt="' . esc_attr( $name ) . '" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px;">' : '',
            'name'    => $name,
            'builder' => ucfirst( $builder ),
            'product' => ucfirst( $product ),
            'status'  => $status,
        );

        wp_send_json_success( $result );
    }

    /**
     * Handle update_template AJAX action.
     *
     * Updates an existing template post.
     *
     * @param array $post_data POST data from the AJAX request.
     */
    private function handle_update_template( $post_data ) {
        $data = ! empty( $post_data['data'] ) ? json_decode( stripslashes( $post_data['data'] ), true ) : array();

        $id          = ! empty( $data['id'] ) ? absint( $data['id'] ) : 0;
        $name        = ! empty( $data['name'] ) ? sanitize_text_field( $data['name'] ) : '';
        $builder     = ! empty( $data['builder'] ) ? sanitize_text_field( $data['builder'] ) : 'gutenberg';
        $product     = ! empty( $data['product'] ) ? sanitize_text_field( $data['product'] ) : '';
        $status      = ! empty( $data['status'] ) ? sanitize_text_field( $data['status'] ) : 'draft';
        $description = ! empty( $data['description'] ) ? sanitize_textarea_field( $data['description'] ) : '';

        if ( ! $id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid template ID.', 'wp-ayecode-template-manager' ) ) );
        }

        // Verify the post exists and is a template.
        $template = PostTypes\TemplateCPT::get_template( $id );
        if ( ! $template ) {
            wp_send_json_error( array( 'message' => __( 'Template not found.', 'wp-ayecode-template-manager' ) ) );
        }

        // Update the post.
        $result = wp_update_post(
            array(
                'ID'           => $id,
                'post_title'   => $name,
                'post_content' => $description,
            )
        );

        if ( is_wp_error( $result ) ) {
            wp_send_json_error( array( 'message' => $result->get_error_message() ) );
        }

        // Update meta fields.
        update_post_meta( $id, '_template_status', $status );
        update_post_meta( $id, '_template_builder', $builder );
        update_post_meta( $id, '_template_product', $product );

        // Get thumbnail.
        $thumbnail = get_the_post_thumbnail_url( $id, 'thumbnail' );
        if ( ! $thumbnail ) {
            $thumbnail = plugins_url( 'assets/img/placeholder.png', AYECODE_TEMPLATE_MANAGER_PLUGIN_FILE );
        }

        // Return the updated template.
        $result = array(
            'id'      => $id,
            'image'   => $thumbnail ? '<img src="' . esc_url( $thumbnail ) . '" alt="' . esc_attr( $name ) . '" style="width: 80px; height: 80px; object-fit: cover; border-radius: 4px;">' : '',
            'name'    => $name,
            'builder' => ucfirst( $builder ),
            'product' => ucfirst( $product ),
            'status'  => $status,
        );

        wp_send_json_success( $result );
    }

    /**
     * Handle delete_template AJAX action.
     *
     * Deletes a template post.
     *
     * @param array $post_data POST data from the AJAX request.
     */
    private function handle_delete_template( $post_data ) {
        $data = ! empty( $post_data['data'] ) ? json_decode( stripslashes( $post_data['data'] ), true ) : array();
        $id   = ! empty( $data['id'] ) ? absint( $data['id'] ) : 0;

        if ( ! $id ) {
            wp_send_json_error( array( 'message' => __( 'Invalid template ID.', 'wp-ayecode-template-manager' ) ) );
        }

        // Verify the post exists and is a template.
        $template = PostTypes\TemplateCPT::get_template( $id );
        if ( ! $template ) {
            wp_send_json_error( array( 'message' => __( 'Template not found.', 'wp-ayecode-template-manager' ) ) );
        }

        // Delete the post.
        $result = wp_delete_post( $id, true );

        if ( ! $result ) {
            wp_send_json_error( array( 'message' => __( 'Failed to delete template.', 'wp-ayecode-template-manager' ) ) );
        }

        wp_send_json_success( array( 'message' => __( 'Template deleted successfully.', 'wp-ayecode-template-manager' ) ) );
    }

    /**
     * Handle bulk_template_action AJAX action.
     *
     * Processes bulk actions on multiple templates.
     *
     * @param array $post_data POST data from the AJAX request.
     */
    private function handle_bulk_action( $post_data ) {
        $data     = ! empty( $post_data['data'] ) ? json_decode( stripslashes( $post_data['data'] ), true ) : array();
        $item_ids = ! empty( $data['item_ids'] ) ? $data['item_ids'] : array();
        $action   = ! empty( $data['action'] ) ? sanitize_text_field( $data['action'] ) : '';

        if ( empty( $item_ids ) || ! is_array( $item_ids ) ) {
            wp_send_json_error( array( 'message' => __( 'No templates selected.', 'wp-ayecode-template-manager' ) ) );
        }

        if ( empty( $action ) ) {
            wp_send_json_error( array( 'message' => __( 'No action specified.', 'wp-ayecode-template-manager' ) ) );
        }

        $success_count = 0;
        $error_count   = 0;
        $errors        = array();

        foreach ( $item_ids as $id ) {
            $id = absint( $id );

            if ( 'delete' === $action ) {
                $result = wp_delete_post( $id, true );
                if ( $result ) {
                    $success_count++;
                } else {
                    $error_count++;
                    $errors[] = sprintf( __( 'Failed to delete template ID: %d', 'wp-ayecode-template-manager' ), $id );
                }
            } elseif ( 'activate' === $action ) {
                update_post_meta( $id, '_template_status', 'active' );
                $success_count++;
            } elseif ( 'draft' === $action ) {
                update_post_meta( $id, '_template_status', 'draft' );
                $success_count++;
            }
        }

        // Build response message.
        if ( $success_count > 0 && 0 === $error_count ) {
            $message = sprintf(
            /* translators: %d: number of templates */
                _n( '%d template processed successfully.', '%d templates processed successfully.', $success_count, 'wp-ayecode-template-manager' ),
                $success_count
            );
            wp_send_json_success( array( 'message' => $message ) );
        } elseif ( $success_count > 0 && $error_count > 0 ) {
            $message = sprintf(
            /* translators: %1$d: success count, %2$d: error count */
                __( '%1$d template(s) processed successfully. %2$d failed.', 'wp-ayecode-template-manager' ),
                $success_count,
                $error_count
            );
            wp_send_json_success( array( 'message' => $message ) );
        } else {
            wp_send_json_error( array( 'message' => __( 'Bulk action failed: ', 'wp-ayecode-template-manager' ) . implode( ', ', $errors ) ) );
        }
    }
}
