<?php
/**
 * Router Class
 *
 * Handles routing to appropriate editor based on theme type and builder detection.
 *
 * @package AyeCode\Templates
 */

namespace AyeCode\Templates;

/**
 * Router class.
 *
 * Centralized edit URL generation for templates.
 */
class Router {

	/**
	 * Get the edit URL for a template.
	 *
	 * Routes to FSE Site Editor for block themes, or detects builder and routes accordingly for classic themes.
	 *
	 * @param int   $post_id       The post ID (used for classic themes).
	 * @param array $template_data Template data from Registry.
	 * @param array $environment   Environment configuration.
	 * @return string The edit URL.
	 */
	public static function get_edit_url( $post_id, $template_data, $environment ) {

		// ==========================================
		// PATH A: Block Theme (FSE)
		// ==========================================
		if ( ! empty( $environment['block_theme'] ) && ! empty( $template_data['fse_slug'] ) ) {
			return self::get_fse_edit_url( $template_data );
		}

		// ==========================================
		// PATH B: Classic Themes & Page Builders
		// ==========================================
		if ( empty( $post_id ) || ! get_post( $post_id ) ) {
			// Fallback if post doesn't exist
			return admin_url( 'post-new.php?post_type=page' );
		}

		// Detect actual builder from post meta
		$detected_builder = self::detect_builder( $post_id, $environment );

		// Route based on detected builder
		return self::get_builder_edit_url( $post_id, $detected_builder );
	}

	/**
	 * Get FSE Site Editor URL.
	 *
	 * @param array $template_data Template data containing fse_slug and fse_type.
	 * @return string FSE edit URL.
	 */
	private static function get_fse_edit_url( $template_data ) {
		$fse_slug = $template_data['fse_slug'];
		$fse_type = ! empty( $template_data['fse_type'] ) ? $template_data['fse_type'] : 'wp_template';

		// Get the actual template using WordPress core function
		$templates = get_block_templates(
			array(
				'slug__in' => array( $fse_slug ),
			),
            $fse_type
		);

		// Use the first matching template (highest priority)
		if ( ! empty( $templates ) && isset( $templates[0] ) ) {
			$template = $templates[0];
			$post_id  = $template->wp_id; // This gives us the proper ID

			// If template is customized, use the custom post ID
			if ( ! empty( $post_id ) ) {
				$params = array(
					'postId'   => $post_id,
					'postType' => $fse_type,
					'canvas'   => 'edit',
				);
			} else {
				// Template exists but not customized, use theme/plugin template
				$params = array(
					'postId'   => $template->id, // Full ID like 'theme-slug//template-slug'
					'postType' => $fse_type,
					'canvas'   => 'edit',
				);
			}
		} else {
			// Template doesn't exist yet, just use the slug
			// Site Editor will handle creating it
			$params = array(
				'postId'   => $fse_slug,
				'postType' => $fse_type,
				'canvas'   => 'edit',
			);
		}

		return add_query_arg( $params, admin_url( 'site-editor.php' ) );
	}

	/**
	 * Detect which builder is actually being used on the post.
	 *
	 * Checks post meta to determine the active page builder.
	 *
	 * @param int   $post_id     The post ID.
	 * @param array $environment Environment configuration.
	 * @return string Builder name (elementor, divi, beaver_builder, bricks, gutenberg).
	 */
	private static function detect_builder( $post_id, $environment ) {

		// Check Elementor
		if ( ! empty( $environment['elementor'] ) ) {
			$elementor_mode = get_post_meta( $post_id, '_elementor_edit_mode', true );
			if ( 'builder' === $elementor_mode ) {
				return 'elementor';
			}
		}

		// Check Divi
		if ( ! empty( $environment['divi'] ) ) {
			$divi_enabled = get_post_meta( $post_id, '_et_pb_use_builder', true );
			if ( 'on' === $divi_enabled ) {
				return 'divi';
			}
		}

		// Check Beaver Builder
		if ( ! empty( $environment['beaver_builder'] ) ) {
			$beaver_enabled = get_post_meta( $post_id, '_fl_builder_enabled', true );
			if ( '1' === $beaver_enabled || true === $beaver_enabled ) {
				return 'beaver_builder';
			}
		}

		// Check Bricks
		if ( ! empty( $environment['bricks'] ) ) {
			$bricks_data = get_post_meta( $post_id, '_bricks_page_content_2', true );
			if ( ! empty( $bricks_data ) ) {
				return 'bricks';
			}
		}

		// Check Oxygen (uses custom post type)
		if ( ! empty( $environment['oxygen'] ) ) {
			$post_type = get_post_type( $post_id );
			if ( 'ct_template' === $post_type ) {
				return 'oxygen';
			}
		}

		// Check Brizy
		if ( ! empty( $environment['brizy'] ) ) {
			$brizy_enabled = get_post_meta( $post_id, 'brizy_enabled', true );
			if ( '1' === $brizy_enabled || true === $brizy_enabled ) {
				return 'brizy';
			}
		}

		// Check Thrive Architect
		if ( ! empty( $environment['thrive_architect'] ) ) {
			$tcb_enabled = get_post_meta( $post_id, 'tcb_editor_enabled', true );
			if ( '1' === $tcb_enabled || true === $tcb_enabled ) {
				return 'thrive_architect';
			}
		}

		// Check Breakdance
		if ( ! empty( $environment['breakdance'] ) ) {
			$breakdance_data = get_post_meta( $post_id, '_breakdance_data', true );
			if ( ! empty( $breakdance_data ) ) {
				return 'breakdance';
			}
		}

		// Check WPBakery
		if ( ! empty( $environment['wpbakery'] ) ) {
			$wpb_enabled = get_post_meta( $post_id, '_wpb_vc_js_status', true );
			if ( 'true' === $wpb_enabled ) {
				return 'wpbakery';
			}
		}

		// Default to Gutenberg
		return 'gutenberg';
	}

