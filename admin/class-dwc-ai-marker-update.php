<?php
/**
 * Dwc_Ai_Marker_Update.
 *
 * Plugin-Update von GitHub laden und installieren.
 *
 * @package DWC_AI_Image_Marker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Klasse für den Plugin-Update-Prozess.
 */
class Dwc_Ai_Marker_Update {

	/**
	 * GitHub Repository URL für Releases.
	 *
	 * @var string
	 */
	private static $github_repo = DWC_AI_MARKER_GITHUB_REPO;

	/**
	 * Lädt das Plugin-Update von GitHub und installiert es.
	 *
	 * @return void
	 */
	/**
	 * Lädt das Plugin-Update von GitHub und installiert es.
	 *
	 * @return void
	 */
	public static function update_plugin() {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( esc_html__( 'Keine Berechtigung für diesen Bereich.', 'dwc-ai-marker' ) );
		}

		$latest_version = get_option( 'dwc_ai_marker_latest_version', '' );

		if ( empty( $latest_version ) ) {
			// Fehlermeldung in Transient speichern statt admin_notices
			set_transient( 'dwc_ai_marker_update_message', array(
				'type' => 'error',
				'message' => __( 'Keine neue Version verfügbar.', 'dwc-ai-marker' )
			), 300 );

			// Redirect zur Einstellungsseite
			wp_safe_redirect( admin_url( 'options-general.php?page=dwc-ai-marker' ) );
			exit;
		}
		error_log( 'DWC AI Marker: Versuche Update auf Version ' . $latest_version );
		$zip_url = self::$github_repo . '/archive/refs/tags/' . $latest_version . '.zip';
		error_log( 'DWC AI Marker: Download-URL: ' . $zip_url );

		$tmp_file = download_url( $zip_url );

		if ( is_wp_error( $tmp_file ) ) {
			$error_message = $tmp_file->get_error_message();
			error_log( 'DWC AI Marker: Download fehlgeschlagen - ' . $error_message );

			// Fehlermeldung in Transient speichern
			set_transient( 'dwc_ai_marker_update_message', array(
				'type' => 'error',
				'message' => __( 'Download fehlgeschlagen!', 'dwc-ai-marker' ) . ' Fehler: ' . $error_message
			), 300 );
			
			// Redirect zur Einstellungsseite
			wp_safe_redirect( admin_url( 'options-general.php?page=dwc-ai-marker' ) );
			exit;
		}

		global $wp_filesystem;
		if ( ! function_exists( 'WP_Filesystem' ) ) {
			require_once ABSPATH . 'wp-admin/includes/file.php';
		}

		WP_Filesystem();

		$plugin_dir  = WP_PLUGIN_DIR . '/dwc-ai-image-marker';
		$extract_dir = WP_PLUGIN_DIR . '/dwc-ai-image-marker-update';

		// Verzeichnis leeren.
		if ( file_exists( $extract_dir ) ) {
			$wp_filesystem->delete( $extract_dir, true );
		}
		$wp_filesystem->mkdir( $extract_dir );

		$unzip_result = unzip_file( $tmp_file, $extract_dir );
		wp_delete_file( $tmp_file );

		if ( is_wp_error( $unzip_result ) ) {
			error_log( 'DWC AI Marker: Entpacken fehlgeschlagen - ' . $unzip_result->get_error_message() );
			add_action(
				'admin_notices',
				function () use ( $unzip_result ) {
					echo '<div class="error"><p>' .
						esc_html__( 'Entpacken fehlgeschlagen!', 'dwc-ai-marker' ) .
						' Fehler: ' . esc_html( $unzip_result->get_error_message() ) .
						'</p></div>';
				}
			);

			return;
		}

		// Durchsuche das gesamte Archiv nach der dwc-ai-marker.php Datei.
		$plugin_file_found = false;
		$plugin_file_dir   = '';

		// Rekursive Funktion zum Durchsuchen von Verzeichnissen.
		function find_plugin_file( $dir ) {
			$files = glob( $dir . '/*' );
			foreach ( $files as $file ) {
				if ( basename( $file ) === 'dwc-ai-marker.php' ) {
					return dirname( $file );
				}
				if ( is_dir( $file ) ) {
					$result = find_plugin_file( $file );
					if ( $result ) {
						return $result;
					}
				}
			}

			return false;
		}

		// Suche nach der Hauptdatei.
		$plugin_file_dir = find_plugin_file( $extract_dir );

		if ( ! $plugin_file_dir ) {
			// Versuche auch andere mögliche Dateinamen.
			function find_any_plugin_file( $dir ) {
				$files = glob( $dir . '/*.php' );
				foreach ( $files as $file ) {
					$content = file_get_contents( $file );
					if ( strpos( $content, 'Plugin Name: DWC AI Image Marker' ) !== false ) {
						return dirname( $file );
					}
				}

				$subdirs = glob( $dir . '/*', GLOB_ONLYDIR );
				foreach ( $subdirs as $subdir ) {
					$result = find_any_plugin_file( $subdir );
					if ( $result ) {
						return $result;
					}
				}

				return false;
			}

			$plugin_file_dir = find_any_plugin_file( $extract_dir );
		}

