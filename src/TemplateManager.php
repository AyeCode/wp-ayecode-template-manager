<?php
/**
 * Template Manager Class
 *
 * Turns registered templates into the rows the admin screen displays.
 *
 * @package AyeCode\Templates
 */

namespace AyeCode\Templates;

/**
 * TemplateManager class.
 */
class TemplateManager {

	/**
	 * Single instance of the class.
	 *
	 * @var TemplateManager
	 */
	private static $instance = null;

	/**
	 * Get the singleton instance.
	 *
	 * @return TemplateManager
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
	}

	/**
	 * Build a row ID the admin screen can hand back to us.
	 *
	 * Page IDs are not used because a row can exist without a page (missing,
	 * or block theme only).
	 *
	 * @param string $product_slug Product slug.
	 * @param string $template_key Template key.
	 * @return string
	 */
	public static function make_row_id( $product_slug, $template_key ) {
		return $product_slug . ':' . $template_key;
	}

	/**
	 * Resolve a row ID back to its registered template.
	 *
	 * @param string $row_id Row ID from the admin screen.
	 * @return array|false Array with 'product', 'key', 'data' and 'group', or false.
	 */
	public static function resolve_row_id( $row_id ) {
		$row_id = (string) $row_id;

		if ( false === strpos( $row_id, ':' ) ) {
			return false;
		}

		list( $product_slug, $template_key ) = explode( ':', $row_id, 2 );

		$registry = Registry::instance();
		$group    = $registry->get_product_templates( $product_slug );
		$template = $registry->get_template( $product_slug, $template_key );

		if ( ! $group || ! $template ) {
			return false;
		}

		return array(
			'product' => $product_slug,
			'key'     => $template_key,
			'data'    => $template,
			'group'   => $group,
		);
	}

	/**
	 * Get templates formatted for list table display.
	 *
	 * @param array $args Query arguments: 'status' (all|customized|attention) and 'product'.
	 * @return array Templates data with items and counts.
	 */
	public function get_templates_for_display( $args = array() ) {
		$args = wp_parse_args(
			$args,
			array(
				'status'  => 'all',
				'product' => '',
			)
		);

		$registry    = Registry::instance();
		$environment = Environment::get();

		$items  = array();
		$counts = array(
			'all'        => 0,
			'customized' => 0,
			'attention'  => 0,
		);

		foreach ( $registry->get_registered_templates() as $product_slug => $product_data ) {
			if ( ! empty( $args['product'] ) && $args['product'] !== $product_slug ) {
				continue;
			}

			foreach ( $product_data['items'] as $template_key => $template_data ) {
				$row = $this->build_row( $product_slug, $product_data, $template_key, $template_data, $environment );

				++$counts['all'];
				if ( State::STATE_CUSTOMIZED === $row['state_key'] ) {
					++$counts['customized'];
				}
				if ( $row['attention'] ) {
					++$counts['attention'];
				}

				if ( 'customized' === $args['status'] && State::STATE_CUSTOMIZED !== $row['state_key'] ) {
					continue;
				}

				if ( 'attention' === $args['status'] && ! $row['attention'] ) {
					continue;
				}

				$items[] = $row;
			}
		}

		return array(
			'items'  => $items,
			'counts' => $counts,
		);
	}

	/**
	 * Build one display row.
	 *
	 * @param string $product_slug  Product slug.
	 * @param array  $product_data  Validated product group.
	 * @param string $template_key  Template key.
	 * @param array  $template_data Validated template data.
	 * @param array  $environment   Environment array.
	 * @return array
	 */
	public function build_row( $product_slug, $product_data, $template_key, $template_data, $environment ) {
		$post_id  = $template_data['post_id'];
		$post     = $post_id ? get_post( $post_id ) : null;
		$is_page  = 'page' === $template_data['type'];
		$uses_fse = ! empty( $environment['block_theme'] ) && ! empty( $template_data['fse_slug'] );

		$override = Overrides\Manager::find_for_template( $template_data, $product_data, $environment );
		$state    = State::get( $template_key, $template_data, $environment );
		$source   = Router::get_source( $post_id, $template_data, $environment, $override );
		$edit_url = State::STATE_MISSING === $state ? '' : Router::get_edit_url( $post_id, $template_data, $environment, $override );

		$page_published = $post && in_array( $post->post_status, array( 'publish', 'private' ), true );
		$view_url       = $is_page && $page_published ? get_permalink( $post_id ) : '';
		$attention      = in_array( $state, array( State::STATE_MISSING, State::STATE_DRAFT ), true ) || ! empty( $override );

		return array(
			'id'             => self::make_row_id( $product_slug, $template_key ),
			'template_key'   => $template_key,
			'product_slug'   => $product_slug,
			'product'        => esc_html( $product_data['group_label'] ),
			'name'           => esc_html( $template_data['title'] ),
			'name_desc'      => $this->render_name( $template_data, $post, $state, $view_url, $uses_fse ),
			'usage'          => $this->render_usage( $template_data ),
			'source'         => $this->render_source( $source, $override, $state ),
			'source_key'     => $source['key'],
			'state'          => $this->render_state( $state ),
			'state_key'      => $state,
			'attention'      => $attention,
			'type'           => $template_data['type'],
			'post_id'        => $post_id,
			'page_slug'      => $post ? $post->post_name : '',
			'page_title'     => $post ? $post->post_title : '',
			'assign_page_id' => $post_id,
			'edit_url'       => $edit_url,
			'view_url'       => $view_url,
			'can_view'       => ! empty( $view_url ),
			'uses_page'      => ! $uses_fse || $is_page,
			'is_override'    => ! empty( $template_data['is_override'] ),
			'is_fse'         => $uses_fse,
			'can_recreate'   => ! $uses_fse || $is_page,
			'can_configure'  => ! $uses_fse || $is_page,
			'fse_slug'       => $template_data['fse_slug'],
			'post_type'      => $template_data['post_type'],
			'override'       => $override ? array(
				'builder' => $override['builder'],
				'label'   => $override['label'],
				'id'      => $override['id'],
			) : null,
		);
	}

