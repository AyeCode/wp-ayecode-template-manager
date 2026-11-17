<?php
/**
 * Bricks Builder template override provider.
 *
 * @package AyeCode\Templates
 */

namespace AyeCode\Templates\Overrides;

/**
 * Bricks stores templates in the 'bricks_template' post type. The template
 * type ('content' for singles, 'archive' for archives) is in
 * '_bricks_template_type' and the display conditions are in
 * '_bricks_template_settings' under 'templateConditions'.
 */
class Bricks implements Provider {

	/**
	 * {@inheritdoc}
	 */
	public function get_slug() {
		return 'bricks';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available( $environment ) {
		return ! empty( $environment['bricks'] );
	}

	/**
	 * {@inheritdoc}
	 */
	public function find( $post_type, $location ) {
		$type = 'archive' === $location ? 'archive' : 'content';

		$templates = get_posts(
			array(
				'post_type'      => 'bricks_template',
				'post_status'    => 'publish',
				'posts_per_page' => 50,
				'fields'         => 'ids',
				'no_found_rows'  => true,
				'meta_key'       => '_bricks_template_type', // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_key
				'meta_value'     => $type, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_value
			)
		);

		foreach ( $templates as $template_id ) {
			$settings   = get_post_meta( $template_id, '_bricks_template_settings', true );
			$conditions = ! empty( $settings['templateConditions'] ) && is_array( $settings['templateConditions'] ) ? $settings['templateConditions'] : array();

			if ( $this->conditions_match( $conditions, $post_type, $location ) ) {
				return array(
					'builder'  => $this->get_slug(),
					'id'       => $template_id,
					'title'    => get_the_title( $template_id ),
					'label'    => sprintf( 'Bricks: %s', get_the_title( $template_id ) ),
					'edit_url' => add_query_arg( 'bricks', 'run', get_permalink( $template_id ) ),
				);
			}
		}

		return null;
	}

	/**
	 * Whether a set of Bricks conditions covers the post type at the location.
	 *
	 * @param array  $conditions Condition arrays.
	 * @param string $post_type  Post type.
	 * @param string $location   single or archive.
	 * @return bool
	 */
	private function conditions_match( $conditions, $post_type, $location ) {
		$matched = false;

		foreach ( $conditions as $condition ) {
			if ( ! is_array( $condition ) || ! empty( $condition['exclude'] ) ) {
				continue;
			}

			$main = isset( $condition['main'] ) ? $condition['main'] : '';

			if ( 'any' === $main ) {
				$matched = true;
				continue;
			}

			if ( 'single' === $location && 'postType' === $main ) {
				$types = isset( $condition['postType'] ) ? (array) $condition['postType'] : array();
				if ( in_array( $post_type, $types, true ) ) {
					$matched = true;
				}
			}

			if ( 'archive' === $location && 'archiveType' === $main ) {
				$archive_types = isset( $condition['archiveType'] ) ? (array) $condition['archiveType'] : array();
				$archive_cpts  = isset( $condition['archivePostTypes'] ) ? (array) $condition['archivePostTypes'] : array();

				if ( in_array( 'any', $archive_types, true ) ) {
					$matched = true;
				} elseif ( in_array( 'postType', $archive_types, true ) && ( empty( $archive_cpts ) || in_array( $post_type, $archive_cpts, true ) ) ) {
					$matched = true;
				}
			}
		}

		return $matched;
	}
}
