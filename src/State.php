<?php
/**
 * Template state derivation.
 *
 * Template state is never stored as a post status. It is worked out on demand
 * from what actually exists: a customized block template, a page whose content
 * differs from the plugin default, a missing page, and so on.
 *
 * @package AyeCode\Templates
 */

namespace AyeCode\Templates;

/**
 * State class.
 */
class State {

	const META_DEFAULT_HASH = '_ayecode_template_default_hash';

	const STATE_DEFAULT    = 'default';
	const STATE_CUSTOMIZED = 'customized';
	const STATE_MISSING    = 'missing';
	const STATE_DRAFT      = 'draft';

	/**
	 * Work out the state of a registered template.
	 *
	 * @param string $template_key  Template key.
	 * @param array  $template_data Validated template data from the Registry.
	 * @param array  $environment   Environment array.
	 * @return string One of the STATE_* constants.
	 */
	public static function get( $template_key, $template_data, $environment ) {
		$uses_fse = ! empty( $environment['block_theme'] ) && ! empty( $template_data['fse_slug'] );
		$post_id  = ! empty( $template_data['post_id'] ) ? absint( $template_data['post_id'] ) : 0;

		if ( $uses_fse ) {
			// A page-type template still needs its page to exist for the URL,
			// even though the block template supplies the design.
			if ( 'page' === $template_data['type'] ) {
				$page_state = self::get_page_status_state( $post_id );
				if ( self::STATE_MISSING === $page_state || self::STATE_DRAFT === $page_state ) {
					return $page_state;
				}
			}

			$template = Fse::find_template( $template_data['fse_slug'], $template_data['fse_type'] );

			if ( ! $template ) {
				return self::STATE_MISSING;
			}

			return Fse::is_customized( $template ) ? self::STATE_CUSTOMIZED : self::STATE_DEFAULT;
		}

		$page_state = self::get_page_status_state( $post_id );
		if ( self::STATE_DEFAULT !== $page_state ) {
			return $page_state;
		}

		return self::is_page_default( $post_id, $template_key, $template_data ) ? self::STATE_DEFAULT : self::STATE_CUSTOMIZED;
	}

	/**
	 * State derived purely from the page post's existence and status.
	 *
	 * @param int $post_id Page ID.
	 * @return string missing, draft or default (meaning "exists and published").
	 */
	private static function get_page_status_state( $post_id ) {
		$status = $post_id ? get_post_status( $post_id ) : false;

		if ( ! $status || 'trash' === $status || 'auto-draft' === $status ) {
			return self::STATE_MISSING;
		}

		if ( 'publish' !== $status && 'private' !== $status ) {
			return self::STATE_DRAFT;
		}

		return self::STATE_DEFAULT;
	}

	/**
	 * Whether a page's content still matches the plugin default.
	 *
	 * Two checks, either of which counts as default:
	 * 1. The content matches the default the host plugin supplies right now.
	 * 2. The content matches the hash stamped when the page was created or last
	 *    restored. This keeps untouched pages "default" even after the plugin
	 *    changes its default markup in an update.
	 *
	 * When check 1 passes and no stamp exists yet, the stamp is written so the
	 * page survives future default changes.
	 *
	 * @param int    $post_id       Page ID.
	 * @param string $template_key  Template key.
	 * @param array  $template_data Template data.
	 * @return bool
	 */
	public static function is_page_default( $post_id, $template_key, $template_data ) {
		$content = get_post_field( 'post_content', $post_id, 'raw' );
		$current = self::hash( $content );

		$default = self::get_default_content( $post_id, $template_key, $template_data );
		if ( '' !== $default && self::hash( $default ) === $current ) {
			if ( ! metadata_exists( 'post', $post_id, self::META_DEFAULT_HASH ) ) {
				update_post_meta( $post_id, self::META_DEFAULT_HASH, $current );
			}

			return true;
		}

		$stamped = get_post_meta( $post_id, self::META_DEFAULT_HASH, true );

		return ! empty( $stamped ) && $stamped === $current;
	}

	/**
	 * Get the default content for a template from the host plugin.
	 *
	 * @param int    $post_id       Page ID (0 when the page is missing).
	 * @param string $template_key  Template key.
	 * @param array  $template_data Template data.
	 * @return string
	 */
	public static function get_default_content( $post_id, $template_key, $template_data ) {
		/**
		 * Filter the default content for a template.
		 *
		 * Host plugins return the markup a freshly installed template contains.
		 * Used for state detection, restore and recreate.
		 *
		 * @param string $content       Default content, empty when unknown.
		 * @param int    $post_id       Page ID, 0 when missing.
		 * @param string $template_key  Template key.
		 * @param array  $template_data Validated template data.
		 */
		$content = apply_filters( 'ayecode_template_default_content', '', $post_id, $template_key, $template_data );

		return is_string( $content ) ? $content : '';
	}

	/**
	 * Hash content after normalising it so editor round trips do not count as edits.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function hash( $content ) {
		return md5( self::normalize( $content ) );
	}

	/**
	 * Normalise content for comparison.
	 *
	 * Block markup is re-serialised so attribute order and whitespace inside
	 * comments do not matter, then all whitespace runs are collapsed.
	 *
	 * @param string $content Post content.
	 * @return string
	 */
	public static function normalize( $content ) {
		$content = (string) $content;

		if ( function_exists( 'parse_blocks' ) && function_exists( 'serialize_blocks' ) && false !== strpos( $content, '<!-- wp:' ) ) {
			$blocks  = parse_blocks( $content );
			$content = serialize_blocks( $blocks );
		}

		$content = str_replace( array( "\r\n", "\r" ), "\n", $content );
		$content = preg_replace( '/\s+/', ' ', $content );

		return trim( $content );
	}

	/**
	 * Stamp a page with the hash of its default content.
	 *
	 * @param int         $post_id Page ID.
	 * @param string|null $content Content to hash. Defaults to the page's current content.
	 */
	public static function stamp( $post_id, $content = null ) {
		if ( null === $content ) {
			$content = get_post_field( 'post_content', $post_id, 'raw' );
		}

		update_post_meta( $post_id, self::META_DEFAULT_HASH, self::hash( $content ) );
	}

	/**
	 * Human readable labels for the states.
	 *
	 * @return array
	 */
	public static function labels() {
		return array(
			self::STATE_DEFAULT    => __( 'Default', 'ayecode-connect' ),
			self::STATE_CUSTOMIZED => __( 'Customized', 'ayecode-connect' ),
			self::STATE_MISSING    => __( 'Missing', 'ayecode-connect' ),
			self::STATE_DRAFT      => __( 'Draft', 'ayecode-connect' ),
		);
	}
}
