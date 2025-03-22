<?php
/**
 * Dwc_Ai_Marker_Updater
 *
 * Prüft auf Updates in GitHub und zeigt eine Admin-Benachrichtigung an.
 *
 * @package DWC_AI_Image_Marker
 */

defined( 'ABSPATH' ) || exit;

/**
 * Klasse zur Prüfung von Plugin-Updates auf GitHub.
 */
class Dwc_Ai_Marker_Updater {

	/**
	 * GitHub API URL zur neuesten Release-Version.
	 *
	 * @var string
	 */
	private static $github_api_url = DWC_AI_MARKER_GITHUB_API;

	/**
	 * Prüft, ob eine neue Version verfügbar ist.
	 *
	 * @return void
	 */
	public static function check_for_update() {
		// Aktualisierung nur alle 12 Stunden prüfen, um API-Limits zu respektieren.
		delete_transient( 'dwc_ai_marker_update_check' );
		$last_check = get_transient( 'dwc_ai_marker_update_check' );
		if ( false !== $last_check ) {
			return;
		}

		$current_version = DWC_AI_MARKER_VERSION;
		$response        = wp_remote_get(
			self::$github_api_url,
			array(
				'headers' => array(
					'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
				),
			)
		);

		if ( is_wp_error( $response ) ) {
			// Cache auch bei Fehler, um wiederholte Anfragen zu vermeiden.
			set_transient( 'dwc_ai_marker_update_check', time(), 3 * HOUR_IN_SECONDS );
			update_option( 'dwc_ai_marker_debug_info', 'API-Fehler: ' . $response->get_error_message() );

			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );
		// Debug: Speichere die komplette API-Antwort.
		update_option( 'dwc_ai_marker_github_response', wp_json_encode( $body ) );

		$latest_version = isset( $body['tag_name'] ) ? sanitize_text_field( $body['tag_name'] ) : '';

		// "v" am Anfang der Version entfernen, wenn vorhanden.
		$latest_version_clean = ltrim( $latest_version, 'v' );
		// Debug-Informationen speichern.
		$debug_info = array(
			'github_tag'           => $latest_version,
			'github_version_clean' => $latest_version_clean,
			'current_version'      => $current_version,
			'comparison_result'    => version_compare( $latest_version_clean, $current_version, '>' ) ? 'Update verfügbar' : 'Kein Update nötig',
			'check_time'           => current_time( 'mysql' ),
		);

		update_option( 'dwc_ai_marker_debug_info', wp_json_encode( $debug_info ) );
		// Benachrichtigung immer anzeigen (für Debugging).
		add_action( 'admin_notices', array( __CLASS__, 'show_debug_notice' ) );

		// if ( $latest_version && version_compare( $latest_version, $current_version, '>' ) ) {
		if ( $latest_version_clean && version_compare( $latest_version_clean, $current_version, '>' ) ) {

			update_option( 'dwc_ai_marker_latest_version', $latest_version );
			// Bei verfügbarem Update Admin-Benachrichtigung anzeigen.
			add_action( 'admin_notices', array( __CLASS__, 'show_update_notice' ) );
		} else {
			// Wenn keine neue Version verfügbar ist, Option zurücksetzen.
			delete_option( 'dwc_ai_marker_latest_version' );
		}

		// Marker für letzte Prüfung setzen (12 Stunden).
		set_transient( 'dwc_ai_marker_update_check', time(), 1 * HOUR_IN_SECONDS );
	}

	/**
	 * Zeigt Debug-Informationen an
	 */
	public static function show_debug_notice() {
		$debug_info = json_decode( get_option( 'dwc_ai_marker_debug_info', '{}' ), true );
		?>
		<div class="notice notice-info is-dismissible">
			<h3>DWC AI Marker Update Debug</h3>
			<p><strong>GitHub Tag:</strong> <?php echo esc_html( $debug_info['github_tag'] ?? 'nicht gefunden' ); ?></p>
			<p><strong>Bereinigte
					Version:</strong> <?php echo esc_html( $debug_info['github_version_clean'] ?? 'nicht gefunden' ); ?>
			</p>
			<p><strong>Installierte
					Version:</strong> <?php echo esc_html( $debug_info['current_version'] ?? 'unbekannt' ); ?></p>
			<p>
				<strong>Vergleichsergebnis:</strong> <?php echo esc_html( $debug_info['comparison_result'] ?? 'unbekannt' ); ?>
			</p>
			<p><strong>Prüfzeitpunkt:</strong> <?php echo esc_html( $debug_info['check_time'] ?? 'unbekannt' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Zeigt eine Admin-Benachrichtigung für das Update an.
	 *
	 * @return void
	 */
	public static function show_update_notice() {
		$latest_version = get_option( 'dwc_ai_marker_latest_version', '' );

		if ( empty( $latest_version ) ) {
			return;
		}

		?>
		<div class="notice notice-warning is-dismissible">
			<p>
				<?php
				printf(
				/* translators: %s = Version */
					esc_html__( 'Eine neue Version von DWC AI Image Marker (%s) ist verfügbar!', 'dwc-ai-marker' ),
					esc_html( $latest_version )
				);
				?>
				<br>
				<a href="<?php echo esc_url( admin_url( 'options-general.php?page=dwc-ai-marker' ) ); ?>">
					<?php esc_html_e( 'Zu den Einstellungen', 'dwc-ai-marker' ); ?>
				</a>
			</p>
		</div>
		<?php
	}
}