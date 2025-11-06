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
		// delete_transient( 'dwc_ai_marker_update_check' );
		$options      = get_option( 'dwc_ai_marker_settings', array() );
		$github_token = ! empty( $options['github_token'] ) ? trim( $options['github_token'] ) : '';

		$last_check = get_transient( 'dwc_ai_marker_update_check' );
		if ( false !== $last_check ) {
			return;
		}

		$current_version = DWC_AI_MARKER_VERSION;

		// Initialisiere Debug-Informationen mit Basiswerten.
		$debug_info = array(
			'github_tag'           => '',
			'github_version_clean' => '',
			'current_version'      => $current_version,
			'comparison_result'    => 'Kein Update nötig',
			'check_time'           => current_time( 'mysql' ),
			'api_status'           => 'Keine Abfrage',
			'api_error'            => '',
		);
		$headers    = array(
			'User-Agent' => 'WordPress/' . get_bloginfo( 'version' ),
		);

		if ( ! empty( $github_token ) ) {
			// GitHub Classic Tokens benötigen 'token', Fine-grained Tokens benötigen 'Bearer'.
			// Versuche zuerst 'Bearer' (funktioniert für beide), bei 401-Fehler Fallback auf 'token'.
			$headers['Authorization'] = 'Bearer ' . $github_token;
		}

		$response = wp_remote_get(
			self::$github_api_url,
			array(
				'timeout' => 10,
				'headers' => $headers,
			)
		);

		if ( is_wp_error( $response ) ) {
			// WP-Fehler bei der Anfrage (z.B. Timeout, DNS-Fehler).
			$debug_info['api_status'] = 'Fehler';
			$debug_info['api_error']  = $response->get_error_message();

			// Cache auch bei Fehler, um wiederholte Anfragen zu vermeiden.
			set_transient( 'dwc_ai_marker_update_check', time(), 3 * HOUR_IN_SECONDS );
			update_option( 'dwc_ai_marker_debug_info', wp_json_encode( $debug_info ) );

			// Debug-Benachrichtigung anzeigen, wenn Debug-Modus aktiv.
			$options       = get_option( 'dwc_ai_marker_settings', array() );
			$debug_enabled = isset( $options['debug_enabled'] ) && $options['debug_enabled'];
			if ( $debug_enabled ) {
				add_action( 'admin_notices', array( __CLASS__, 'show_debug_notice' ) );
			}

			return;
		}

		// HTTP-Status prüfen.
		$http_code = wp_remote_retrieve_response_code( $response );
		if ( ! empty( $http_code ) ) {
			$debug_info['api_status'] = $http_code;
		}

		// Bei 401-Fehler mit Bearer: Versuche Fallback auf 'token' für Classic Tokens.
		if ( 401 === $http_code && ! empty( $github_token ) && isset( $headers['Authorization'] ) && strpos( $headers['Authorization'], 'Bearer' ) === 0 ) {
			// Fallback: Versuche Classic Token Format.
			$headers['Authorization'] = 'token ' . $github_token;
			$response                 = wp_remote_get(
				self::$github_api_url,
				array(
					'timeout' => 10,
					'headers' => $headers,
				)
			);

			// HTTP-Status erneut prüfen.
			$http_code = wp_remote_retrieve_response_code( $response );
			if ( ! empty( $http_code ) ) {
				$debug_info['api_status'] = $http_code . ' (nach Fallback)';
			}
		}

		if ( 200 !== $http_code ) {
			// HTTP-Fehler (404, 403, etc.).
			$response_body = wp_remote_retrieve_body( $response );
			$error_message = wp_remote_retrieve_response_message( $response );
			
			// Versuche Fehlermeldung aus JSON-Body zu extrahieren.
			if ( ! empty( $response_body ) ) {
				$error_body = json_decode( $response_body, true );
				if ( isset( $error_body['message'] ) ) {
					$error_message = $error_body['message'];
				}
			}
			
			$debug_info['api_error'] = 'HTTP ' . $http_code . ': ' . $error_message;

			// Cache auch bei Fehler, um wiederholte Anfragen zu vermeiden.
			set_transient( 'dwc_ai_marker_update_check', time(), 3 * HOUR_IN_SECONDS );
			update_option( 'dwc_ai_marker_debug_info', wp_json_encode( $debug_info ) );

			// Debug-Benachrichtigung anzeigen, wenn Debug-Modus aktiv
			$options       = get_option( 'dwc_ai_marker_settings', array() );
			$debug_enabled = isset( $options['debug_enabled'] ) && $options['debug_enabled'];
			if ( $debug_enabled ) {
				add_action( 'admin_notices', array( __CLASS__, 'show_debug_notice' ) );
			}

			return;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		// JSON-Dekodierungsfehler prüfen.
		if ( json_last_error() !== JSON_ERROR_NONE ) {
			$debug_info['api_error'] = 'JSON-Fehler: ' . json_last_error_msg();
			update_option( 'dwc_ai_marker_debug_info', wp_json_encode( $debug_info ) );

			// Debug-Benachrichtigung anzeigen, wenn Debug-Modus aktiv.
			$options       = get_option( 'dwc_ai_marker_settings', array() );
			$debug_enabled = isset( $options['debug_enabled'] ) && $options['debug_enabled'];
			if ( $debug_enabled ) {
				add_action( 'admin_notices', array( __CLASS__, 'show_debug_notice' ) );
			}

			return;
		}

		// Debug: Speichere die komplette API-Antwort.
		update_option( 'dwc_ai_marker_github_response', wp_json_encode( $body ) );

		$latest_version = isset( $body['tag_name'] ) ? sanitize_text_field( $body['tag_name'] ) : '';

		// "v" am Anfang der Version entfernen, wenn vorhanden.
		$latest_version_clean = ltrim( $latest_version, 'v' );

		// Debug-Informationen aktualisieren.
		$debug_info['github_tag']           = $latest_version;
		$debug_info['github_version_clean'] = $latest_version_clean;
		$debug_info['comparison_result']    = version_compare( $latest_version_clean, $current_version, '>' ) ?
			'Update verfügbar' : 'Kein Update nötig';

		// Debug-Infos speichern und ggf. anzeigen.
		$options       = get_option( 'dwc_ai_marker_settings', array() );
		$debug_enabled = isset( $options['debug_enabled'] ) && $options['debug_enabled'];

		if ( $debug_enabled ) {
			update_option( 'dwc_ai_marker_debug_info', wp_json_encode( $debug_info ) );
			// Debug-Benachrichtigung nur anzeigen, wenn Debug-Modus aktiv.
			add_action( 'admin_notices', array( __CLASS__, 'show_debug_notice' ) );
		}

		if ( $latest_version_clean && version_compare( $latest_version_clean, $current_version, '>' ) ) {
			update_option( 'dwc_ai_marker_latest_version', $latest_version );
			// Bei verfügbarem Update Admin-Benachrichtigung anzeigen.
			add_action( 'admin_notices', array( __CLASS__, 'show_update_notice' ) );
		} else {
			// Wenn keine neue Version verfügbar ist, Option zurücksetzen.
			delete_option( 'dwc_ai_marker_latest_version' );
		}

		// Marker für letzte Prüfung setzen (12 Stunden).
		set_transient( 'dwc_ai_marker_update_check', time(), 12 * HOUR_IN_SECONDS );
	}

	/**
	 * Zeigt Debug-Informationen an
	 */
	public static function show_debug_notice() {
		// Prüfen, ob der aktuelle Benutzer ein Administrator ist.
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}
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
			<p><strong>API-Status:</strong> <?php echo esc_html( $debug_info['api_status'] ?? 'unbekannt' ); ?></p>
			<?php if ( ! empty( $debug_info['api_error'] ) ) : ?>
				<p><strong style="color:red">API-Fehler:</strong> <?php echo esc_html( $debug_info['api_error'] ); ?>
				</p>
			<?php endif; ?>
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