	/**
	 * Render the Template column.
	 *
	 * @param array         $template_data Template data.
	 * @param \WP_Post|null $post          Page post.
	 * @param string        $state         State key.
	 * @param string        $view_url      Front end URL for pages.
	 * @param bool          $uses_fse      Whether a block template renders this row.
	 * @return string HTML.
	 */
	private function render_name( $template_data, $post, $state, $view_url, $uses_fse ) {
		$indent = ! empty( $template_data['is_override'] ) ? '<i class="fa-solid fa-turn-up fa-rotate-90 text-muted me-2 ms-2 small"></i>' : '';
		$icon   = ! empty( $template_data['icon_class'] ) ? '<i class="' . esc_attr( $template_data['icon_class'] ) . ' me-1"></i> ' : '';

		$html  = '<div class="' . ( ! empty( $template_data['is_override'] ) ? 'ps-2' : '' ) . '">';
		$html .= $indent . $icon . '<strong>' . esc_html( $template_data['title'] ) . '</strong>';

		if ( ! empty( $template_data['description'] ) ) {
			$html .= '<br><small class="text-muted">' . esc_html( $template_data['description'] ) . '</small>';
		}

		if ( $post && State::STATE_MISSING !== $state ) {
			$slug       = $view_url
				? '<a href="' . esc_url( $view_url ) . '" target="_blank" rel="noopener">/' . esc_html( $post->post_name ) . '/</a>'
				: '';
			$page_label = $uses_fse && 'page' !== $template_data['type']
				? __( 'Classic page (unused on block themes)', 'ayecode-connect' )
				: $post->post_title;

			$html .= '<br><small class="text-muted"><i class="fa-regular fa-file me-1"></i>' . esc_html( $page_label ) . ( $slug ? ' ' . $slug : '' ) . '</small>';
		} elseif ( ! $uses_fse && ! empty( $template_data['post_id'] ) ) {
			$html .= '<br><small class="text-danger">' . sprintf(
				/* translators: %d: page ID */
				esc_html__( 'Page #%d no longer exists.', 'ayecode-connect' ),
				absint( $template_data['post_id'] )
			) . '</small>';
		} elseif ( ! $uses_fse ) {
			$html .= '<br><small class="text-danger">' . esc_html__( 'No page is assigned.', 'ayecode-connect' ) . '</small>';
		}

		$html .= '</div>';

		return $html;
	}

	/**
	 * Render the Applies To column.
	 *
	 * @param array $template_data Template data.
	 * @return string HTML.
	 */
	private function render_usage( $template_data ) {
		if ( empty( $template_data['usage'] ) ) {
			return '';
		}

		$class = ! empty( $template_data['global'] ) ? 'text-bg-secondary' : 'text-bg-primary';

		return '<span class="badge rounded-pill ' . $class . '">' . esc_html( $template_data['usage'] ) . '</span>';
	}

	/**
	 * Render the Source column.
	 *
	 * @param array      $source   Source info from Router.
	 * @param array|null $override Theme builder override, if any.
	 * @param string     $state    State key.
	 * @return string HTML.
	 */
	private function render_source( $source, $override, $state ) {
		if ( ! empty( $override ) ) {
			$title = __( 'A theme builder template takes over rendering. The page assigned to this template is not used while it is active.', 'ayecode-connect' );

			$html = '<span class="text-warning-emphasis" title="' . esc_attr( $title ) . '" data-bs-toggle="tooltip"><i class="fa-solid fa-triangle-exclamation me-1"></i>' . esc_html( $override['label'] ) . '</span>';

			if ( ! empty( $override['post_type_label'] ) ) {
				$html .= '<br><small class="text-muted">' . sprintf(
					/* translators: %s: post type name */
					esc_html__( 'Applies to %s', 'ayecode-connect' ),
					esc_html( $override['post_type_label'] )
				) . '</small>';
			}

			return $html;
		}

		if ( State::STATE_MISSING === $state ) {
			return '<span class="text-muted">' . esc_html__( 'Nothing', 'ayecode-connect' ) . '</span>';
		}

		return esc_html( $source['label'] );
	}

	/**
	 * Render the State column.
	 *
	 * @param string $state State key.
	 * @return string HTML.
	 */
	private function render_state( $state ) {
		$labels  = State::labels();
		$classes = array(
			State::STATE_DEFAULT    => 'text-bg-light border',
			State::STATE_CUSTOMIZED => 'text-bg-primary',
			State::STATE_MISSING    => 'text-bg-danger',
			State::STATE_DRAFT      => 'text-bg-warning',
		);

		$label = isset( $labels[ $state ] ) ? $labels[ $state ] : ucfirst( $state );
		$class = isset( $classes[ $state ] ) ? $classes[ $state ] : 'text-bg-secondary';

		return '<span class="badge rounded-pill ' . esc_attr( $class ) . '">' . esc_html( $label ) . '</span>';
	}
}
