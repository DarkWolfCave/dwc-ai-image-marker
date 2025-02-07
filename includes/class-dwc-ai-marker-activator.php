<?php
/**
 * Fired during plugin activation and deactivation
 *
 * @link       https://darkwolfcave.de
 * @since      1.1.0
 *
 * @package    DWC_AI_Image_Marker
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Dwc_Ai_Marker_Activator
 *
 * Handles activation and deactivation hooks for the plugin.
 *
 * @since 1.1.0
 */
class Dwc_Ai_Marker_Activator {

	/**
	 * Runs on plugin activation.
	 *
	 * Sets up default options if they don't exist.
	 *
	 * @return void
	 * @since 1.1.0
	 */
	public static function activate() {
		if ( ! get_option( 'dwc_ai_marker_settings' ) ) {
			update_option(
				'dwc_ai_marker_settings',
				array(
					'badge_text'       => 'AI-generated',
					'position'         => 'top-left',
					'background_color' => '#000000', // Default setting from CSS: rgba(0, 0, 0, 0.7).
					'font_family'      => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
					'opacity'          => 0.7, // Transparency value from CSS.
					'padding_top'      => 5,    // Default value in pixels.
					'padding_right'    => 10,
					'padding_bottom'   => 5,
					'padding_left'     => 10,
				)
			);
		}
	}

	/**
	 * Runs on plugin deactivation.
	 *
	 * Place cleanup routines here.
	 *
	 * @return void
	 * @since 1.1.0
	 */
	public static function deactivate() {
		// Add deactivation routines here.
	}
}
