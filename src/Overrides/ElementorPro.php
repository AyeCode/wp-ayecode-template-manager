<?php
/**
 * Elementor Pro Theme Builder override provider.
 *
 * @package AyeCode\Templates
 */

namespace AyeCode\Templates\Overrides;

/**
 * Elementor Pro stores theme builder templates in the 'elementor_library'
 * post type. The document type lives in '_elementor_template_type' and the
 * display conditions in '_elementor_conditions' as strings of the form
 * "include/singular/gd_place" or "include/archive/gd_place_archive".
 */
class ElementorPro implements Provider {

	/**
	 * {@inheritdoc}
	 */
	public function get_slug() {
		return 'elementor_pro';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available( $environment ) {
		return ! empty( $environment['elementor_pro'] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function find( $post_type, $location ) {
		$types = 'archive' === $location ? array( 'archive' ) : array( 'single', 'single-post' );

		$templates = get_posts(
			array(
				'post_type'      => 'elementor_library',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_query'     => array( // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
					array(
						'key'     => '_elementor_template_type',
						'value'   => $types,
						'compare' => 'IN',
					),
				),
			)
		);

		foreach ( $templates as $template_id ) {
			$conditions = get_post_meta( $template_id, '_elementor_conditions', true );

			if ( empty( $conditions ) || ! is_array( $conditions ) ) {
				continue;
			}

			if ( $this->conditions_match( $conditions, $post_type, $location ) ) {
				return array(
					'builder'  => $this->get_slug(),
					'id'       => $template_id,
					'title'    => get_the_title( $template_id ),
					'label'    => sprintf( 'Elementor Pro: %s', get_the_title( $template_id ) ),
					'edit_url' => add_query_arg(
						array(
							'post'   => $template_id,
							'action' => 'elementor',
						),
						admin_url( 'post.php' )
					),
				);
			}
		}

		return null;
	}

	/**
	 * Whether a set of Elementor conditions covers the post type at the location.
	 *
	 * @param array  $conditions Condition strings.
	 * @param string $post_type  Post type.
	 * @param string $location   single or archive.
	 * @return bool
	 */
	private function conditions_match( $conditions, $post_type, $location ) {
		$matched = false;

		foreach ( $conditions as $condition ) {
			$parts = explode( '/', (string) $condition );
			$type  = isset( $parts[0] ) ? $parts[0] : '';
			$name  = isset( $parts[1] ) ? $parts[1] : '';
			$sub   = isset( $parts[2] ) ? $parts[2] : '';
			$id    = isset( $parts[3] ) ? $parts[3] : '';

			if ( 'include' !== $type ) {
				continue;
			}

			if ( 'general' === $name ) {
				$matched = true;
				continue;
			}

			if ( 'single' === $location && 'singular' === $name ) {
				// "include/singular" is every singular; "include/singular/gd_place" is
				// the whole post type. A specific post ID does not replace the type.
				if ( '' === $sub || ( $sub === $post_type && '' === $id ) ) {
					$matched = true;
				}
			}

			if ( 'archive' === $location && 'archive' === $name ) {
				if ( '' === $sub || $sub === $post_type . '_archive' || 0 === strpos( $sub, $post_type ) ) {
					$matched = true;
				}
			}
		}

		return $matched;
	}
}
