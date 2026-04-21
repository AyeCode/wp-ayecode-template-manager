<?php
/**
 * Main loader class for the WP AyeCode Template Manager plugin.
 *
 * Orchestrates the initialization of all plugin components and settings framework integration.
 *
 * @package AyeCode\Templates
 */

namespace AyeCode\Templates;

/**
 * Loader class.
 *
 * Main plugin initialization and component orchestration.
 */
class Loader {

	/**
	 * Constructor.
	 *
	 * Registers all hooks for the package. No logic is executed here directly;
	 * everything is delegated to other classes via WordPress hooks.
	 */
	public function __construct() {

		if ( ! class_exists( '\AyeCode\SettingsFramework\Settings_Framework' ) ) {
			add_action( 'admin_notices', array( $this, 'missing_framework_notice' ) );
			return;
		}

		// Boot at priority 11 so both singletons initialise after this Loader
		// runs at priority 10, but before `init` fires.
		add_action( 'plugins_loaded', array( 'AyeCode\Templates\TemplateManager', 'instance' ), 11 );

		if ( is_admin() ) {
			add_action( 'plugins_loaded', array( 'AyeCode\Templates\Settings', 'instance' ), 11 );
		}
	}

	/**
	 * Display an admin notice when the AyeCode Settings Framework is missing.
	 */
	public function missing_framework_notice() {
		?>
		<div class="notice notice-error">
			<p>
				<?php
				echo wp_kses_post(
					sprintf(
						/* translators: %s: plugin name */
						__( '<strong>%s</strong> requires the AyeCode Settings Framework to be installed and activated.', 'ayecode-connect' ),
						'WP AyeCode Template Manager'
					)
				);
				?>
			</p>
		</div>
		<?php
	}

}
