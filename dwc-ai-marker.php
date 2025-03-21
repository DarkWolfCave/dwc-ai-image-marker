<?php
/**
 * Plugin Name: DWC AI Image Marker
 * Plugin URI: https://github.com/DarkWolfCave/dwc-ai-image-marker
 * Description: Automatically marks AI-generated images with a badge and centrally manages them in WordPress.
 * Version: 1.2.1
 * Author: DarkWolfCave.de
 * Author URI: https://darkwolfcave.de
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dwc-ai-marker
 *
 * @package DWC_AI_Image_Marker
 */

// Sicherheitscheck
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Plugin-Pfade definieren
define( 'DWC_AI_MARKER_VERSION', '1.2.1' );
define( 'DWC_AI_MARKER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DWC_AI_MARKER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DWC_AI_MARKER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Erforderliche Dateien laden
require_once DWC_AI_MARKER_PLUGIN_DIR . 'includes/class-dwc-ai-marker-activator.php';
require_once DWC_AI_MARKER_PLUGIN_DIR . 'includes/class-dwc-ai-marker-utils.php';
require_once DWC_AI_MARKER_PLUGIN_DIR . 'admin/class-dwc-ai-marker-admin.php';
require_once DWC_AI_MARKER_PLUGIN_DIR . 'public/class-dwc-ai-marker-frontend.php';
require_once DWC_AI_MARKER_PLUGIN_DIR . 'public/class-dwc-ai-marker-background.php';

/**
 * Class Dwc_Ai_Marker_Loader
 *
 * Hauptklasse für den Plugin-Loader
 *
 * @since 1.1.0
 */
class Dwc_Ai_Marker_Loader {
	/**
	 * Plugin-Instanz
	 *
	 * @var Dwc_Ai_Marker_Loader
	 */
	private static $instance = null;

	/**
	 * Admin-Instanz
	 *
	 * @var Dwc_Ai_Marker_Admin
	 */
	public $admin;

	/**
	 * Frontend-Instanz
	 *
	 * @var Dwc_Ai_Marker_Frontend
	 */
	public $frontend;

	/**
	 * Hintergrund-Instanz
	 *
	 * @var Dwc_Ai_Marker_Background
	 */
	public $background;

	/**
	 * Hilfsfunktionen-Instanz
	 *
	 * @var Dwc_Ai_Marker_Utils
	 */
	public $utils;

	/**
	 * Plugin-Optionen
	 *
	 * @var array
	 */
	private $options = null;

	/**
	 * Dwc_Ai_Marker_Loader Konstruktor.
	 * Initialisiert das Plugin und seine Komponenten.
	 */
	private function __construct() {
		$this->utils      = new Dwc_Ai_Marker_Utils();
		$this->admin      = new Dwc_Ai_Marker_Admin();
		$this->frontend   = new Dwc_Ai_Marker_Frontend();
		$this->background = new Dwc_Ai_Marker_Background();

		$this->init_hooks();
	}

	/**
	 * Singleton-Instanz zurückgeben
	 *
	 * @return Dwc_Ai_Marker_Loader Plugin-Instanz
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Plugin-Hooks initialisieren
	 */
	private function init_hooks() {
		// Aktivierungs- und Deaktivierungshooks
		register_activation_hook( __FILE__, array( 'Dwc_Ai_Marker_Activator', 'activate' ) );
		register_deactivation_hook( __FILE__, array( 'Dwc_Ai_Marker_Activator', 'deactivate' ) );

		// CSS-Datei verschieben
		add_action( 'admin_init', array( $this, 'move_css_file' ) );
	}

	/**
	 * CSS-Datei verschieben (Einmalig bei Plugin-Aktualisierung)
	 */
	public function move_css_file() {
		$src_file  = DWC_AI_MARKER_PLUGIN_DIR . 'css/dwc-ai-marker.css';
		$dest_file = DWC_AI_MARKER_PLUGIN_DIR . 'assets/css/dwc-ai-marker.css';

		if ( file_exists( $src_file ) && ! file_exists( $dest_file ) ) {
			// Stellen Sie sicher, dass der Zielordner existiert
			if ( ! is_dir( dirname( $dest_file ) ) ) {
				wp_mkdir_p( dirname( $dest_file ) );
			}

			// Kopieren Sie die Datei
			copy( $src_file, $dest_file );
		}
	}

	/**
	 * Holt die Plugin-Optionen
	 *
	 * @return array Plugin-Optionen
	 */
	public function get_options() {
		if ( null === $this->options ) {
			$this->options = wp_parse_args(
				get_option( 'dwc_ai_marker_settings', array() ),
				Dwc_Ai_Marker_Utils::get_defaults()
			);
		}

		return $this->options;
	}
}

/**
 * Gibt die Haupt-Plugin-Instanz zurück
 *
 * @return Dwc_Ai_Marker_Loader
 */
function dwc_ai_marker() {
	return Dwc_Ai_Marker_Loader::get_instance();
}

// Plugin starten
dwc_ai_marker();
