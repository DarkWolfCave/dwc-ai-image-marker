<?php
/**
 * Admin-Funktionen für das Plugin.
 *
 * @package DWC_AI_Image_Marker
 * @subpackage DWC_AI_Image_Marker/admin
 */

// Sicherheitscheck.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Dwc_Ai_Marker_Admin
 *
 * Verwaltet die Admin-Funktionen des Plugins.
 *
 * @since 1.1.0
 */
class Dwc_Ai_Marker_Admin {

	/**
	 * Initialisiert die Admin-Funktionen und Hooks.
	 */
	public function __construct() {
		// Admin-Hooks.
		add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_filter( 'bulk_actions-upload', array( $this, 'add_bulk_actions' ) );
		add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_actions' ), 10, 3 );
		add_action( 'admin_notices', array( $this, 'bulk_action_notices' ) );
		add_filter( 'manage_upload_columns', array( $this, 'add_media_columns' ) );
		add_action( 'manage_media_custom_column', array( $this, 'manage_media_custom_column' ), 10, 2 );
		add_filter( 'manage_upload_sortable_columns', array( $this, 'register_sortable_columns' ) );
		add_action( 'pre_get_posts', array( $this, 'sort_columns' ) );
		// Hooks für Medienbibliothek.
		add_action( 'init', array( $this, 'init_media_hooks' ) );
		// AJAX-Handler für Token-Validierung.
		add_action( 'wp_ajax_validate_github_token', array( $this, 'validate_github_token' ) );

		// JavaScript-Variablen hinzufügen.
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_admin_scripts' ) );
	}

	/**
	 * JavaScript für den Admin-Bereich laden
	 */
	public function enqueue_admin_scripts( $hook ) {
		if ( 'settings_page_dwc-ai-marker' !== $hook ) {
			return;
		}

		// WordPress-Admin-Script wird bereits geladen, wir fügen nur Lokalisierung hinzu.
		wp_localize_script(
			'jquery',
			'dwc_ai_marker_vars',
			array(
				'nonce' => wp_create_nonce( 'dwc_ai_marker_token_validation' ),
			)
		);
	}

