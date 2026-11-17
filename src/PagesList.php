<?php
/**
 * Pages list integration.
 *
 * Keeps wp-admin > Pages tidy: layout templates (pages that are only storage
 * containers and are never visited directly) are moved out of the "All" view
 * into their own "Templates" view, and every plugin managed page gets a label
 * so it is not deleted by accident.
 *
 * @package AyeCode\Templates
 */

namespace AyeCode\Templates;

/**
 * PagesList class.
 */
class PagesList {

	const QUERY_VAR = 'ayecode_templates';

	/**
	 * Single instance of the class.
	 *
	 * @var PagesList
	 */
	private static $instance = null;

	/**
	 * Cached managed page info.
	 *
	 * @var array|null
	 */
	private $managed = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return PagesList
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Constructor.
	 */
	private function __construct() {
		add_action( 'pre_get_posts', array( $this, 'filter_pages_query' ) );
		add_filter( 'wp_count_posts', array( $this, 'filter_page_counts' ), 10, 2 );
		add_filter( 'views_edit-page', array( $this, 'add_templates_view' ) );
		add_filter( 'display_post_states', array( $this, 'add_post_state' ), 10, 2 );
		add_filter( 'page_row_actions', array( $this, 'add_row_action' ), 10, 2 );
	}

	/**
	 * Whether layout pages should be hidden from the main Pages views.
	 *
	 * @return bool
	 */
	public static function hide_layout_pages() {
		/**
		 * Filter whether layout template pages are hidden from the "All" view of Pages.
		 *
		 * @param bool $hide Default true.
		 */
		return (bool) apply_filters( 'ayecode_hide_layout_pages', true );
	}

	/**
	 * Whether we are on the Pages list screen.
	 *
	 * @return bool
	 */
	private function is_pages_screen() {
		if ( ! is_admin() || ! function_exists( 'get_current_screen' ) ) {
			return false;
		}

		$screen = get_current_screen();

		return $screen && 'edit-page' === $screen->id;
	}

	/**
	 * Whether the "Templates" view is being displayed.
	 *
	 * @return bool
	 */
	private function is_templates_view() {
		return ! empty( $_GET[ self::QUERY_VAR ] ); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
	}

	/**
	 * Collect the pages the registered templates point at.
	 *
	 * @return array Keyed by page ID: array( 'label', 'type', 'product' ).
	 */
	public function get_managed_pages() {
		if ( null !== $this->managed ) {
			return $this->managed;
		}

		$this->managed = array();

		foreach ( Registry::instance()->get_registered_templates() as $product_slug => $product_data ) {
			foreach ( $product_data['items'] as $template_key => $template_data ) {
				$post_id = $template_data['post_id'];

				if ( ! $post_id || isset( $this->managed[ $post_id ] ) ) {
					continue;
				}

				$this->managed[ $post_id ] = array(
					'label'   => $product_data['group_label'] . ': ' . $template_data['title'],
					'type'    => $template_data['type'],
					'product' => $product_slug,
					'key'     => $template_key,
				);
			}
		}

		return $this->managed;
	}

	/**
	 * IDs of layout pages (never visited directly).
	 *
	 * @return int[]
	 */
	private function get_layout_page_ids() {
		$ids = array();

		foreach ( $this->get_managed_pages() as $post_id => $info ) {
			if ( 'layout' === $info['type'] ) {
				$ids[] = (int) $post_id;
			}
		}

		return $ids;
	}

	/**
	 * Exclude layout pages from the main views, or show only managed pages in
	 * the Templates view.
	 *
	 * @param \WP_Query $query The query.
	 */
	public function filter_pages_query( $query ) {
		if ( ! $query->is_main_query() || ! $this->is_pages_screen() || 'page' !== $query->get( 'post_type' ) ) {
			return;
		}

		if ( $this->is_templates_view() ) {
			$ids = array_map( 'intval', array_keys( $this->get_managed_pages() ) );

			// An empty post__in is ignored by WP_Query, which would list everything.
			$query->set( 'post__in', $ids ? $ids : array( 0 ) );

			return;
		}

		// Trash is never filtered so a trashed template page can still be restored.
		if ( ! self::hide_layout_pages() || 'trash' === $query->get( 'post_status' ) ) {
			return;
		}

		$layout_ids = $this->get_layout_page_ids();

		if ( empty( $layout_ids ) ) {
			return;
		}

		$existing = (array) $query->get( 'post__not_in' );

		$query->set( 'post__not_in', array_values( array_unique( array_merge( $existing, $layout_ids ) ) ) );
	}

