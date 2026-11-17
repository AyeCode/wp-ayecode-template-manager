<?php
/**
 * Theme builder override lookup.
 *
 * @package AyeCode\Templates
 */

namespace AyeCode\Templates\Overrides;

/**
 * Manager class.
 *
 * Holds the registered providers and answers "which builder template, if any,
 * takes over rendering for this post type and location".
 */
class Manager {

	/**
	 * Per-request result cache keyed by "post_type|location".
	 *
	 * @var array
	 */
	private static $cache = array();

	/**
	 * Get the available providers for the environment.
	 *
	 * @param array $environment Environment array.
	 * @return Provider[]
	 */
	public static function get_providers( $environment ) {
		$providers = array(
			new ElementorPro(),
			new Bricks(),
			new Divi(),
		);

		/**
		 * Filter the theme builder override providers.
		 *
		 * @param Provider[] $providers   Provider instances.
		 * @param array      $environment Environment array.
		 */
		$providers = apply_filters( 'ayecode_template_override_providers', $providers, $environment );

		$available = array();
		foreach ( $providers as $provider ) {
			if ( $provider instanceof Provider && $provider->is_available( $environment ) ) {
				$available[] = $provider;
			}
		}

		return $available;
	}

	/**
	 * Find an override for a single post type.
	 *
	 * @param string $post_type   Post type slug.
	 * @param string $location    'single' or 'archive'.
	 * @param array  $environment Environment array.
	 * @return array|null
	 */
	public static function find( $post_type, $location, $environment ) {
		if ( empty( $post_type ) || ! in_array( $location, array( 'single', 'archive' ), true ) ) {
			return null;
		}

		$cache_key = $post_type . '|' . $location;

		if ( array_key_exists( $cache_key, self::$cache ) ) {
			return self::$cache[ $cache_key ];
		}

		$result = null;

		foreach ( self::get_providers( $environment ) as $provider ) {
			$found = $provider->find( $post_type, $location );

			if ( ! empty( $found ) ) {
				$result = $found;
				break;
			}
		}

		self::$cache[ $cache_key ] = $result;

		return $result;
	}

	/**
	 * Find an override for a template row.
	 *
	 * Rows tied to one post type check that type. Global rows check every post
	 * type the product registered and report the first match, naming the type.
	 *
	 * @param array $template_data Validated template data.
	 * @param array $group_data    Validated product group data (for 'variants').
	 * @param array $environment   Environment array.
	 * @return array|null
	 */
	public static function find_for_template( $template_data, $group_data, $environment ) {
		if ( empty( $template_data['location'] ) || ! empty( $environment['block_theme'] ) ) {
			return null;
		}

		if ( ! empty( $template_data['post_type'] ) ) {
			return self::find( $template_data['post_type'], $template_data['location'], $environment );
		}

		$post_types = ! empty( $group_data['variants']['post_types'] ) ? $group_data['variants']['post_types'] : array();

		foreach ( $post_types as $post_type => $label ) {
			$found = self::find( $post_type, $template_data['location'], $environment );

			if ( $found ) {
				$found['post_type']       = $post_type;
				$found['post_type_label'] = $label;

				return $found;
			}
		}

		return null;
	}

	/**
	 * Clear the per-request cache.
	 */
	public static function clear_cache() {
		self::$cache = array();
	}
}