	/**
	 * Get builder-specific edit URL.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $builder The detected builder name.
	 * @return string Edit URL.
	 */
	private static function get_builder_edit_url( $post_id, $builder ) {

		switch ( $builder ) {
			case 'elementor':
				return add_query_arg(
					array(
						'post'   => $post_id,
						'action' => 'elementor',
					),
					admin_url( 'post.php' )
				);

			case 'divi':
				return add_query_arg(
					array(
						'post'   => $post_id,
						'action' => 'edit',
						'et_fb'  => '1',
					),
					admin_url( 'post.php' )
				);

			case 'beaver_builder':
				return add_query_arg(
					array(
						'post'       => $post_id,
						'fl_builder' => '',
					),
					admin_url( 'post.php' )
				);

			case 'bricks':
				return add_query_arg(
					array(
						'post'   => $post_id,
						'action' => 'bricks_editor',
					),
					admin_url( 'post.php' )
				);

			case 'oxygen':
				return add_query_arg(
					array(
						'page'  => 'ct_template',
						'ct_id' => $post_id,
					),
					admin_url( 'admin.php' )
				);

			case 'brizy':
				return add_query_arg(
					array(
						'post'   => $post_id,
						'action' => 'edit-with-brizy',
					),
					admin_url( 'post.php' )
				);

			case 'thrive_architect':
				return add_query_arg(
					array(
						'post'   => $post_id,
						'tve'    => 'true',
						'action' => 'architect',
					),
					admin_url( 'post.php' )
				);

			case 'breakdance':
				return add_query_arg(
					array(
						'page' => 'breakdance',
						'id'   => $post_id,
					),
					admin_url( 'admin.php' )
				);

			case 'wpbakery':
				return add_query_arg(
					array(
						'post'              => $post_id,
						'action'            => 'edit',
						'classic-editor'    => '',
						'vc_editable'       => 'true',
						'vc_post_type'      => get_post_type( $post_id ),
						'_vcnonce'          => wp_create_nonce( 'vc-admin-nonce' ),
					),
					admin_url( 'post.php' )
				);

			case 'gutenberg':
			default:
				// Standard WordPress editor
				return add_query_arg(
					array(
						'post'   => $post_id,
						'action' => 'edit',
					),
					admin_url( 'post.php' )
				);
		}
	}

	/**
	 * Get detected builder name for display.
	 *
	 * @param int   $post_id     The post ID.
	 * @param array $environment Environment configuration.
	 * @return string Human-readable builder name.
	 */
	public static function get_detected_builder_name( $post_id, $environment ) {
		$builder = self::detect_builder( $post_id, $environment );

		$names = array(
			'elementor'        => 'Elementor',
			'divi'             => 'Divi',
			'beaver_builder'   => 'Beaver Builder',
			'bricks'           => 'Bricks',
			'oxygen'           => 'Oxygen',
			'brizy'            => 'Brizy',
			'thrive_architect' => 'Thrive Architect',
			'breakdance'       => 'Breakdance',
			'wpbakery'         => 'WPBakery',
			'gutenberg'        => 'Gutenberg',
		);

		return isset( $names[ $builder ] ) ? $names[ $builder ] : 'WordPress';
	}
}
