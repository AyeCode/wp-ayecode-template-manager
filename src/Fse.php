<?php
/**
 * FSE (block theme) template helpers.
 *
 * Wraps the core block template API so the rest of the package can ask simple
 * questions: does this template exist, has the user customized it, where is it
 * edited, and how is it reset.
 *
 * @package AyeCode\Templates
 */

namespace AyeCode\Templates;

/**
 * Fse class.
 */
class Fse {

	/**
	 * Per-request cache of looked up templates keyed by "type//slug".
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Find a block template (or template part) by slug.
	 *
	 * Returns the highest priority match, which is the user customized version
	 * when one exists, otherwise the theme or plugin file version.
	 *
	 * @param string $slug Template slug, e.g. 'gd-single'.
	 * @param string $type 'wp_template' or 'wp_template_part'.
	 * @return \WP_Block_Template|null
	 */
	public static function find_template( $slug, $type = 'wp_template' ) {
		if ( empty( $slug ) || ! function_exists( 'get_block_templates' ) ) {
			return null;
		}

		$type      = 'wp_template_part' === $type ? 'wp_template_part' : 'wp_template';
		$cache_key = $type . '//' . $slug;

		if ( array_key_exists( $cache_key, self::$cache ) ) {
			return self::$cache[ $cache_key ];
		}

		$templates = get_block_templates( array( 'slug__in' => array( $slug ) ), $type );

		self::$cache[ $cache_key ] = ! empty( $templates[0] ) ? $templates[0] : null;

		return self::$cache[ $cache_key ];
	}

	/**
	 * Whether the template has a user customized copy saved in the database.
	 *
	 * @param \WP_Block_Template|null $template Template object.
	 * @return bool
	 */
	public static function is_customized( $template ) {
		return $template && 'custom' === $template->source && ! empty( $template->wp_id );
	}

	/**
	 * Get the Site Editor URL for a template.
	 *
	 * @param string $slug Template slug.
	 * @param string $type Template type.
	 * @return string
	 */
	public static function get_edit_url( $slug, $type = 'wp_template' ) {
		global $wp_version;

		$type     = 'wp_template_part' === $type ? 'wp_template_part' : 'wp_template';
		$template = self::find_template( $slug, $type );

		// The Site Editor addresses templates by their "theme//slug" ID whether or
		// not a customized copy exists. Fall back to "stylesheet//slug" when the
		// template is not registered yet so the editor can still create it.
		$template_id = $template && ! empty( $template->id ) ? $template->id : get_stylesheet() . '//' . $slug;

		// WordPress 6.8 moved the Site Editor to path based routing.
		if ( version_compare( $wp_version, '6.8', '>=' ) ) {
			return add_query_arg(
				array(
					'p'      => '/' . $type . '/' . $template_id,
					'canvas' => 'edit',
				),
				admin_url( 'site-editor.php' )
			);
		}

		return add_query_arg(
			array(
				'postId'   => $template_id,
				'postType' => $type,
				'canvas'   => 'edit',
			),
			admin_url( 'site-editor.php' )
		);
	}

	/**
	 * Reset a template to its file version by deleting the customized copy.
	 *
	 * This mirrors what the Site Editor does when a user clicks "Reset".
	 *
	 * @param string $slug Template slug.
	 * @param string $type Template type.
	 * @return bool True when a customized copy was removed.
	 */
	public static function reset( $slug, $type = 'wp_template' ) {
		$template = self::find_template( $slug, $type );

		if ( ! self::is_customized( $template ) ) {
			return false;
		}

		$deleted = wp_delete_post( $template->wp_id, true );

		self::clear_cache();

		return ! empty( $deleted );
	}

	/**
	 * Create a customized template post from an existing template's content.
	 *
	 * Used to create per post type overrides such as 'single-gd_place' on block
	 * themes. The new template belongs to the active theme so the Site Editor
	 * lists it under the theme's templates.
	 *
	 * @param string $slug    New template slug, e.g. 'single-gd_place'.
	 * @param string $title   Template title.
	 * @param string $content Block markup for the template.
	 * @param string $type    Template type.
	 * @return int|\WP_Error New post ID.
	 */
	public static function create_template( $slug, $title, $content, $type = 'wp_template' ) {
		$type = 'wp_template_part' === $type ? 'wp_template_part' : 'wp_template';

		$existing = self::find_template( $slug, $type );
		if ( self::is_customized( $existing ) ) {
			return (int) $existing->wp_id;
		}

		$post_id = wp_insert_post(
			array(
				'post_type'    => $type,
				'post_name'    => $slug,
				'post_title'   => $title,
				'post_content' => $content,
				'post_status'  => 'publish',
				'tax_input'    => array(
					'wp_theme' => array( get_stylesheet() ),
				),
			),
			true
		);

		if ( is_wp_error( $post_id ) ) {
			return $post_id;
		}

		// tax_input is ignored when the current user cannot assign terms, so set
		// the theme term explicitly to be safe.
		wp_set_post_terms( $post_id, array( get_stylesheet() ), 'wp_theme' );

		self::clear_cache();

		return (int) $post_id;
	}

	/**
	 * Get the raw block content of a template.
	 *
	 * @param string $slug Template slug.
	 * @param string $type Template type.
	 * @return string
	 */
	public static function get_content( $slug, $type = 'wp_template' ) {
		$template = self::find_template( $slug, $type );

		return $template && isset( $template->content ) ? (string) $template->content : '';
	}

	/**
	 * Clear the per-request lookup cache.
	 */
	public static function clear_cache() {
		self::$cache = array();
	}
}