	/**
	 * Take the hidden layout pages out of the view counts.
	 *
	 * @param object $counts Status counts.
	 * @param string $type   Post type.
	 * @return object
	 */
	public function filter_page_counts( $counts, $type ) {
		if ( 'page' !== $type || ! self::hide_layout_pages() || ! $this->is_pages_screen() ) {
			return $counts;
		}

		foreach ( $this->get_layout_page_ids() as $post_id ) {
			$status = get_post_status( $post_id );

			if ( $status && 'trash' !== $status && isset( $counts->$status ) && $counts->$status > 0 ) {
				--$counts->$status;
			}
		}

		return $counts;
	}

	/**
	 * Add a "Templates" view link next to All | Published | Drafts.
	 *
	 * @param array $views View links.
	 * @return array
	 */
	public function add_templates_view( $views ) {
		$count = 0;

		foreach ( array_keys( $this->get_managed_pages() ) as $post_id ) {
			$status = get_post_status( $post_id );

			if ( $status && 'trash' !== $status ) {
				++$count;
			}
		}

		// The "Mine" view counts with its own query, so take the hidden layout
		// pages out of it here to keep the numbers consistent.
		if ( isset( $views['mine'] ) && self::hide_layout_pages() ) {
			$mine_hidden = 0;
			foreach ( $this->get_layout_page_ids() as $post_id ) {
				$post = get_post( $post_id );
				if ( $post && 'trash' !== $post->post_status && get_current_user_id() === (int) $post->post_author ) {
					++$mine_hidden;
				}
			}

			if ( $mine_hidden && preg_match( '/\((\d+)\)/', $views['mine'], $matches ) ) {
				$views['mine'] = str_replace( '(' . $matches[1] . ')', '(' . max( 0, (int) $matches[1] - $mine_hidden ) . ')', $views['mine'] );
			}
		}

		if ( ! $count ) {
			return $views;
		}

		$url = add_query_arg(
			array(
				'post_type'     => 'page',
				self::QUERY_VAR => '1',
			),
			admin_url( 'edit.php' )
		);

		$views[ self::QUERY_VAR ] = sprintf(
			'<a href="%1$s"%2$s>%3$s <span class="count">(%4$d)</span></a>',
			esc_url( $url ),
			$this->is_templates_view() ? ' class="current" aria-current="page"' : '',
			esc_html__( 'Templates', 'ayecode-connect' ),
			$count
		);

		return $views;
	}

	/**
	 * Label managed pages in the list, WooCommerce style.
	 *
	 * @param array    $post_states Post states.
	 * @param \WP_Post $post        The post.
	 * @return array
	 */
	public function add_post_state( $post_states, $post ) {
		if ( ! $post || 'page' !== $post->post_type ) {
			return $post_states;
		}

		$managed = $this->get_managed_pages();

		if ( isset( $managed[ $post->ID ] ) ) {
			$post_states['ayecode_template'] = esc_html( $managed[ $post->ID ]['label'] );
		}

		return $post_states;
	}

	/**
	 * Link managed pages back to the Templates screen.
	 *
	 * @param array    $actions Row actions.
	 * @param \WP_Post $post    The post.
	 * @return array
	 */
	public function add_row_action( $actions, $post ) {
		$managed = $this->get_managed_pages();

		if ( ! $post || ! isset( $managed[ $post->ID ] ) ) {
			return $actions;
		}

		$actions['ayecode_template'] = sprintf(
			'<a href="%s">%s</a>',
			esc_url( Settings::get_page_url() ),
			esc_html__( 'Template settings', 'ayecode-connect' )
		);

		// Layout pages have no meaningful front end, so drop the View link.
		if ( 'layout' === $managed[ $post->ID ]['type'] ) {
			unset( $actions['view'] );
		}

		return $actions;
	}
}
