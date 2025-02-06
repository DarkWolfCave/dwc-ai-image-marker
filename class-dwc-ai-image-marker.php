<?php
/**
 * Plugin Name: DWC AI Image Marker
 * Plugin URI: https://github.com/DarkWolfCave/dwc-ai-marker-easy/archive/refs/heads/master.zip
 * Description: Markiert KI-generierte Bilder automatisch mit einem Badge und verwaltet diese zentral in WordPress.
 * Version: 1.1.0
 * Author: DarkWolfCave.de
 * Author URI: https://darkwolfcave.de
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: dwc-ai-image-marker
 *
 * @package DWC_AI_Image_Marker
 */

// Sicherheitscheck.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Dwc_Ai_Image_Marker {
	// Plugin-Version
	private $version         = '1.1.0';
	private $options         = null;
	private static $defaults = array(
		'badge_text'       => 'KI-generiert',
		'position'         => 'top-left',
		'background_color' => '#000000',
		'font_family'      => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
		'opacity'          => 0.7,
		'padding_top'      => 5,
		'padding_right'    => 10,
		'padding_bottom'   => 5,
		'padding_left'     => 10,
	);

	// Konstruktor
	public function __construct() {
		// Hook für die Plugin-Initialisierung
		add_action( 'init', array( $this, 'init' ) );

		// Admin-Hooks
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			// Neue Hooks für Bulk-Actions
			add_filter( 'bulk_actions-upload', array( $this, 'add_bulk_actions' ) );
			add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_actions' ), 10, 3 );
			add_action( 'admin_notices', array( $this, 'bulk_action_notices' ) );
			// Neue Hooks für Medienübersicht
			add_filter( 'manage_upload_columns', array( $this, 'add_media_columns' ) );
			add_action( 'manage_media_custom_column', array( $this, 'manage_media_custom_column' ), 10, 2 );
			add_filter( 'manage_upload_sortable_columns', array( $this, 'register_sortable_columns' ) );
			add_action( 'pre_get_posts', array( $this, 'sort_columns' ) );

		}

		// Frontend-Hooks
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_filter( 'render_block', array( $this, 'handle_generateblocks_image' ), 10, 3 );
	}

	// Neue Spalte hinzufügen
	public function add_media_columns( $columns ) {
		$columns['ai_generated'] = __( 'KI-generiert', 'dwc-ai-marker' );

		return $columns;
	}

	// Spalteninhalt anzeigen
	public function manage_media_custom_column( $column_name, $post_id ) {
		if ( $column_name === 'ai_generated' ) {
			$is_ai = get_post_meta( $post_id, '_is_ai_generated', true );
			if ( $is_ai ) {
				echo '<span style="font-size: 16px; display: inline-block; padding-left: 5px;" title="KI-generiertes Bild">🤖</span>';
			} else {
				echo '<span style="color: #ccc; display: inline-block; padding-left: 5px;" title="Kein KI-Bild">−</span>';
			}
		}
	}

	// Spalte sortierbar machen
	public function register_sortable_columns( $columns ) {
		$columns['ai_generated'] = 'ai_generated';

		return $columns;
	}

	// Sortierung implementieren
	public function sort_columns( $query ) {
		if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'attachment' ) {
			return;
		}

		$orderby = $query->get( 'orderby' );
		if ( $orderby === 'ai_generated' ) {
			$query->set( 'meta_key', '_is_ai_generated' );
			$query->set( 'orderby', 'meta_value' );
		}
	}

	// Registriere die Einstellungen
	public function register_settings() {
		register_setting(
			'dwc_ai_marker_settings_group',
			'dwc_ai_marker_settings',
			array(
				'sanitize_callback' => array( $this, 'sanitize_settings' ),
				'default'           => array( 'badge_text' => 'KI-generiert' ),
			)
		);
		// Registrierung der neuen Einstellungen
		register_setting( 'dwc_ai_marker_settings_group', 'dwc_ai_marker_settings[background_color]' );
		register_setting( 'dwc_ai_marker_settings_group', 'dwc_ai_marker_settings[font_family]' );
		register_setting( 'dwc_ai_marker_settings_group', 'dwc_ai_marker_settings[opacity]' );
		register_setting( 'dwc_ai_marker_settings_group', 'dwc_ai_marker_settings[padding]' );
	}

	// Bulk-Actions zur Medienbibliothek hinzufügen
	public function add_bulk_actions( $bulk_actions ) {
		$bulk_actions['mark_ai_generated']   = __( 'Als KI-generiert markieren', 'dwc-ai-marker' );
		$bulk_actions['unmark_ai_generated'] = __( 'KI-Markierung entfernen', 'dwc-ai-marker' );

		return $bulk_actions;
	}

	// Bulk-Actions verarbeiten
	public function handle_bulk_actions( $redirect_to, $doaction, $post_ids ) {
		if ( $doaction !== 'mark_ai_generated' && $doaction !== 'unmark_ai_generated' ) {
			return $redirect_to;
		}

		$updated = 0;

		foreach ( $post_ids as $post_id ) {
			if ( $doaction === 'mark_ai_generated' ) {
				update_post_meta( $post_id, '_is_ai_generated', '1' );
				++$updated;
			} elseif ( $doaction === 'unmark_ai_generated' ) {
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

	// Admin-Benachrichtigungen für Bulk-Actions
	public function bulk_action_notices() {
		if ( ! empty( $_REQUEST['bulk_ai_marker_updated'] ) ) {
			$updated = intval( $_REQUEST['bulk_ai_marker_updated'] );
			$action  = $_REQUEST['bulk_action'];

			$message = '';
			if ( $action === 'mark_ai_generated' ) {
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


	// Sanitize-Funktion
	public function sanitize_settings( $input ) {
		$sanitized               = array();
		$sanitized['badge_text'] = sanitize_text_field( $input['badge_text'] );
		// Sanitize die neuen Einstellungen
		$sanitized['background_color'] = sanitize_hex_color( $input['background_color'] ); // Hex-farben-Säuberung
		$sanitized['font_family']      = sanitize_text_field( $input['font_family'] );
		$sanitized['opacity']          = floatval( $input['opacity'] );
		$sanitized['padding_top']      = intval( $input['padding_top'] );
		$sanitized['padding_right']    = intval( $input['padding_right'] );
		$sanitized['padding_bottom']   = intval( $input['padding_bottom'] );
		$sanitized['padding_left']     = intval( $input['padding_left'] );

		// Position validieren
		$valid_positions       = array( 'top-left', 'top-right', 'bottom-left', 'bottom-right' );
		$sanitized['position'] = in_array( $input['position'], $valid_positions ) ? $input['position'] : 'top-left';

		return $sanitized;
	}

	public function enqueue_styles() {
		wp_enqueue_style(
			'dwc-ai-marker',
			$this->get_plugin_url() . 'css/dwc-ai-marker.css',
			array(),
			$this->version
		);

		// Dynamisches CSS basierend auf Position
		$options  = $this->get_options();
		$position = isset( $options['position'] ) ? $options['position'] : 'top-left';

		$css = '.ai-image-badge {';
		switch ( $position ) {
			case 'top-right':
				$css .= 'left: auto !important; right: 10px !important; top: 10px !important; bottom: auto !important;';
				break;
			case 'bottom-left':
				$css .= 'left: 10px !important; right: auto !important; top: auto !important; bottom: 10px !important;';
				break;
			case 'bottom-right':
				$css .= 'left: auto !important; right: 10px !important; top: auto !important; bottom: 10px !important;';
				break;
			default: // top-left
				$css .= 'left: 10px !important; right: auto !important; top: 10px !important; bottom: auto !important;';
		}
		// Hinzufügen der Styles für die neuen Optionen
		$css .= 'background-color: ' . esc_attr( $options['background_color'] ) . ' !important;';
		$css .= 'font-family: ' . esc_attr( $options['font_family'] ) . ' !important;';
		$css .= 'opacity: ' . esc_attr( $options['opacity'] ) . ' !important;';
		$css .= 'padding: ' . esc_attr( $options['padding_top'] ) . 'px ' .
				esc_attr( $options['padding_right'] ) . 'px ' .
				esc_attr( $options['padding_bottom'] ) . 'px ' .
				esc_attr( $options['padding_left'] ) . 'px !important;';
		$css .= '}';

		// Anpassung des Hover-Effekts je nach Position
		$css .= '.ai-image-badge:hover::after {';
		switch ( $position ) {
			case 'top-right':
				$css .= 'right: 0 !important; left: auto !important; top: 100% !important;';
				break;
			case 'bottom-left':
				$css .= 'left: 0 !important; right: auto !important; bottom: 100% !important; top: auto !important; margin-top: 0 !important; margin-bottom: 5px !important;';
				break;
			case 'bottom-right':
				$css .= 'right: 0 !important; left: auto !important; bottom: 100% !important; top: auto !important; margin-top: 0 !important; margin-bottom: 5px !important;';
				break;
			default: // top-left
				$css .= 'left: 0 !important; right: auto !important; top: 100% !important;';
		}
		$css .= '}';

		wp_add_inline_style( 'dwc-ai-marker', $css );
	}


	// Helper-Funktion für Options
	private function get_options() {
		if ( $this->options === null ) {
			$this->options = wp_parse_args(
				get_option( 'dwc_ai_marker_settings', array() ),
				self::$defaults
			);
		}

		return $this->options;
	}

	// Plugin-Initialisierung
	public function init() {
		// Custom Field für Medienbibliothek
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_ai_image_field' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_ai_image_field' ), 10, 2 );

		// Filter für Bildausgabe
		add_filter( 'render_block_core/image', array( $this, 'modify_image_block' ), 10, 2 );
	}

	public function add_admin_menu() {
		add_options_page(
			'DWC AI Image Marker Settings', // Seitentitel
			'AI Image Marker', // Menütitel
			'manage_options', // Fähigkeit (nur Administratoren)
			'dwc-ai-marker', // Menü-Slug
			array( $this, 'settings_page' ) // Callback für die Einstellungsseite
		);
	}

	// Rendern der Einstellungsseite
	public function settings_page() {
		$options = $this->get_options();
		?>
		<div class="wrap">
			<h1>DWC AI Image Marker Einstellungen</h1>
			<form method="post" action="options.php">
				<?php settings_fields( 'dwc_ai_marker_settings_group' ); ?>
				<style>
					.dwc-padding-settings label {
						display: inline-block;
						width: 50px; /* Breite für die Label */
						text-align: right;
						margin-right: 5px;
					}

					.dwc-padding-settings input {
						width: 60px; /* Breite für die Eingabefelder */
					}
				</style>

				<table class="form-table">
					<tr>
						<th>Badge Text</th>
						<td>
							<input type="text" name="dwc_ai_marker_settings[badge_text]"
									value="<?php echo esc_attr( $options['badge_text'] ); ?>"/>
						</td>
					</tr>
					<tr>
						<th>Hintergrundfarbe</th>
						<td>
							<input type="color" name="dwc_ai_marker_settings[background_color]"
									value="<?php echo esc_attr( $options['background_color'] ); ?>"/>
						</td>
					</tr>
					<tr>
						<th>Schriftart</th>
						<td>
							<input type="text" name="dwc_ai_marker_settings[font_family]"
									value="<?php echo esc_attr( $options['font_family'] ); ?>"/>
						</td>
					</tr>
					<tr>
						<th>Transparenz</th>
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
							<input type="number" name="dwc_ai_marker_settings[padding_top]"
									value="<?php echo esc_attr( $options['padding_top'] ); ?>"/> px
							<br>
							<label for="padding_right">Rechts:</label>
							<input type="number" name="dwc_ai_marker_settings[padding_right]"
									value="<?php echo esc_attr( $options['padding_right'] ); ?>"/> px
							<br>
							<label for="padding_bottom">Unten:</label>
							<input type="number" name="dwc_ai_marker_settings[padding_bottom]"
									value="<?php echo esc_attr( $options['padding_bottom'] ); ?>"/> px
							<br>
							<label for="padding_left">Links:</label>
							<input type="number" name="dwc_ai_marker_settings[padding_left]"
									value="<?php echo esc_attr( $options['padding_left'] ); ?>"/> px
						</td>
					</tr>
					<tr>
						<th>Badge Position</th>
						<td>
							<select name="dwc_ai_marker_settings[position]">
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
				</table>
				<?php submit_button(); ?>
				<input type="button" id="reset_defaults" class="button" value="Standardwerte"/>

			</form>
			<script>
				document.addEventListener('DOMContentLoaded', function () {
					const resetButton = document.getElementById('reset_defaults');

					// Hole Standardwerte aus get_options als separate Standardwerte
					const defaults = <?php echo wp_json_encode( self::$defaults ); ?>;

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
					});
				});

				document.addEventListener('DOMContentLoaded', function () {
					const opacityRange = document.getElementById('opacity_range');
					const opacityDisplay = document.getElementById('opacity_display');

					opacityRange.addEventListener('input', function () {
						opacityDisplay.value = opacityRange.value;
					});
				});
			</script>
		</div>
		<?php
	}

	// Custom Field in der Medienbibliothek hinzufügen
	public function add_ai_image_field( $form_fields, $post ) {
		$field_value = get_post_meta( $post->ID, '_is_ai_generated', true );

		$form_fields['is_ai_generated'] = array(
			'label' => __( 'KI-generiertes Bild', 'dwc-ai-marker' ),
			'input' => 'html',
			'html'  => sprintf(
				'%s<label><input type="checkbox" name="attachments[%d][is_ai_generated]" value="1" %s/> %s</label>',
				wp_nonce_field( 'save_ai_image_field_' . $post->ID, 'ai_image_field_nonce_' . $post->ID, true, false ),
				esc_attr( $post->ID ),
				checked( $field_value, '1', false ),
				__( 'Ja', 'dwc-ai-marker' )
			),
			'helps' => __( 'Markiere dieses Bild als KI-generiert (DALL-E, Adobe Firefly, Midjourney etc.)', 'dwc-ai-marker' ),
		);

		return $form_fields;
	}

	// Custom Field speichern
	public function save_ai_image_field( $post, $attachment ) {
		$nonce = isset( $_POST[ 'ai_image_field_nonce_' . $post['ID'] ] ) ? $_POST[ 'ai_image_field_nonce_' . $post['ID'] ] : '';

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

	// GenerateBlocks Image Block behandeln
	// GenerateBlocks Image Block behandeln
	public function handle_generateblocks_image( $block_content, $block, $instance ) {
		// Prüfen, ob es sich um einen GenerateBlocks Image Block handelt
		if ( isset( $block['blockName'] ) && $block['blockName'] === 'generateblocks/image' ) {
			$attributes = isset( $block['attrs'] ) ? $block['attrs'] : null;

			// Versuche die Bild-ID zu bekommen
			$image_id = isset( $attributes['mediaId'] ) ? $attributes['mediaId'] : null;

			if ( $image_id && get_post_meta( $image_id, '_is_ai_generated', true ) ) {
				$dom = new DOMDocument();
				@$dom->loadHTML( mb_convert_encoding( $block_content, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
				// Finde das img-Element
				$img = $dom->getElementsByTagName( 'img' )->item( 0 );
				if ( $img ) {
					// Erstelle einen Wrapper
					$wrapper = $dom->createElement( 'div' );
					$wrapper->setAttribute( 'class', 'ai-image-wrapper' );

					// Füge das Badge hinzu
					$badge = $dom->createElement( 'div' );
					$badge->setAttribute( 'class', 'ai-image-badge' );
					$options    = get_option( 'dwc_ai_marker_settings' );
					$badge_text = isset( $options['badge_text'] ) ? $options['badge_text'] : 'KI-generiert';
					$badge->appendChild( $dom->createTextNode( $badge_text ) );

					// Umschließe das Bild
					$img->parentNode->insertBefore( $wrapper, $img );
					$wrapper->appendChild( $badge );
					$wrapper->appendChild( $img );

					$block_content = $dom->saveHTML();
				}
			}
		}

		return $block_content;
	}

	// Bildblock modifizieren
	public function modify_image_block( $block_content, $block ) {
		$image_id = isset( $block['attrs']['id'] ) ? $block['attrs']['id'] : null;

		if ( $image_id && get_post_meta( $image_id, '_is_ai_generated', true ) ) {
			$dom = new DOMDocument();
			@$dom->loadHTML( mb_convert_encoding( $block_content, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

			// Finde das img-Element
			$img = $dom->getElementsByTagName( 'img' )->item( 0 );
			if ( $img ) {
				// Erstelle einen Wrapper
				$wrapper = $dom->createElement( 'div' );
				$wrapper->setAttribute( 'class', 'ai-image-wrapper' );

				// Füge das Badge hinzu
				$badge = $dom->createElement( 'div' );
				$badge->setAttribute( 'class', 'ai-image-badge' );
				$options    = get_option( 'dwc_ai_marker_settings' );
				$badge_text = isset( $options['badge_text'] ) ? $options['badge_text'] : 'KI-generiert';
				$badge->appendChild( $dom->createTextNode( $badge_text ) );

				// Umschließe das Bild
				$img->parentNode->insertBefore( $wrapper, $img );
				$wrapper->appendChild( $badge );
				$wrapper->appendChild( $img );

				$block_content = $dom->saveHTML();
			}
		}

		return $block_content;
	}

	// Helper: Plugin-URL ermitteln
	private function get_plugin_url() {
		return plugin_dir_url( __FILE__ );
	}

	// Helper: Plugin-Pfad ermitteln
	private function get_plugin_path() {
		return plugin_dir_path( __FILE__ );
	}
}

// Plugin initialisieren
$dwc_ai_image_marker = new Dwc_Ai_Image_Marker();

// Aktivierungshook
register_activation_hook( __FILE__, 'dwc_ai_marker_activate' );

function dwc_ai_marker_activate() {
	// Nur setzen wenn noch nicht vorhanden
	if ( ! get_option( 'dwc_ai_marker_settings' ) ) {
		update_option(
			'dwc_ai_marker_settings',
			array(
				'badge_text'       => 'KI-generiert',
				'position'         => 'top-left',
				'background_color' => '#000000', // Standardeinstellung aus CSS: rgba(0, 0, 0, 0.7)
				'font_family'      => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
				'opacity'          => 0.7, // Transparentwert aus der CSS
				'padding_top'      => 5,    // Standardwert in Pixeln
				'padding_right'    => 10,
				'padding_bottom'   => 5,
				'padding_left'     => 10,
			)
		);
	}
}

// Deaktivierungshook
register_deactivation_hook( __FILE__, 'dwc_ai_marker_deactivate' );

function dwc_ai_marker_deactivate() {
	// Hier können Deaktivierungsroutinen hinzugefügt werden
}
