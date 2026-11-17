<?php
/**
 * Router Class
 *
 * Answers "what renders this template and where is it edited" across block
 * themes, classic themes and page builders.
 *
 * @package AyeCode\Templates
 */

namespace AyeCode\Templates;

/**
 * Router class.
 */
class Router {

	/**
	 * Get the edit URL for a template.
	 *
	 * Priority order:
	 * 1. A theme builder template that overrides rendering (passed in as $override).
	 * 2. The Site Editor template on block themes.
	 * 3. The page, opened in whichever builder built it.
	 *
	 * @param int        $post_id       The page ID (used for classic themes).
	 * @param array      $template_data Template data from Registry.
	 * @param array      $environment   Environment configuration.
	 * @param array|null $override      Theme builder override from Overrides\Manager, if any.
	 * @return string The edit URL.
	 */
	public static function get_edit_url( $post_id, $template_data, $environment, $override = null ) {
		if ( ! empty( $override['edit_url'] ) ) {
			return $override['edit_url'];
		}

		if ( ! empty( $environment['block_theme'] ) && ! empty( $template_data['fse_slug'] ) ) {
			return Fse::get_edit_url( $template_data['fse_slug'], $template_data['fse_type'] );
		}

		if ( empty( $post_id ) || ! get_post( $post_id ) ) {
			return '';
		}

		$detected_builder = self::detect_builder( $post_id, $environment );

		return self::get_builder_edit_url( $post_id, $detected_builder );
	}

	/**
	 * Describe what renders a template.
	 *
	 * @param int        $post_id       The page ID.
	 * @param array      $template_data Template data from Registry.
	 * @param array      $environment   Environment configuration.
	 * @param array|null $override      Theme builder override, if any.
	 * @return array Array with 'key' and 'label'.
	 */
	public static function get_source( $post_id, $template_data, $environment, $override = null ) {
		if ( ! empty( $override ) ) {
			return array(
				'key'   => $override['builder'],
				'label' => $override['label'],
			);
		}

		if ( ! empty( $environment['block_theme'] ) && ! empty( $template_data['fse_slug'] ) ) {
			return array(
				'key'   => 'site_editor',
				'label' => __( 'Site Editor', 'ayecode-connect' ),
			);
		}

		if ( empty( $post_id ) || ! get_post( $post_id ) ) {
			return array(
				'key'   => '',
				'label' => '',
			);
		}

		$builder = self::detect_builder( $post_id, $environment );

		return array(
			'key'   => $builder,
			'label' => self::get_builder_label( $builder ),
		);
	}

	/**
	 * Detect which builder is actually being used on the post.
	 *
	 * Checks post meta to determine the active page builder.
	 *
	 * @param int   $post_id     The post ID.
	 * @param array $environment Environment configuration.
	 * @return string Builder key.
	 */
	public static function detect_builder( $post_id, $environment ) {

		if ( ! empty( $environment['elementor'] ) && 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true ) ) {
			return 'elementor';
		}

		if ( ! empty( $environment['divi'] ) && 'on' === get_post_meta( $post_id, '_et_pb_use_builder', true ) ) {
			return 'divi';
		}

		if ( ! empty( $environment['beaver_builder'] ) ) {
			$beaver_enabled = get_post_meta( $post_id, '_fl_builder_enabled', true );
			if ( '1' === $beaver_enabled || true === $beaver_enabled ) {
				return 'beaver_builder';
			}
		}

		if ( ! empty( $environment['bricks'] ) && get_post_meta( $post_id, '_bricks_page_content_2', true ) ) {
			return 'bricks';
		}

		if ( ! empty( $environment['oxygen'] ) && 'ct_template' === get_post_type( $post_id ) ) {
			return 'oxygen';
		}

		if ( ! empty( $environment['brizy'] ) ) {
			$brizy_enabled = get_post_meta( $post_id, 'brizy_enabled', true );
			if ( '1' === $brizy_enabled || true === $brizy_enabled ) {
				return 'brizy';
			}
		}

		if ( ! empty( $environment['thrive_architect'] ) ) {
			$tcb_enabled = get_post_meta( $post_id, 'tcb_editor_enabled', true );
			if ( '1' === $tcb_enabled || true === $tcb_enabled ) {
				return 'thrive_architect';
			}
		}

		if ( ! empty( $environment['breakdance'] ) && get_post_meta( $post_id, '_breakdance_data', true ) ) {
			return 'breakdance';
		}

		if ( ! empty( $environment['wpbakery'] ) && 'true' === get_post_meta( $post_id, '_wpb_vc_js_status', true ) ) {
			return 'wpbakery';
		}

		// The block editor unless the site has turned it off.
		if ( ! empty( $environment['gutenberg'] ) && ! class_exists( 'Classic_Editor' ) ) {
			return 'gutenberg';
		}

		return 'classic_editor';
	}

	/**
	 * Get builder-specific edit URL.
	 *
	 * @param int    $post_id The post ID.
	 * @param string $builder The detected builder name.
	 * @return string Edit URL.
	 */
	public static function get_builder_edit_url( $post_id, $builder ) {

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
				return add_query_arg( 'fl_builder', '', get_permalink( $post_id ) );

			case 'bricks':
				return add_query_arg( 'bricks', 'run', get_permalink( $post_id ) );

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
						'post'           => $post_id,
						'action'         => 'edit',
						'classic-editor' => '',
						'vc_editable'    => 'true',
						'vc_post_type'   => get_post_type( $post_id ),
						'_vcnonce'       => wp_create_nonce( 'vc-admin-nonce' ),
					),
					admin_url( 'post.php' )
				);

			case 'gutenberg':
			case 'classic_editor':
			default:
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
	 * Human readable label for a builder key.
	 *
	 * @param string $builder Builder key.
	 * @return string
	 */
	public static function get_builder_label( $builder ) {
		$names = array(
			'elementor'        => 'Elementor',
			'elementor_pro'    => 'Elementor Pro',
			'divi'             => 'Divi',
			'beaver_builder'   => 'Beaver Builder',
			'bricks'           => 'Bricks',
			'oxygen'           => 'Oxygen',
			'brizy'            => 'Brizy',
			'thrive_architect' => 'Thrive Architect',
			'breakdance'       => 'Breakdance',
			'wpbakery'         => 'WPBakery',
			'gutenberg'        => __( 'Block Editor', 'ayecode-connect' ),
			'classic_editor'   => __( 'Classic Editor', 'ayecode-connect' ),
			'site_editor'      => __( 'Site Editor', 'ayecode-connect' ),
		);

		return isset( $names[ $builder ] ) ? $names[ $builder ] : 'WordPress';
	}

	/**
	 * Get detected builder name for display.
	 *
	 * Kept for backwards compatibility with host plugins.
	 *
	 * @param int   $post_id     The post ID.
	 * @param array $environment Environment configuration.
	 * @return string Human-readable builder name.
	 */
	public static function get_detected_builder_name( $post_id, $environment ) {
		return self::get_builder_label( self::detect_builder( $post_id, $environment ) );
	}
}