	/**
	 * Validiert einen GitHub-Token via AJAX
	 */
	public function validate_github_token() {
		// Sicherheitsprüfung.
		if ( ! check_ajax_referer( 'dwc_ai_marker_token_validation', 'security', false ) ) {
			wp_send_json_error( array( 'message' => 'Sicherheitscheck fehlgeschlagen.' ) );
		}

		if ( ! current_user_can( 'manage_options' ) ) {
			wp_send_json_error( array( 'message' => 'Keine Berechtigung.' ) );
		}

		$token = isset( $_POST['token'] ) ? trim( sanitize_text_field( wp_unslash( $_POST['token'] ) ) ) : '';

		if ( empty( $token ) ) {
			wp_send_json_error( array( 'message' => 'Kein Token angegeben.' ) );
		}

		// GitHub API Rate-Limit prüfen (minimaler Endpunkt).
		$response = wp_remote_get(
			'https://api.github.com/rate_limit',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $token,
					'User-Agent'    => 'WordPress/' . get_bloginfo( 'version' ),
				),
				'timeout' => 10,
			)
		);

		if ( is_wp_error( $response ) ) {
			wp_send_json_error( array( 'message' => 'Verbindungsfehler: ' . $response->get_error_message() ) );
		}

		$status = wp_remote_retrieve_response_code( $response );
		$body   = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( 200 === $status ) {
			// Token ist gültig, Limit-Informationen zurückgeben.
			$rate_limit     = isset( $body['resources']['core']['limit'] ) ? $body['resources']['core']['limit'] : 'unbekannt';
			$rate_remaining = isset( $body['resources']['core']['remaining'] ) ? $body['resources']['core']['remaining'] : 'unbekannt';

			wp_send_json_success(
				array(
					'message'        => sprintf(
						'Token gültig! API-Limit: %s, verbleibend: %s',
						$rate_limit,
						$rate_remaining
					),
					'rate_limit'     => $rate_limit,
					'rate_remaining' => $rate_remaining,
				)
			);
		} elseif ( 401 === $status ) {
			wp_send_json_error( array( 'message' => 'Token ungültig oder abgelaufen.' ) );
		} else {
			// Andere Fehler.
			$error_message = isset( $body['message'] ) ? $body['message'] : 'Unbekannter Fehler (Status ' . $status . ')';
			wp_send_json_error( array( 'message' => 'API-Fehler: ' . $error_message ) );
		}
	}

	/**
	 * Initialisiert Hooks für die Medienbibliothek.
	 */
	public function init_media_hooks() {
		// Felder in der Medienbibliothek.
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_ai_image_field' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_ai_image_field' ), 10, 2 );
	}

	/**
	 * Add the plugin settings page to the WordPress admin menu.
	 *
	 * Creates a new settings page under the 'Settings' menu in WordPress admin panel.
	 * Only users with 'manage_options' capability can access this page.
	 */
	public function add_admin_menu() {
		add_options_page(
			'DWC AI Image Marker Settings', // Page title.
			'AI Image Marker', // Menu title.
			'manage_options', // Capability (administrators only).
			'dwc-ai-marker', // Menu slug.
			array( $this, 'settings_page' ) // Callback for settings page.
		);
	}

	/**
	 * Register plugin settings.
	 */
	public function register_settings() {
		register_setting(
			'dwc_ai_marker_settings_group',
			'dwc_ai_marker_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array( 'badge_text' => 'KI-generiert' ),
			)
		);
		// Register additional settings.
		register_setting( 'dwc_ai_marker_settings_group', 'dwc_ai_marker_settings[background_color]' );
		register_setting( 'dwc_ai_marker_settings_group', 'dwc_ai_marker_settings[font_family]' );
		register_setting( 'dwc_ai_marker_settings_group', 'dwc_ai_marker_settings[opacity]' );
		register_setting( 'dwc_ai_marker_settings_group', 'dwc_ai_marker_settings[padding]' );
	}

	/**
	 * Sanitize plugin settings.
	 *
	 * @param array $input The settings input.
	 *
	 * @return array Sanitized settings.
	 * @since 1.1.0
	 * @updated 1.2.0 Unterstützung für debug_enabled hinzugefügt.
	 */
	public function sanitize_settings( $input ) {
		$sanitized               = array();
		$sanitized['badge_text'] = sanitize_text_field( $input['badge_text'] );
		// Sanitize additional settings.
		$sanitized['background_color'] = sanitize_hex_color( $input['background_color'] );
		$sanitized['font_family']      = sanitize_text_field( $input['font_family'] );
		$sanitized['opacity']          = floatval( $input['opacity'] );
		$sanitized['padding_top']      = intval( $input['padding_top'] );
		$sanitized['padding_right']    = intval( $input['padding_right'] );
		$sanitized['padding_bottom']   = intval( $input['padding_bottom'] );
		$sanitized['padding_left']     = intval( $input['padding_left'] );
		$sanitized['debug_enabled']    = isset( $input['debug_enabled'] ) ? (bool) $input['debug_enabled'] : false;

		// Validate position.
		$valid_positions       = array( 'top-left', 'top-right', 'bottom-left', 'bottom-right' );
		$sanitized['position'] = in_array( $input['position'], $valid_positions, true ) ? $input['position'] : 'top-left';

		// GitHub Token unverändert übernehmen.
		$sanitized['github_token'] = isset( $input['github_token'] ) ? $input['github_token'] : '';

		return $sanitized;
	}

	/**
	 * Render the settings page.
	 *
	 * @since 1.1.0
	 * @updated 1.2.0 Debug-Modus-Einstellung hinzugefügt.
	 * @updated 1.2.2 Update-Informationen hinzugefügt.
	 */
	public function settings_page() {
		$options = dwc_ai_marker()->get_options();

		// Überprüfe, ob das Update durchgeführt werden soll.
		if ( isset( $_POST['dwc_ai_update_plugin'] ) && check_admin_referer( 'dwc_ai_update_plugin_nonce' ) ) {
			Dwc_Ai_Marker_Update::update_plugin();
		}

		// Aktuelle und neueste Version abrufen.
		$current_version = DWC_AI_MARKER_VERSION;
		$latest_version  = get_option( 'dwc_ai_marker_latest_version', '' );
		// $update_available = $latest_version && version_compare( $latest_version, $current_version, '>' );
		// "v" am Anfang der Version entfernen, wenn vorhanden
		$latest_version_clean = ltrim( $latest_version, 'v' );
		$update_available     = $latest_version_clean && version_compare( $latest_version_clean, $current_version, '>' );
		// Debug-Informationen abrufen für erweiterte Status-Anzeige.
		$debug_info = json_decode( get_option( 'dwc_ai_marker_debug_info', '{}' ), true );
		$api_status = isset( $debug_info['api_status'] ) ? $debug_info['api_status'] : '';
		$api_error  = isset( $debug_info['api_error'] ) ? $debug_info['api_error'] : '';
		?>
		<div class="wrap">
			<h1>DWC AI Image Marker Einstellungen</h1>

			<!-- Update-Bereich -->
			<div class="card">
				<h2><?php esc_html_e( 'Plugin-Version', 'dwc-ai-marker' ); ?></h2>
				<p>
					<?php esc_html_e( 'Installierte Version:', 'dwc-ai-marker' ); ?>
					<strong><?php echo esc_html( $current_version ); ?></strong>
				</p>

				<?php if ( ! empty( $api_error ) ) : ?>
					<p style="color:red">
						<?php esc_html_e( 'API-Fehler beim Prüfen auf Updates:', 'dwc-ai-marker' ); ?>
						<strong><?php echo esc_html( $api_error ); ?></strong>
					</p>
				<?php elseif ( $update_available ) : ?>
					<p>
						<?php esc_html_e( 'Neue Version verfügbar:', 'dwc-ai-marker' ); ?>
						<strong><?php echo esc_html( $latest_version ); ?></strong>
					</p>
					<form method="post">
						<?php wp_nonce_field( 'dwc_ai_update_plugin_nonce' ); ?>
						<input type="submit" name="dwc_ai_update_plugin" class="button button-primary"
								value="<?php esc_attr_e( 'Jetzt aktualisieren', 'dwc-ai-marker' ); ?>">
					</form>
				<?php else : ?>
					<p><?php esc_html_e( 'Du verwendest die neueste Version.', 'dwc-ai-marker' ); ?></p>
					<?php if ( $api_status ) : ?>
						<p class="description">
							<?php esc_html_e( 'Letzter API-Status:', 'dwc-ai-marker' ); ?>
							<?php echo esc_html( $api_status ); ?>
						</p>
					<?php endif; ?>
				<?php endif; ?>
			</div>

			<form method="post" action="options.php">
				<?php settings_fields( 'dwc_ai_marker_settings_group' ); ?>
				<style>
					.dwc-padding-settings label {
						display: inline-block;
						width: 50px; /* Width for the labels. */
						text-align: right;
						margin-right: 5px;
					}

					.dwc-padding-settings input {
						width: 60px; /* Width for the input fields. */
					}
				</style>

				<table class="form-table">
					<tr>
						<th><label for="badge_text">Badge Text</label></th>
						<td>
							<input type="text" id="badge_text" name="dwc_ai_marker_settings[badge_text]"
									value="<?php echo esc_attr( $options['badge_text'] ); ?>"/>
						</td>
					</tr>
					<tr>
						<th><label for="background_color">Hintergrundfarbe</label></th>
						<td>
							<input type="color" id="background_color" name="dwc_ai_marker_settings[background_color]"
									value="<?php echo esc_attr( $options['background_color'] ); ?>"/>
						</td>
					</tr>
					<tr>
						<th><label for="font_family">Schriftart</label></th>
						<td>
							<input type="text" id="font_family" name="dwc_ai_marker_settings[font_family]"
									value="<?php echo esc_attr( $options['font_family'] ); ?>"/>
						</td>
					</tr>
					<tr>
						<th><label for="opacity_range">Transparenz</label></th>
						<td>
							<input type="range" min="0" max="1" step="0.1" name="dwc_ai_marker_settings[opacity]"
									id="opacity_range"
									value="<?php echo esc_attr( $options['opacity'] ); ?>"/>
							<input type="text" id="opacity_display"
									value="<?php echo esc_attr( $options['opacity'] ); ?>" readonly size="3"/>

						</td>
					</tr>
					<tr>
						<th>Randabstände</th>
						<td class="dwc-padding-settings">
							<label for="padding_top">Oben:</label>
							<input type="number" id="padding_top" name="dwc_ai_marker_settings[padding_top]"
									value="<?php echo esc_attr( $options['padding_top'] ); ?>"/> px
							<br>
							<label for="padding_right">Rechts:</label>
							<input type="number" id="padding_right" name="dwc_ai_marker_settings[padding_right]"
									value="<?php echo esc_attr( $options['padding_right'] ); ?>"/> px
							<br>
							<label for="padding_bottom">Unten:</label>
							<input type="number" id="padding_bottom" name="dwc_ai_marker_settings[padding_bottom]"
									value="<?php echo esc_attr( $options['padding_bottom'] ); ?>"/> px
							<br>
							<label for="padding_left">Links:</label>
							<input type="number" id="padding_left" name="dwc_ai_marker_settings[padding_left]"
									value="<?php echo esc_attr( $options['padding_left'] ); ?>"/> px
						</td>
					</tr>
					<tr>
						<th><label for="badge_position">Badge Position</label></th>
						<td>
							<select id="badge_position" name="dwc_ai_marker_settings[position]">
								<option value="top-left" <?php selected( isset( $options['position'] ) ? $options['position'] : 'top-left', 'top-left' ); ?>>
									Oben Links
								</option>
								<option value="top-right" <?php selected( isset( $options['position'] ) ? $options['position'] : 'top-left', 'top-right' ); ?>>
									Oben Rechts
								</option>
								<option value="bottom-left" <?php selected( isset( $options['position'] ) ? $options['position'] : 'top-left', 'bottom-left' ); ?>>
									Unten Links
								</option>
								<option value="bottom-right" <?php selected( isset( $options['position'] ) ? $options['position'] : 'top-left', 'bottom-right' ); ?>>
									Unten Rechts
								</option>
							</select>
						</td>
					</tr>
					<tr>
						<th><label for="github_token">GitHub API Token</label></th>
						<td>
							<?php
							// Options direkt aus der Datenbank abrufen.
							$current_options = get_option( 'dwc_ai_marker_settings', array() );
							$current_token   = isset( $current_options['github_token'] ) ? $current_options['github_token'] : '';
							?>

							<div style="display: flex; align-items: center;">
								<input type="password" id="github_token" name="dwc_ai_marker_settings[github_token]"
										value="<?php echo esc_attr( $current_token ); ?>" style="flex: 1;"/>
								<button type="button" id="toggle_token_visibility" class="button"
										style="margin-left: 5px;">
									<span class="dashicons dashicons-visibility"></span>
								</button>
								<button type="button" id="validate_token" class="button button-secondary"
										style="margin-left: 5px;">
									Token prüfen
								</button>
							</div>

							<div id="token_validation_result" style="margin-top: 5px; display: none;">
								<span id="token_validation_icon" class="dashicons"></span>
								<span id="token_validation_message"></span>
							</div>

							<?php if ( ! empty( $current_token ) ) : ?>
								<div class="token-status" style="margin-top: 5px;">
									<span class="dashicons dashicons-yes" style="color: green;"></span>
									<span style="color: green; font-weight: bold;">Token konfiguriert</span>
									<small style="margin-left: 5px; color: #666;">
										(endet mit <?php echo esc_html( substr( $current_token, - 4 ) ); ?>)
									</small>
								</div>
							<?php else : ?>
								<div class="token-status" style="margin-top: 5px;">
									<span class="dashicons dashicons-no" style="color: #d63638;"></span>
									<span style="color: #d63638;">Kein Token konfiguriert</span>
								</div>
							<?php endif; ?>

							<p class="description">
								Persönlicher GitHub-Token für API-Zugriff. Erhöht das API-Limit von 60 auf 5000 Anfragen
								pro Stunde und behebt 403-Fehler.
								<a href="https://github.com/settings/tokens" target="_blank">Token erstellen</a> (nur
								"public_repo" Berechtigung auswählen).
							</p>
						</td>
					</tr>

					<tr>
						<th><label for="debug_enabled">Debug-Modus</label></th>
						<td>
							<input type="checkbox" id="debug_enabled" name="dwc_ai_marker_settings[debug_enabled]"
									value="1"
								<?php checked( isset( $options['debug_enabled'] ) ? $options['debug_enabled'] : false ); ?> />
							Debug-Meldungen in der Browser-Konsole anzeigen
							<p class="description">
								Aktivieren Sie diese Option, um detaillierte Debug-Informationen in der Browser-Konsole
								anzuzeigen.
								Dies ist hilfreich zur Fehlersuche, sollte aber im Produktivbetrieb deaktiviert werden.
							</p>
						</td>
					</tr>
				</table>
				<?php submit_button(); ?>
				<input type="button" id="reset_defaults" class="button" value="Standardwerte"/>

			</form>
			<script>
				document.addEventListener('DOMContentLoaded', function () {
					const resetButton = document.getElementById('reset_defaults');

					// Get defaults from get_options.
					const defaults = <?php echo wp_json_encode( Dwc_Ai_Marker_Utils::get_defaults() ); ?>;

					resetButton.addEventListener('click', function () {
						document.querySelector('input[name="dwc_ai_marker_settings[badge_text]"]').value = defaults.badge_text;
						document.querySelector('input[name="dwc_ai_marker_settings[background_color]"]').value = defaults.background_color;
						document.querySelector('select[name="dwc_ai_marker_settings[position]"]').value = defaults.position;
						document.querySelector('input[name="dwc_ai_marker_settings[font_family]"]').value = defaults.font_family;
						document.querySelector('input[name="dwc_ai_marker_settings[opacity]"]').value = defaults.opacity;
						document.querySelector('input[name="dwc_ai_marker_settings[padding_top]"]').value = defaults.padding_top;
						document.querySelector('input[name="dwc_ai_marker_settings[padding_right]"]').value = defaults.padding_right;
						document.querySelector('input[name="dwc_ai_marker_settings[padding_bottom]"]').value = defaults.padding_bottom;
						document.querySelector('input[name="dwc_ai_marker_settings[padding_left]"]').value = defaults.padding_left;
						document.getElementById('opacity_display').value = defaults.opacity;
						document.querySelector('input[name="dwc_ai_marker_settings[debug_enabled]"]').checked = defaults.debug_enabled;
					});
				});

				document.addEventListener('DOMContentLoaded', function () {
					const opacityRange = document.getElementById('opacity_range');
					const opacityDisplay = document.getElementById('opacity_display');

					opacityRange.addEventListener('input', function () {
						opacityDisplay.value = opacityRange.value;
					});
				});
				// GitHub Token Validierung
				document.addEventListener('DOMContentLoaded', function () {
					const tokenField = document.getElementById('github_token');
					const toggleButton = document.getElementById('toggle_token_visibility');
					const validateButton = document.getElementById('validate_token');
					const resultDiv = document.getElementById('token_validation_result');
					const resultIcon = document.getElementById('token_validation_icon');
					const resultMessage = document.getElementById('token_validation_message');

					// Toggle-Funktion
					if (tokenField && toggleButton) {
						toggleButton.addEventListener('click', function () {
							if (tokenField.type === 'password') {
								tokenField.type = 'text';
								toggleButton.querySelector('.dashicons').classList.remove('dashicons-visibility');
								toggleButton.querySelector('.dashicons').classList.add('dashicons-hidden');
							} else {
								tokenField.type = 'password';
								toggleButton.querySelector('.dashicons').classList.remove('dashicons-hidden');
								toggleButton.querySelector('.dashicons').classList.add('dashicons-visibility');
							}
						});
					}

					// Validierungs-Funktion
					if (validateButton && tokenField) {
						validateButton.addEventListener('click', function () {
							const token = tokenField.value.trim();

							if (!token) {
								resultDiv.style.display = 'block';
								resultIcon.className = 'dashicons dashicons-warning';
								resultIcon.style.color = '#f0ad4e';
								resultMessage.textContent = 'Bitte gib einen Token ein.';
								resultMessage.style.color = '#f0ad4e';
								return;
							}

							// Status während der Prüfung anzeigen
							resultDiv.style.display = 'block';
							resultIcon.className = 'dashicons dashicons-update';
							resultIcon.style.color = '#007cba';
							resultMessage.textContent = 'Token wird überprüft...';
							resultMessage.style.color = '#007cba';
							validateButton.disabled = true;

							// AJAX-Request für die Validierung
							const data = new FormData();
							data.append('action', 'validate_github_token');
							data.append('token', token);
							data.append('security', dwc_ai_marker_vars.nonce);

							fetch(ajaxurl, {
								method: 'POST',
								credentials: 'same-origin',
								body: data
							})
								.then(response => response.json())
								.then(data => {
									if (data.success) {
										resultIcon.className = 'dashicons dashicons-yes';
										resultIcon.style.color = 'green';
										resultMessage.textContent = data.data.message;
										resultMessage.style.color = 'green';
									} else {
										resultIcon.className = 'dashicons dashicons-no';
										resultIcon.style.color = '#d63638';
										resultMessage.textContent = data.data.message;
										resultMessage.style.color = '#d63638';
									}
									validateButton.disabled = false;
								})
								.catch(error => {
									resultIcon.className = 'dashicons dashicons-no';
									resultIcon.style.color = '#d63638';
									resultMessage.textContent = 'Fehler bei der Überprüfung: ' + error.message;
									resultMessage.style.color = '#d63638';
									validateButton.disabled = false;
								});
						});
					}
				});

			</script>
		</div>
		<?php
	}

	/**
	 * Add a new column to the media library.
	 *
	 * @param array $columns Existing columns.
	 *
	 * @return array Modified columns.
	 */
	public function add_media_columns( $columns ) {
		$columns['ai_generated'] = __( 'KI-generiert', 'dwc-ai-marker' );

		return $columns;
	}

	/**
	 * Display content for custom media column.
	 *
	 * @param string $column_name Name of the column.
	 * @param int    $post_id Post ID.
	 */
	public function manage_media_custom_column( $column_name, $post_id ) {
		if ( 'ai_generated' === $column_name ) {
			$is_ai = get_post_meta( $post_id, '_is_ai_generated', true );
			if ( $is_ai ) {
				echo '<span style="font-size: 16px; display: inline-block; padding-left: 5px;" title="' . esc_attr__( 'AI-generated image', 'dwc-ai-marker' ) . '">🤖</span>';
			} else {
				echo '<span style="color: #ccc; display: inline-block; padding-left: 5px;" title="' . esc_attr__( 'Not an AI image', 'dwc-ai-marker' ) . '">−</span>';
			}
		}
	}

	/**
	 * Register sortable media columns.
	 *
	 * @param array $columns Existing sortable columns.
	 *
	 * @return array Modified sortable columns.
	 */
	public function register_sortable_columns( $columns ) {
		$columns['ai_generated'] = 'ai_generated';

		return $columns;
	}

	/**
	 * Implement sorting for media columns.
	 *
	 * @param WP_Query $query The current query.
	 */
	public function sort_columns( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || 'attachment' !== $query->get( 'post_type' ) ) {
			return;
		}

		$orderby = $query->get( 'orderby' );
		if ( 'ai_generated' === $orderby ) {
			$query->set( 'meta_key', '_is_ai_generated' );
			$query->set( 'orderby', 'meta_value' );
		}
	}

	/**
	 * Add bulk actions to the media library.
	 *
	 * @param array $bulk_actions Existing bulk actions.
	 *
	 * @return array Modified bulk actions.
	 */
	public function add_bulk_actions( $bulk_actions ) {
		$bulk_actions['mark_ai_generated']   = __( 'Als KI-generiert markieren', 'dwc-ai-marker' );
		$bulk_actions['unmark_ai_generated'] = __( 'KI-Markierung entfernen', 'dwc-ai-marker' );

		return $bulk_actions;
	}

	/**
	 * Handle bulk actions.
	 *
	 * @param string $redirect_to The redirect URL.
	 * @param string $doaction The action being processed.
	 * @param array  $post_ids The array of post IDs to process.
	 *
	 * @return string Modified redirect URL.
	 */
	public function handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
		if ( 'mark_ai_generated' !== $doaction && 'unmark_ai_generated' !== $doaction ) {
			return $redirect_to;
		}

		// Prüfe den Nonce für die Sicherheit.
		if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'bulk-media' ) ) {
			return $redirect_to;
		}

		$updated = 0;

		foreach ( $post_ids as $post_id ) {
			if ( 'mark_ai_generated' === $doaction ) {
				update_post_meta( $post_id, '_is_ai_generated', '1' );
				++$updated;
			} elseif ( 'unmark_ai_generated' === $doaction ) {
				delete_post_meta( $post_id, '_is_ai_generated' );
				++$updated;
			}
		}

		$redirect_to = add_query_arg(
			array(
				'bulk_ai_marker_updated' => $updated,
				'bulk_action'            => $doaction,
			),
			$redirect_to
		);

		return $redirect_to;
	}

	/**
	 * Display admin notices for bulk actions.
	 */
	public function bulk_action_notices() {
		if ( ! empty( $_REQUEST['bulk_ai_marker_updated'] ) && isset( $_REQUEST['bulk_action'] ) ) {
			// Prüfe den Nonce für die Sicherheit.
			if ( ! isset( $_REQUEST['_wpnonce'] ) || ! wp_verify_nonce( sanitize_key( $_REQUEST['_wpnonce'] ), 'bulk-media' ) ) {
				return;
			}

			$updated = intval( wp_unslash( $_REQUEST['bulk_ai_marker_updated'] ) );
			$action  = sanitize_text_field( wp_unslash( $_REQUEST['bulk_action'] ) );

			$message = '';
			if ( 'mark_ai_generated' === $action ) {
				/* translators: %s is the number of images marked as AI-generated. */
				$message = sprintf(
					_n(
						'%s Bild wurde als KI-generiert markiert.',
						'%s Bilder wurden als KI-generiert markiert.',
						$updated,
						'dwc-ai-marker'
					),
					number_format_i18n( $updated )
				);
			} else {
				// translators: %s is the number of images from which the AI mark was removed.
				$message = sprintf(
					_n(
						'KI-Markierung wurde von %s Bild entfernt.',
						'KI-Markierung wurde von %s Bildern entfernt.',
						$updated,
						'dwc-ai-marker'
					),
					number_format_i18n( $updated )
				);
			}

			echo '<div class="updated notice is-dismissible"><p>' . esc_html( $message ) . '</p></div>';
		}
	}

	/**
	 * Add a custom field in the media library.
	 *
	 * @param array   $form_fields Form fields.
	 * @param WP_Post $post Post object.
	 *
	 * @return array Modified form fields.
	 */
	public function add_ai_image_field( $form_fields, $post ) {
		$field_value = get_post_meta( $post->ID, '_is_ai_generated', true );

		// Generate nonce field.
		$nonce_field = wp_nonce_field(
			'save_ai_image_field_' . $post->ID,
			'ai_image_field_nonce_' . $post->ID,
			true,
			false
		);

		// Generate checkbox input.
		$checkbox  = '<input type="checkbox" id="attachments-' . esc_attr( $post->ID ) . '-is_ai_generated" ';
		$checkbox .= 'name="attachments[' . esc_attr( $post->ID ) . '][is_ai_generated]" value="1"';
		$checkbox .= checked( $field_value, '1', false );
		$checkbox .= '/>';

		$form_fields['is_ai_generated'] = array(
			'label' => __( 'KI-generiertes Bild', 'dwc-ai-marker' ),
			'input' => 'html',
			'html'  => $nonce_field . '<label for="attachments-' . esc_attr( $post->ID ) . '-is_ai_generated">' . $checkbox . ' ' . esc_html__( 'Ja', 'dwc-ai-marker' ) . '</label>',
			'helps' => __( 'Mark this image as AI-generated (DALL-E, Adobe Firefly, Midjourney, etc.)', 'dwc-ai-marker' ),
		);

		return $form_fields;
	}

	/**
	 * Save the custom field for AI-generated images.
	 *
	 * @param array $post Post data.
	 * @param array $attachment Attachment data.
	 *
	 * @return array Modified post data.
	 */
	public function save_ai_image_field( $post, $attachment ) {
		$nonce_key = 'ai_image_field_nonce_' . $post['ID'];
		$nonce     = '';

		if ( isset( $_POST[ $nonce_key ] ) ) {
			$nonce = sanitize_key( wp_unslash( $_POST[ $nonce_key ] ) );
		}

		if ( ! wp_verify_nonce( $nonce, 'save_ai_image_field_' . $post['ID'] ) ) {
			return $post;
		}

		if ( isset( $attachment['is_ai_generated'] ) ) {
			update_post_meta( $post['ID'], '_is_ai_generated', '1' );
		} else {
			delete_post_meta( $post['ID'], '_is_ai_generated' );
		}

		return $post;
	}
}
