<?php
/**
 * Plugin Name: DWC AI Image Marker
 * Plugin URI: https://github.com/DarkWolfCave/dwc-ai-image-marker
 * Description: Automatically marks AI-generated images with a badge and centrally manages them in WordPress.
 * Version: 1.2.4.2
 * Author: DarkWolfCave.de
 * Author URI: https://darkwolfcave.de
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dwc-ai-marker
 *
 * @package DWC_AI_Image_Marker
 */

// Sicherheitscheck.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin-Pfade definieren.
const DWC_AI_MARKER_VERSION = '1.2.4.2';
define( 'DWC_AI_MARKER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DWC_AI_MARKER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DWC_AI_MARKER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );
// GitHub Repository URL definieren.
define( 'DWC_AI_MARKER_GITHUB_REPO', 'https://github.com/DarkWolfCave/dwc-ai-image-marker' );
define( 'DWC_AI_MARKER_GITHUB_API', 'https://api.github.com/repos/DarkWolfCave/dwc-ai-image-marker/releases/latest' );

// Erforderliche Dateien laden.
require_once DWC_AI_MARKER_PLUGIN_DIR . 'includes/class-dwc-ai-marker-updater.php';
require_once DWC_AI_MARKER_PLUGIN_DIR . 'admin/class-dwc-ai-marker-update.php';
require_once DWC_AI_MARKER_PLUGIN_DIR . 'includes/class-dwc-ai-marker-activator.php';
require_once DWC_AI_MARKER_PLUGIN_DIR . 'includes/class-dwc-ai-marker-utils.php';
require_once DWC_AI_MARKER_PLUGIN_DIR . 'includes/class-dwc-ai-marker-loader.php';
require_once DWC_AI_MARKER_PLUGIN_DIR . 'admin/class-dwc-ai-marker-admin.php';
require_once DWC_AI_MARKER_PLUGIN_DIR . 'public/class-dwc-ai-marker-frontend.php';
require_once DWC_AI_MARKER_PLUGIN_DIR . 'public/class-dwc-ai-marker-background.php';

/**
 * Gibt die Haupt-Plugin-Instanz zurück
 *
 * @return Dwc_Ai_Marker_Loader
 */
function dwc_ai_marker(): Dwc_Ai_Marker_Loader {
	return Dwc_Ai_Marker_Loader::get_instance();
}

// Plugin starten.
dwc_ai_marker();

// Update-Prüfung starten.
add_action( 'admin_init', array( 'Dwc_Ai_Marker_Updater', 'check_for_update' ) );
