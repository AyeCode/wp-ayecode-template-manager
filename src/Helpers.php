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
	 * Create a template page.
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
			'post_title'     => ! empty( $config['title'] ) ? $config['title'] : ucwords( str_replace( '_', ' ', $template_key ) ),
			'post_content'   => '',
			'post_status'    => 'publish',
			'post_type'      => 'page',
			'comment_status' => 'closed',
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

		if ( ! empty( $config['product'] ) ) {
			update_post_meta( $post_id, '_ayecode_template_product', $config['product'] );
		}

		// Remember what "untouched" looks like so state detection survives
		// later changes to the plugin's default markup.
		self::stamp_default_content( $post_id, $page_args['post_content'] );

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
				'post_type'      => 'page',
				'post_status'    => 'any',
				'posts_per_page' => 1,
				'meta_key'       => '_ayecode_template_key', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $template_key, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
				'fields'         => 'ids',
			)
		);

		if ( ! empty( $query->posts ) ) {
			return $query->posts[0];
		}

		return false;
	}

	/**
	 * Get the edit URL for a template page.
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

		/**
		 * Filter the edit URL for a template page.
		 *
		 * @param string $url           Empty by default.
		 * @param int    $post_id       Post ID.
		 * @param string $builder       Builder meta value, if any.
		 * @param string $template_type 'page' or 'layout'.
		 */
		$custom_url = apply_filters( 'ayecode_template_edit_url', '', $post_id, $builder, $template_type );
		if ( ! empty( $custom_url ) ) {
			return $custom_url;
		}

		if ( 'wp_template' === $post->post_type || 'wp_template_part' === $post->post_type ) {
			return Fse::get_edit_url( $post->post_name, $post->post_type );
		}

		$environment = Environment::get();
		$registered  = Registry::instance()->find_by_post_id( $post_id );

		return Router::get_edit_url( $post_id, $registered ? $registered['data'] : array(), $environment );
	}

	/**
	 * Stamp a page with the hash of its default content.
	 *
	 * Host plugins call this right after creating a template page so the
	 * package knows what "untouched" looks like.
	 *
	 * @param int         $post_id Page ID.
	 * @param string|null $content The default content. Defaults to the page's current content.
	 */
	public static function stamp_default_content( $post_id, $content = null ) {
		if ( ! $post_id ) {
			return;
		}

		State::stamp( $post_id, $content );
	}

	/**
	 * Stamp a page only when its content still matches the given default.
	 *
	 * Safe to call on every install run: an already customized page is left alone.
	 *
	 * @param int    $post_id Page ID.
	 * @param string $content The default content.
	 */
	public static function maybe_stamp_default_content( $post_id, $content ) {
		if ( ! $post_id || metadata_exists( 'post', $post_id, State::META_DEFAULT_HASH ) ) {
			return;
		}

		$current = get_post_field( 'post_content', $post_id, 'raw' );

		if ( State::hash( $current ) === State::hash( $content ) ) {
			State::stamp( $post_id, $content );
		}
	}

	/**
	 * Restore a template to its default state.
	 *
	 * On block themes this deletes the user customized copy of the block
	 * template, which is what the Site Editor's own "Reset" does. On classic
	 * themes the page content is replaced with the host plugin's default,
	 * supplied through the 'ayecode_template_default_content' filter.
	 *
	 * @param int         $post_id       The page ID.
	 * @param string|null $template_key  The template key. Looked up from the page when omitted.
	 * @param array|null  $template_data Validated template data. Looked up when omitted.
	 * @return true|\WP_Error
	 */
	public static function restore_template( $post_id, $template_key = null, $template_data = null ) {
		if ( null === $template_key || null === $template_data ) {
			$registered = Registry::instance()->find_by_post_id( $post_id );

			if ( ! $registered ) {
				return new \WP_Error( 'ayecode_template_unknown', __( 'This page is not a registered template.', 'ayecode-connect' ) );
			}

			$template_key  = $registered['key'];
			$template_data = $registered['data'];
		}

		/**
		 * Action fired before restoring a template.
		 *
		 * @param int    $post_id       The page ID.
		 * @param string $template_key  The template key.
		 * @param array  $template_data Validated template data.
		 */
		do_action( 'ayecode_before_restore_template', $post_id, $template_key, $template_data );

		$environment = Environment::get();

		if ( ! empty( $environment['block_theme'] ) && ! empty( $template_data['fse_slug'] ) ) {
			$reset = Fse::reset( $template_data['fse_slug'], $template_data['fse_type'] );

			if ( ! $reset ) {
				return new \WP_Error( 'ayecode_template_not_customized', __( 'This template has no customizations to remove.', 'ayecode-connect' ) );
			}
		} else {
			$post = get_post( $post_id );
			if ( ! $post ) {
				return new \WP_Error( 'ayecode_template_missing', __( 'The template page no longer exists.', 'ayecode-connect' ) );
			}

			$default_content = State::get_default_content( $post_id, $template_key, $template_data );

			if ( '' === $default_content ) {
				return new \WP_Error( 'ayecode_template_no_default', __( 'No default content is available for this template.', 'ayecode-connect' ) );
			}

			$result = wp_update_post(
				array(
					'ID'           => $post_id,
					'post_content' => $default_content,
				),
				true
			);

			if ( is_wp_error( $result ) ) {
				return $result;
			}

			// Page builders keep their own copy of the design in post meta. Clear
			// the flags so the page opens in the block editor with the default.
			foreach ( array( '_elementor_edit_mode', '_elementor_data', '_et_pb_use_builder', '_fl_builder_enabled', '_bricks_page_content_2', 'brizy_enabled', 'tcb_editor_enabled', '_breakdance_data', '_wpb_vc_js_status' ) as $meta_key ) {
				delete_post_meta( $post_id, $meta_key );
			}

			State::stamp( $post_id, $default_content );
		}

		/**
		 * Action fired after a template is restored.
		 *
		 * @param int    $post_id       The page ID.
		 * @param string $template_key  The template key.
		 * @param array  $template_data Validated template data.
		 */
		do_action( 'ayecode_template_restored', $post_id, $template_key, $template_data );

		Registry::instance()->clear_cache();

		return true;
	}

	/**
	 * Recreate a missing template page.
	 *
	 * The host plugin gets first refusal through the 'ayecode_template_recreate'
	 * filter (it usually knows the right slug and option to update). When it
	 * returns nothing, a page is created from the default content and the host
	 * is told through 'ayecode_template_recreated'.
	 *
	 * @param string $template_key  The template key.
	 * @param array  $template_data Validated template data.
	 * @param string $product       Product slug.
	 * @return int|\WP_Error New page ID.
	 */
	public static function recreate_template( $template_key, $template_data, $product ) {
		// A trashed page is the common case: bring it back rather than making a new one.
		$current_id = ! empty( $template_data['post_id'] ) ? absint( $template_data['post_id'] ) : 0;
		if ( $current_id && 'trash' === get_post_status( $current_id ) ) {
			$restored = wp_untrash_post( $current_id );

			if ( $restored ) {
				if ( 'publish' !== get_post_status( $current_id ) ) {
					wp_publish_post( $current_id );
				}

				Registry::instance()->clear_cache();

				return $current_id;
			}
		}

		/**
		 * Let the host plugin recreate the page itself.
		 *
		 * @param int    $post_id       0 by default. Return the new page ID to short circuit.
		 * @param string $template_key  The template key.
		 * @param array  $template_data Validated template data.
		 * @param string $product       Product slug.
		 */
		$post_id = (int) apply_filters( 'ayecode_template_recreate', 0, $template_key, $template_data, $product );

		if ( $post_id && get_post( $post_id ) ) {
			Registry::instance()->clear_cache();

			return $post_id;
		}

		$default_content = State::get_default_content( 0, $template_key, $template_data );

		$post_id = wp_insert_post(
			array(
				'post_type'      => 'page',
				'post_status'    => 'publish',
				'post_title'     => wp_strip_all_tags( $template_data['title'] ),
				'post_content'   => $default_content,
				'comment_status' => 'closed',
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		State::stamp( $post_id, $default_content );

		/**
		 * Action fired after the package recreated a missing template page.
		 *
		 * Host plugins should point their page option at the new ID.
		 *
		 * @param int    $post_id       New page ID.
		 * @param string $template_key  The template key.
		 * @param array  $template_data Validated template data.
		 * @param string $product       Product slug.
		 */
		do_action( 'ayecode_template_recreated', $post_id, $template_key, $template_data, $product );

		Registry::instance()->clear_cache();

		return $post_id;
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
				'post_type'      => 'page',
				'post_status'    => 'any',
				'posts_per_page' => -1,
				'meta_key'       => '_ayecode_template_product', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $product, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
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
