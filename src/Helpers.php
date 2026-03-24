<?php
/**
 * Helper Functions for Template Management
 *
 * Provides utility functions for plugins to create and manage templates.
 *
 * @package AyeCode\Templates
 */

namespace AyeCode\Templates;

/**
 * Helpers class.
 *
 * Static helper methods for template creation and management.
 */
class Helpers {

	/**
	 * Get the current environment.
	 *
	 * Convenience wrapper for Environment::get().
	 *
	 * @param bool $force_refresh Force refresh the cache.
	 * @return array Environment configuration array.
	 */
	public static function get_environment( $force_refresh = false ) {
		return Environment::get( $force_refresh );
	}

	/**
	 * Create a template (page or layout CPT).
	 *
	 * This is the main helper function that plugins use to create their templates.
	 *
	 * @param string $template_key Unique key for the template (e.g., 'gd_add_listing').
	 * @param array  $config Template configuration array.
	 * @return int|false Post ID on success, false on failure.
	 */
	public static function create_template( $template_key, $config ) {
		// Check if template already exists
		$existing_id = self::get_template_id_by_key( $template_key );
		if ( $existing_id ) {
			return $existing_id;
		}

		// Validate config
		if ( empty( $config['type'] ) || ! in_array( $config['type'], array( 'page', 'layout' ), true ) ) {
			return false;
		}

		// Get page_args
		$page_args = ! empty( $config['page_args'] ) ? $config['page_args'] : array();

		// Set defaults
		$defaults = array(
			'post_title'   => ! empty( $config['title'] ) ? $config['title'] : ucwords( str_replace( '_', ' ', $template_key ) ),
			'post_content' => '',
			'post_status'  => 'publish',
			'post_type'    => 'page' === $config['type'] ? 'page' : PostTypes\TemplateCPT::POST_TYPE,
		);

		$page_args = wp_parse_args( $page_args, $defaults );

		/**
		 * Filter the page args before creating a template.
		 *
		 * @param array  $page_args    The page arguments.
		 * @param string $template_key The template key.
		 * @param array  $config       The full template config.
		 */
		$page_args = apply_filters( 'ayecode_before_create_template', $page_args, $template_key, $config );

		// Create the post
		$post_id = wp_insert_post( $page_args, true );

		if ( is_wp_error( $post_id ) ) {
			return false;
		}

		// Store the template key for future lookups
		update_post_meta( $post_id, '_ayecode_template_key', $template_key );

		// Store the template type
		update_post_meta( $post_id, '_ayecode_template_type', $config['type'] );

		// Store additional config as meta
		if ( ! empty( $config['builder'] ) ) {
			update_post_meta( $post_id, '_ayecode_template_builder', $config['builder'] );
		}

		if ( ! empty( $config['product'] ) ) {
			update_post_meta( $post_id, '_ayecode_template_product', $config['product'] );
		}

		/**
		 * Action fired after a template is created.
		 *
		 * @param int    $post_id      The created post ID.
		 * @param string $template_key The template key.
		 * @param array  $config       The full template config.
		 */
		do_action( 'ayecode_template_created', $post_id, $template_key, $config );

		return $post_id;
	}

	/**
	 * Get a template post ID by its key.
	 *
	 * @param string $template_key The template key.
	 * @return int|false Post ID if found, false otherwise.
	 */
	public static function get_template_id_by_key( $template_key ) {
		$query = new \WP_Query(
			array(
				'post_type'      => array( 'page', PostTypes\TemplateCPT::POST_TYPE ),
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => '_ayecode_template_key',
				'meta_value'     => $template_key,
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $query->posts ) ) {
			return $query->posts[0];
		}

		return false;
	}

	/**
	 * Get the edit URL for a template.
	 *
	 * Determines the appropriate edit URL based on the builder and post type.
	 *
	 * @param int $post_id The post ID.
	 * @return string The edit URL.
	 */
	public static function get_template_edit_url( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return '';
		}

		$builder       = get_post_meta( $post_id, '_ayecode_template_builder', true );
		$template_type = get_post_meta( $post_id, '_ayecode_template_type', true );

		// Check for custom edit URL
		$custom_url = apply_filters( 'ayecode_template_edit_url', '', $post_id, $builder, $template_type );
		if ( ! empty( $custom_url ) ) {
			return $custom_url;
		}

		// Builder-specific URLs
		if ( 'elementor' === $builder && Environment::has_builder( 'elementor' ) ) {
			return admin_url( 'post.php?post=' . $post_id . '&action=elementor' );
		}

		if ( 'beaver_builder' === $builder && Environment::has_builder( 'beaver_builder' ) ) {
			return admin_url( 'post.php?post=' . $post_id . '&fl_builder' );
		}

