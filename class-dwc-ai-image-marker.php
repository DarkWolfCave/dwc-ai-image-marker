<?php
/**
 * Plugin Name: DWC AI Image Marker
 * Plugin URI: https://github.com/DarkWolfCave/dwc-ai-image-marker
 * Description: Automatically marks AI-generated images with a badge and centrally manages them in WordPress.
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

/**
 * Load required files.
 */
require_once plugin_dir_path( __FILE__ ) . 'includes/class-dwc-ai-marker-activator.php';

// Initialize the plugin.
$dwc_ai_image_marker = new Dwc_Ai_Image_Marker();

// Register activation and deactivation hooks.
register_activation_hook( __FILE__, array( 'Dwc_Ai_Marker_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'Dwc_Ai_Marker_Activator', 'deactivate' ) );

/**
 * Class Dwc_Ai_Image_Marker
 *
 * Main class for the AI Image Marker plugin.
 *
 * @package DWC_AI_Image_Marker
 */
class Dwc_Ai_Image_Marker {
	/**
	 * The current plugin version.
	 *
	 * @var string
	 */
	private $version = '1.1.0';
	/**
	 * Die gespeicherten Plugin-Optionen.
	 *
	 * @var array|null
	 */
	private $options = null;
	/**
	 * Standardwerte für Plugin-Einstellungen.
	 *
	 * @var array
	 */
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

	/**
	 * Dwc_Ai_Image_Marker constructor.
	 *
	 * Initializes the hooks for both admin and frontend.
	 */
	public function __construct() {
		// Hook for plugin initialization.
		add_action( 'init', array( $this, 'init' ) );

		// Admin hooks.
		if ( is_admin() ) {
			add_action( 'admin_menu', array( $this, 'add_admin_menu' ) );
			add_action( 'admin_init', array( $this, 'register_settings' ) );
			add_filter( 'bulk_actions-upload', array( $this, 'add_bulk_actions' ) );
			add_filter( 'handle_bulk_actions-upload', array( $this, 'handle_bulk_actions' ), 10, 3 );
			add_action( 'admin_notices', array( $this, 'bulk_action_notices' ) );
			add_filter( 'manage_upload_columns', array( $this, 'add_media_columns' ) );
			add_action( 'manage_media_custom_column', array( $this, 'manage_media_custom_column' ), 10, 2 );
			add_filter( 'manage_upload_sortable_columns', array( $this, 'register_sortable_columns' ) );
			add_action( 'pre_get_posts', array( $this, 'sort_columns' ) );

		}

		// Frontend hooks.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );
		add_filter( 'render_block', array( $this, 'handle_generateblocks_image' ), 10, 3 );
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
				echo '<span style="font-size: 16px; display: inline-block; padding-left: 5px;" title="AI-generated image">🤖</span>';
			} else {
				echo '<span style="color: #ccc; display: inline-block; padding-left: 5px;" title="Not an AI image">−</span>';
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
		if ( ! is_admin() || ! $query->is_main_query() || $query->get( 'post_type' ) !== 'attachment' ) {
			return;
		}

		$orderby = $query->get( 'orderby' );
		if ( 'ai_generated' === $orderby ) {
			$query->set( 'meta_key', '_is_ai_generated' );
			$query->set( 'orderby', 'meta_value' );
		}
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
	 * Sanitize plugin settings.
	 *
	 * @param array $input The settings input.
	 *
	 * @return array Sanitized settings.
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

		// Validate position.
		$valid_positions       = array( 'top-left', 'top-right', 'bottom-left', 'bottom-right' );
		$sanitized['position'] = in_array( $input['position'], $valid_positions, true ) ? $input['position'] : 'top-left';

		return $sanitized;
	}

	/**
	 * Enqueue styles for the frontend.
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			'dwc-ai-marker',
			$this->get_plugin_url() . 'css/dwc-ai-marker.css',
			array(),
			$this->version
		);

		// Dynamic CSS based on position.
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
			default: // top-left.
				$css .= 'left: 10px !important; right: auto !important; top: 10px !important; bottom: auto !important;';
		}
		// Add styles for new options.
		$css .= 'background-color: ' . esc_attr( $options['background_color'] ) . ' !important;';
		$css .= 'font-family: ' . esc_attr( $options['font_family'] ) . ' !important;';
		$css .= 'opacity: ' . esc_attr( $options['opacity'] ) . ' !important;';
		$css .= 'padding: ' . esc_attr( $options['padding_top'] ) . 'px ' .
				esc_attr( $options['padding_right'] ) . 'px ' .
				esc_attr( $options['padding_bottom'] ) . 'px ' .
				esc_attr( $options['padding_left'] ) . 'px !important;';
		$css .= '}';

		// Adjust hover effect based on position.
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
			default: // top-left.
				$css .= 'left: 0 !important; right: auto !important; top: 100% !important;';
		}
		$css .= '}';

		wp_add_inline_style( 'dwc-ai-marker', $css );
	}


	/**
	 * Helper function to get plugin options.
	 *
	 * @return array Plugin options.
	 */
	private function get_options() {
		if ( null === $this->options ) {
			$this->options = wp_parse_args(
				get_option( 'dwc_ai_marker_settings', array() ),
				self::$defaults
			);
		}

		return $this->options;
	}

	/**
	 * Initialize plugin functionality.
	 *
	 * Sets up custom fields for the media library and adds filters for image output.
	 * This method is called on WordPress 'init' action hook.
	 *
	 * @return void
	 * @since 1.0.0
	 */
	public function init() {
		// Custom fields for media library.
		add_filter( 'attachment_fields_to_edit', array( $this, 'add_ai_image_field' ), 10, 2 );
		add_filter( 'attachment_fields_to_save', array( $this, 'save_ai_image_field' ), 10, 2 );

		// Filter for image output.
		add_filter( 'render_block_core/image', array( $this, 'modify_image_block' ), 10, 2 );
	}

	/**
	 * Add the plugin settings page to the WordPress admin menu.
	 *
	 * Creates a new settings page under the 'Settings' menu in WordPress admin panel.
	 * Only users with 'manage_options' capability can access this page.
	 *
	 * @return void
	 * @since 1.0.0
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
	 * Render the settings page.
	 */
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
						width: 50px; /* Width for the labels */
						text-align: right;
						margin-right: 5px;
					}

					.dwc-padding-settings input {
						width: 60px; /* Width for the input fields */
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

					// Get defaults from get_options.
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
		$checkbox  = '<input type="checkbox" name="attachments[' . esc_attr( $post->ID ) . '][is_ai_generated]" value="1"';
		$checkbox .= checked( $field_value, '1', false );
		$checkbox .= '/>';

		$form_fields['is_ai_generated'] = array(
			'label' => __( 'KI-generiertes Bild', 'dwc-ai-marker' ),
			'input' => 'html',
			'html'  => $nonce_field . '<label>' . $checkbox . ' ' . esc_html__( 'Ja', 'dwc-ai-marker' ) . '</label>',
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

	/**
	 * Handle the GenerateBlocks image block.
	 *
	 * @param string $block_content Block content.
	 * @param array  $block Block data.
	 * @param array  $instance Block instance (not used).
	 *
	 * @return string Modified block content.
	 */
	public function handle_generateblocks_image( $block_content, $block, $instance ) {
		// Check if it's a GenerateBlocks image block.
		if ( isset( $block['blockName'] ) && 'generateblocks/image' === $block['blockName'] ) {
			$attributes = isset( $block['attrs'] ) ? $block['attrs'] : null;

			// Attempt to get the image ID.
			$image_id = isset( $attributes['mediaId'] ) ? $attributes['mediaId'] : null;

			if ( $image_id && get_post_meta( $image_id, '_is_ai_generated', true ) ) {
				// Suppress XML errors and use internal errors.
				$previous_value = libxml_use_internal_errors( true );

				$dom = new DOMDocument();

				// Convert content to HTML entities and load.
				$html_content = mb_convert_encoding( $block_content, 'HTML-ENTITIES', 'UTF-8' );
				$dom->loadHTML( $html_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

				// Clear any generated errors.
				libxml_clear_errors();

				// Restore previous error handling state.
				libxml_use_internal_errors( $previous_value );

				// Find the img element.
				$img = $dom->getElementsByTagName( 'img' )->item( 0 );
				if ( $img ) {
					// Create a wrapper.
					$wrapper = $dom->createElement( 'div' );
					$wrapper->setAttribute( 'class', 'ai-image-wrapper' );

					// Add the badge.
					$badge = $dom->createElement( 'div' );
					$badge->setAttribute( 'class', 'ai-image-badge' );
					$options    = get_option( 'dwc_ai_marker_settings' );
					$badge_text = isset( $options['badge_text'] ) ? $options['badge_text'] : 'AI-generated';
					$badge->appendChild( $dom->createTextNode( $badge_text ) );

					// Get parent node and perform the insertion.
					$img->parentNode->insertBefore( $wrapper, $img );
					$wrapper->appendChild( $badge );
					$wrapper->appendChild( $img );

					$block_content = $dom->saveHTML();
				}
			}
		}

		return $block_content;
	}

	/**
	 * Modify the image block.
	 *
	 * @param string $block_content Block content.
	 * @param array  $block Block data.
	 *
	 * @return string Modified block content.
	 */
	public function modify_image_block( $block_content, $block ) {
		$image_id = isset( $block['attrs']['id'] ) ? $block['attrs']['id'] : null;

		if ( $image_id && get_post_meta( $image_id, '_is_ai_generated', true ) ) {
			// Suppress XML errors and use internal errors.
			$previous_value = libxml_use_internal_errors( true );

			$dom = new DOMDocument();

			// Convert content to HTML entities and load.
			$html_content = mb_convert_encoding( $block_content, 'HTML-ENTITIES', 'UTF-8' );
			$dom->loadHTML( $html_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );

			// Clear any generated errors.
			libxml_clear_errors();

			// Restore previous error handling state.
			libxml_use_internal_errors( $previous_value );

			// Find the img element.
			$img = $dom->getElementsByTagName( 'img' )->item( 0 );
			if ( $img ) {
				// Create a wrapper.
				$wrapper = $dom->createElement( 'div' );
				$wrapper->setAttribute( 'class', 'ai-image-wrapper' );

				// Add the badge.
				$badge = $dom->createElement( 'div' );
				$badge->setAttribute( 'class', 'ai-image-badge' );
				$options    = get_option( 'dwc_ai_marker_settings' );
				$badge_text = isset( $options['badge_text'] ) ? $options['badge_text'] : 'AI-generated';
				$badge->appendChild( $dom->createTextNode( $badge_text ) );

				// Get parent node and perform the insertion.
				$parent_node = $img->parentNode;
				$parent_node->insertBefore( $wrapper, $img );
				$wrapper->appendChild( $badge );
				$wrapper->appendChild( $img );

				$block_content = $dom->saveHTML();
			}
		}

		return $block_content;
	}

	/**
	 * Helper function to get the plugin URL.
	 *
	 * @return string Plugin URL.
	 */
	private function get_plugin_url() {
		return plugin_dir_url( __FILE__ );
	}

	/**
	 * Helper function to get the plugin path.
	 *
	 * @return string Plugin path.
	 */
	private function get_plugin_path() {
		return plugin_dir_path( __FILE__ );
	}
}
