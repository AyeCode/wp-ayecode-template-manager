<?php
/**
 * Divi Theme Builder override provider.
 *
 * @package AyeCode\Templates
 */

namespace AyeCode\Templates\Overrides;

/**
 * Divi's Theme Builder keeps its templates as 'et_template' posts attached to
 * the active 'et_theme_builder' post. Each template lists its display rules as
 * multi-value '_et_use_on' meta ("singular:post_type:gd_place:all",
 * "archive:post_type:gd_place", "archive:all") and points at a body layout
 * through '_et_body_layout_id'. Only templates with an enabled body layout
 * replace the page content.
 */
class Divi implements Provider {

	/**
	 * {@inheritdoc}
	 */
	public function get_slug() {
		return 'divi';
	}

	/**
	 * {@inheritdoc}
	 */
	public function is_available( $environment ) {
		return ! empty( $environment['divi'] ) && function_exists( 'et_theme_builder_get_theme_builder_post_id' );
	}

	/**
	 * {@inheritdoc}
	 */
	public function find( $post_type, $location ) {
		$builder_id = (int) et_theme_builder_get_theme_builder_post_id( true );

		if ( ! $builder_id ) {
			return null;
		}

		$template_ids = get_post_meta( $builder_id, '_et_template', false );

		if ( empty( $template_ids ) ) {
			return null;
		}

		$fallback = null;

		foreach ( $template_ids as $template_id ) {
			$template_id = (int) $template_id;

			if ( ! $template_id || 'publish' !== get_post_status( $template_id ) ) {
				continue;
			}

			$enabled = get_post_meta( $template_id, '_et_enabled', true );
			if ( '' !== $enabled && ! (bool) $enabled ) {
				continue;
			}

			$body_id      = (int) get_post_meta( $template_id, '_et_body_layout_id', true );
			$body_enabled = get_post_meta( $template_id, '_et_body_layout_enabled', true );
			if ( ! $body_id || ( '' !== $body_enabled && ! (bool) $body_enabled ) ) {
				continue;
			}

			$match = array(
				'builder'  => $this->get_slug(),
				'id'       => $template_id,
				'title'    => get_the_title( $template_id ),
				'label'    => sprintf( 'Divi Theme Builder: %s', get_the_title( $template_id ) ),
				'edit_url' => add_query_arg(
					array(
						'et_fb' => '1',
						'et_tb' => '1',
					),
					get_permalink( $body_id )
				),
			);

			$use_on = get_post_meta( $template_id, '_et_use_on', false );

			if ( $this->rules_match( (array) $use_on, $post_type, $location ) ) {
				return $match;
			}

			// The default template applies everywhere nothing more specific matches.
			if ( ! $fallback && (bool) get_post_meta( $template_id, '_et_default', true ) ) {
				$fallback = $match;
			}
		}

		return $fallback;
	}

	/**
	 * Whether Divi "use on" rules cover the post type at the location.
	 *
	 * @param array  $rules     Rule strings.
	 * @param string $post_type Post type.
	 * @param string $location  single or archive.
	 * @return bool
	 */
	private function rules_match( $rules, $post_type, $location ) {
		foreach ( $rules as $rule ) {
			$parts = explode( ':', (string) $rule );

			if ( 'single' === $location && 'singular' === $parts[0] ) {
				if ( isset( $parts[1] ) && 'all' === $parts[1] ) {
					return true;
				}
				if ( isset( $parts[1], $parts[2] ) && 'post_type' === $parts[1] && $parts[2] === $post_type && ( ! isset( $parts[3] ) || 'all' === $parts[3] ) ) {
					return true;
				}
			}

			if ( 'archive' === $location && 'archive' === $parts[0] ) {
				if ( isset( $parts[1] ) && 'all' === $parts[1] ) {
					return true;
				}
				if ( isset( $parts[1], $parts[2] ) && 'post_type' === $parts[1] && $parts[2] === $post_type ) {
					return true;
				}
				if ( isset( $parts[1], $parts[2] ) && 'taxonomy' === $parts[1] && 0 === strpos( $parts[2], $post_type ) ) {
					return true;
				}
			}
		}

		return false;
	}
}