		if ( 'divi' === $builder && Environment::has_builder( 'divi' ) ) {
			return admin_url( 'post.php?post=' . $post_id . '&et_fb=1&action=edit' );
		}

		if ( 'oxygen' === $builder && Environment::has_builder( 'oxygen' ) ) {
			return admin_url( 'admin.php?page=ct_template&ct_id=' . $post_id );
		}

		if ( 'brizy' === $builder && Environment::has_builder( 'brizy' ) ) {
			return admin_url( 'post.php?post=' . $post_id . '&action=edit-with-brizy' );
		}

		if ( 'thrive_architect' === $builder && Environment::has_builder( 'thrive_architect' ) ) {
			return admin_url( 'post.php?post=' . $post_id . '&tve=true&action=architect' );
		}

		if ( 'breakdance' === $builder && Environment::has_builder( 'breakdance' ) ) {
			return admin_url( 'admin.php?page=breakdance&id=' . $post_id );
		}

		if ( 'bricks' === $builder && Environment::has_builder( 'bricks' ) ) {
			return admin_url( 'post.php?post=' . $post_id . '&action=bricks_editor' );
		}

		// FSE/Block template
		if ( 'wp_template' === $post->post_type || 'wp_template_part' === $post->post_type ) {
			return admin_url( 'site-editor.php?postType=' . $post->post_type . '&postId=' . $post_id );
		}

		// Default WordPress editor
		return admin_url( 'post.php?post=' . $post_id . '&action=edit' );
	}

	/**
	 * Mark a template as customized.
	 *
	 * Sets a custom post status to indicate user modification.
	 *
	 * @param int $post_id The post ID.
	 * @return bool True on success, false on failure.
	 */
	public static function mark_template_customized( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		// Store original status if not already stored
		$original_status = get_post_meta( $post_id, '_ayecode_original_status', true );
		if ( empty( $original_status ) ) {
			update_post_meta( $post_id, '_ayecode_original_status', $post->post_status );
		}

		// Update to customized status
		wp_update_post(
			array(
				'ID'          => $post_id,
				'post_status' => 'customized',
			)
		);

		return true;
	}

	/**
	 * Restore a template to its default state.
	 *
	 * Plugins should hook into 'ayecode_restore_template' or filter
	 * 'ayecode_template_default_content' to provide restoration logic.
	 *
	 * @param int $post_id The post ID.
	 * @return bool True on success, false on failure.
	 */
	public static function restore_template( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post ) {
			return false;
		}

		$template_key = get_post_meta( $post_id, '_ayecode_template_key', true );

		/**
		 * Allow plugins to provide default content for restoration.
		 *
		 * @param string $content      The default content (empty by default).
		 * @param int    $post_id      The post ID.
		 * @param string $template_key The template key.
		 */
		$default_content = apply_filters( 'ayecode_template_default_content', '', $post_id, $template_key );

		/**
		 * Action fired before restoring a template.
		 *
		 * Plugins can use this to handle their own restoration logic.
		 *
		 * @param int    $post_id      The post ID.
		 * @param string $template_key The template key.
		 */
		do_action( 'ayecode_before_restore_template', $post_id, $template_key );

		// If content provided, update the post
		if ( ! empty( $default_content ) ) {
			wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $default_content,
				)
			);
		}

		// Restore original status
		$original_status = get_post_meta( $post_id, '_ayecode_original_status', true );
		if ( ! empty( $original_status ) ) {
			wp_update_post(
				array(
					'ID'          => $post_id,
					'post_status' => $original_status,
				)
			);
			delete_post_meta( $post_id, '_ayecode_original_status' );
		}

		/**
		 * Action fired after a template is restored.
		 *
		 * @param int    $post_id      The post ID.
		 * @param string $template_key The template key.
		 */
		do_action( 'ayecode_template_restored', $post_id, $template_key );

		return true;
	}

	/**
	 * Get all templates for a specific product.
	 *
	 * @param string $product The product slug (e.g., 'geodirectory').
	 * @return array Array of post objects.
	 */
	public static function get_templates_by_product( $product ) {
		$query = new \WP_Query(
			array(
				'post_type'      => array( 'page', PostTypes\TemplateCPT::POST_TYPE ),
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_key'       => '_ayecode_template_product',
				'meta_value'     => $product,
			)
		);

		return $query->posts;
	}

	/**
	 * Delete a template by key.
	 *
	 * @param string $template_key The template key.
	 * @return bool True on success, false on failure.
	 */
	public static function delete_template( $template_key ) {
		$post_id = self::get_template_id_by_key( $template_key );

		if ( ! $post_id ) {
			return false;
		}

		$result = wp_delete_post( $post_id, true );

		return ! empty( $result );
	}
}