		// Liste alle gefundenen PHP-Dateien im entpackten Archiv auf (für Debugging).
		$all_php_files = array();
		function list_all_php_files( $dir, &$results ) {
			$files = glob( $dir . '/*.php' );
			foreach ( $files as $file ) {
				$results[] = $file;
			}

			$subdirs = glob( $dir . '/*', GLOB_ONLYDIR );
			foreach ( $subdirs as $subdir ) {
				list_all_php_files( $subdir, $results );
			}
		}

		list_all_php_files( $extract_dir, $all_php_files );
		error_log( 'DWC AI Marker: Gefundene PHP-Dateien: ' . print_r( $all_php_files, true ) );

		// Zeige Verzeichnisstruktur.
		function list_dir_structure( $dir, $level = 0 ) {
			$files     = scandir( $dir );
			$structure = '';
			$indent    = str_repeat( '  ', $level );

			foreach ( $files as $file ) {
				if ( '.' === $file || '..' === $file ) {
					continue;
				}
				$path       = $dir . '/' . $file;
				$structure .= $indent . $file . "\n";
				if ( is_dir( $path ) ) {
					$structure .= list_dir_structure( $path, $level + 1 );
				}
			}

			return $structure;
		}

		$dir_structure = list_dir_structure( $extract_dir );
		error_log( 'DWC AI Marker: Verzeichnisstruktur:' . "\n" . $dir_structure );

		if ( ! $plugin_file_dir ) {
			error_log( 'DWC AI Marker: Plugin-Hauptdatei nicht gefunden!' );
			
			// Fehlermeldung in Transient speichern
			set_transient( 'dwc_ai_marker_update_message', array(
				'type' => 'error',
				'message' => __( 'Plugin-Hauptdatei nicht gefunden im entpackten Archiv!', 'dwc-ai-marker' )
			), 300 );
			
			// Redirect zur Einstellungsseite
			wp_safe_redirect( admin_url( 'options-general.php?page=dwc-ai-marker' ) );
			exit;
		}

		error_log( 'DWC AI Marker: Plugin-Verzeichnis gefunden: ' . $plugin_file_dir );

		// Altes Plugin löschen und neues verschieben.
		error_log( 'DWC AI Marker: Lösche altes Plugin-Verzeichnis: ' . $plugin_dir );
		$wp_filesystem->delete( $plugin_dir, true );

		error_log( 'DWC AI Marker: Verschiebe ' . $plugin_file_dir . ' nach ' . $plugin_dir );
		$move_result = $wp_filesystem->move( $plugin_file_dir, $plugin_dir );

		if ( ! $move_result ) {
			error_log( 'DWC AI Marker: Fehler beim Verschieben des Verzeichnisses' );
			
			// Fehlermeldung in Transient speichern
			set_transient( 'dwc_ai_marker_update_message', array(
				'type' => 'error',
				'message' => __( 'Fehler beim Verschieben des neuen Plugins!', 'dwc-ai-marker' )
			), 300 );
			
			// Redirect zur Einstellungsseite
			wp_safe_redirect( admin_url( 'options-general.php?page=dwc-ai-marker' ) );
			exit;
		}

		// Bereinigen.
		$wp_filesystem->delete( $extract_dir, true );

		// Erfolgsmeldung in Transient speichern
		set_transient( 'dwc_ai_marker_update_message', array(
			'type' => 'success',
			'message' => __( 'Update erfolgreich! Plugin wurde auf Version ', 'dwc-ai-marker' ) . $latest_version . __( ' aktualisiert.', 'dwc-ai-marker' )
		), 300 );

		// Plugin deaktivieren damit es neu aktiviert werden kann.
		deactivate_plugins( plugin_basename( DWC_AI_MARKER_PLUGIN_DIR . 'dwc-ai-marker.php' ) );
		
		// Plugin wieder aktivieren.
		$result = activate_plugin( DWC_AI_MARKER_PLUGIN_BASENAME );

		if ( is_wp_error( $result ) ) {
			error_log( 'DWC AI Marker: Aktivierungsfehler - ' . $result->get_error_message() );
			
			// Aktualisiere Meldung mit Aktivierungsproblem
			set_transient( 'dwc_ai_marker_update_message', array(
				'type' => 'warning',
				'message' => __( 'Update erfolgreich! Bitte aktiviere das Plugin manuell.', 'dwc-ai-marker' ) . ' ' . $result->get_error_message()
			), 300 );
		}
		
		// Redirect zur Einstellungsseite
		wp_safe_redirect( admin_url( 'options-general.php?page=dwc-ai-marker' ) );
		exit;
	}
}
