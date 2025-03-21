<?php
/**
 * Main loader class for the plugin
 *
 * @package DWC_AI_Image_Marker
 * @subpackage DWC_AI_Image_Marker/includes
 */

// Sicherheitscheck.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

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
	 * @var Dwc_Ai_Marker_Loader|null
	 */
	private static ?Dwc_Ai_Marker_Loader $instance = null;

	/**
	 * Admin-Instanz
	 *
	 * @var Dwc_Ai_Marker_Admin
	 */
	public Dwc_Ai_Marker_Admin $admin;

	/**
	 * Frontend-Instanz
	 *
	 * @var Dwc_Ai_Marker_Frontend
	 */
	public Dwc_Ai_Marker_Frontend $frontend;

	/**
	 * Hintergrund-Instanz
	 *
	 * @var Dwc_Ai_Marker_Background
	 */
	public Dwc_Ai_Marker_Background $background;

	/**
	 * Hilfsfunktionen-Instanz
	 *
	 * @var Dwc_Ai_Marker_Utils
	 */
	public Dwc_Ai_Marker_Utils $utils;

	/**
	 * Plugin-Optionen
	 *
	 * @var array|null
	 */
	private ?array $options = null;

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
	public static function get_instance(): ?Dwc_Ai_Marker_Loader {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Plugin-Hooks initialisieren
	 */
	private function init_hooks() {
		// Aktivierungs- und Deaktivierungshooks.
		register_activation_hook( DWC_AI_MARKER_PLUGIN_BASENAME, array( 'Dwc_Ai_Marker_Activator', 'activate' ) );
		register_deactivation_hook( DWC_AI_MARKER_PLUGIN_BASENAME, array( 'Dwc_Ai_Marker_Activator', 'deactivate' ) );

		// CSS-Datei verschieben.
		add_action( 'admin_init', array( $this, 'move_css_file' ) );
	}

	/**
	 * CSS-Datei verschieben (Einmalig bei Plugin-Aktualisierung)
	 */
	public function move_css_file() {
		$src_file  = DWC_AI_MARKER_PLUGIN_DIR . 'css/dwc-ai-marker.css';
		$dest_file = DWC_AI_MARKER_PLUGIN_DIR . 'assets/css/dwc-ai-marker.css';

		if ( file_exists( $src_file ) && ! file_exists( $dest_file ) ) {
			// Stellen Sie sicher, dass der Zielordner existiert.
			if ( ! is_dir( dirname( $dest_file ) ) ) {
				wp_mkdir_p( dirname( $dest_file ) );
			}

			// Kopieren Sie die Datei.
			copy( $src_file, $dest_file );
		}
	}

	/**
	 * Holt die Plugin-Optionen
	 *
	 * @return array Plugin-Optionen
	 */
	public function get_options(): ?array {
		if ( null === $this->options ) {
			$this->options = wp_parse_args(
				get_option( 'dwc_ai_marker_settings', array() ),
				Dwc_Ai_Marker_Utils::get_defaults()
			);
		}

		return $this->options;
	}
}
