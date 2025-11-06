<?php
/**
 * Funktionen für das Frontend des Plugins.
 *
 * @package DWC_AI_Image_Marker
 * @subpackage DWC_AI_Image_Marker/public
 */

// Sicherheitscheck.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Dwc_Ai_Marker_Frontend
 *
 * Verwaltet die Frontend-Funktionen des Plugins, insbesondere die Bildererkennung und Badging.
 *
 * @since 1.1.0
 */
class Dwc_Ai_Marker_Frontend {

	/**
	 * Initialisiert die Frontend-Funktionen und Hooks.
	 */
	public function __construct() {
		// CSS für das Frontend.
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		// Blockfilter Hooks.
		add_action( 'init', array( $this, 'init_hooks' ) );

		// Filter für den gesamten Seiteninhalt.
		add_filter( 'the_content', array( $this, 'process_content_images' ), 99 );

		// Filter für GeneratePress Inhalte.
		add_filter( 'generate_after_entry_content', array( $this, 'process_content_images' ), 99 );

		// Zusätzliche Filter für GenerateBlocks.
		add_filter( 'generateblocks_render_block_content', array( $this, 'process_content_images' ), 99 );
		add_filter( 'generateblocks_after_container_content', array( $this, 'process_content_images' ), 99 );

		// Filter für Figure-Tags und post-thumbnails.
		add_filter( 'post_thumbnail_html', array( $this, 'process_thumbnail_html' ), 99, 5 );
		add_filter( 'get_image_tag', array( $this, 'process_image_tag' ), 99, 6 );

		// GeneratePress spezifische Filter.
		add_filter( 'generate_blog_image', array( $this, 'process_generatepress_blog_image' ), 99, 2 );

		// Benutzerdefinierte Loop-Query Filter.
		add_filter( 'the_post_thumbnail', array( $this, 'process_thumbnail_html' ), 99, 5 );
		add_filter( 'wp_get_attachment_image', array( $this, 'process_attachment_image' ), 99, 5 );

		// Zusätzliche Filter für benutzerdefinierte Loops.
		add_filter( 'wp_get_attachment_image_attributes', array( $this, 'maybe_add_ai_image_class' ), 99, 3 );

		// Elementor-spezifische Filter.
		add_action( 'init', array( $this, 'init_elementor_hooks' ) );
	}

	/**
	 * Initialisiert weitere Hooks für Block-Filter.
	 *
	 * @return void
	 */
	public function init_hooks() {
		// Filter für WordPress Core-Blöcke und GenerateBlocks.
		add_filter( 'render_block_core/image', array( $this, 'modify_image_block' ), 10, 2 );
		add_filter( 'render_block', array( $this, 'handle_generateblocks_image' ), 10, 3 );
		add_filter( 'render_block_generateblocks/container', array( $this, 'process_content_images' ), 10, 2 );
		add_filter(
			'render_block_generateblocks/container',
			array(
				$this,
				'process_generateblocks_container_background',
			),
			10,
			2
		);
		add_filter( 'render_block_generateblocks/image', array( $this, 'process_generate_blocks_image' ), 10, 2 );
	}

	/**
	 * Initialisiert Elementor-spezifische Hooks.
	 *
	 * @return void
	 */
	public function init_elementor_hooks() {
		// Prüfe, ob Elementor aktiv ist.
		if ( ! defined( 'ELEMENTOR_VERSION' ) ) {
			return;
		}

		// Filter für Elementor Widget Content - mit späterer Priorität, damit Elementor fertig gerendert hat.
		add_filter( 'elementor/widget/render_content', array( $this, 'process_elementor_widget_content' ), 99, 2 );

		// Filter für Elementor Frontend Output.
		add_action( 'elementor/frontend/after_render', array( $this, 'process_elementor_section' ), 10, 1 );
	}

	/**
	 * Enqueue styles for the frontend.
	 *
	 * Registriert und lädt die CSS-Stile für das Plugin-Frontend.
	 * Ab Version 1.2.0 beinhaltet dies spezifische Anpassungen für Badges innerhalb der
	 * DWC_AI_Image_Marker-Klasse, mit besonderer Positionierung.
	 *
	 * @return void
	 * @since 1.1.0
	 * @updated 1.2.0 Spezifische CSS-Anpassungen für DWC_AI_Image_Marker hinzugefügt.
	 */
	public function enqueue_styles() {
		wp_enqueue_style(
			'dwc-ai-marker',
			DWC_AI_MARKER_PLUGIN_URL . 'assets/css/dwc-ai-marker.css',
			array(),
			DWC_AI_MARKER_VERSION
		);

		// Dynamic CSS based on position.
		$options  = dwc_ai_marker()->get_options();
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
		$css .= 'z-index: 999 !important;';
		$css .= 'color: #fff !important;';
		$css .= 'font-size: 12px !important;';
		$css .= 'position: absolute !important;';
		$css .= 'cursor: help !important;';
		$css .= 'border-radius: 3px !important;';
		$css .= '}';

		// Anpassung für Container mit Hintergrundbildern.
		$css .= '.gb-container-link + .ai-image-badge, .gb-has-dynamic-bg > .ai-image-badge {';
		$css .= 'position: absolute !important;';
		$css .= 'z-index: 999 !important;';
		$css .= '}';

		// Spezielle Anpassung für Badges in der DWC_AI_Image_Marker-Klasse.
		$css .= '.DWC_AI_Image_Marker .ai-image-badge {';
		$css .= 'top: 30px !important;'; // Etwas tiefer.
		$css .= 'left: 20px !important;'; // Etwas mehr nach rechts.
		$css .= '}';

		// Elementor-spezifische Anpassungen.
		$css .= '.elementor-widget-container .ai-image-wrapper {';
		$css .= 'position: relative !important;';
		$css .= 'display: inline-block !important;';
		$css .= '}';

		$css .= '.elementor-element .ai-image-badge {';
		$css .= 'position: absolute !important;';
		$css .= 'z-index: 999 !important;';
		$css .= 'display: block !important;';
		$css .= 'visibility: visible !important;';
		$css .= 'opacity: 1 !important;';
		$css .= '}';

		// Stelle sicher, dass Container mit Hintergrundbildern relative Position haben.
		$css .= '.elementor-element[style*="background-image"] {';
		$css .= 'position: relative !important;';
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
	 * Handle the GenerateBlocks image block.
	 *
	 * @param string $block_content Block content.
	 * @param array  $block Block data.
	 * @param array  $instance Block instance (unused but required for the filter).
	 *
	 * @return string Modified block content.
	 */
	public function handle_generateblocks_image( $block_content, $block, $instance ) {
		// Check if it's a GenerateBlocks image block.
		if ( isset( $block['blockName'] ) && 'generateblocks/image' === $block['blockName'] ) {
			$attributes = isset( $block['attrs'] ) ? $block['attrs'] : null;

			// Attempt to get the image ID.
			$image_id = isset( $attributes['mediaId'] ) ? $attributes['mediaId'] : null;

			if ( $image_id && dwc_ai_marker()->utils->is_ai_generated( $image_id ) ) {
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
					$options    = dwc_ai_marker()->get_options();
					$badge_text = isset( $options['badge_text'] ) ? $options['badge_text'] : 'AI-generated';
					$badge->appendChild( $dom->createTextNode( $badge_text ) );

					// Get parent node and perform the insertion.
					// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
					$img->parentNode->insertBefore( $wrapper, $img );
					// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
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

		if ( $image_id && dwc_ai_marker()->utils->is_ai_generated( $image_id ) ) {
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
				$options    = dwc_ai_marker()->get_options();
				$badge_text = isset( $options['badge_text'] ) ? $options['badge_text'] : 'AI-generated';
				$badge->appendChild( $dom->createTextNode( $badge_text ) );

				// Get parent node and perform the insertion.
				// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$parent_node = $img->parentNode;
				// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$parent_node->insertBefore( $wrapper, $img );
				$wrapper->appendChild( $badge );
				$wrapper->appendChild( $img );

				$block_content = $dom->saveHTML();
			}
		}

		return $block_content;
	}

	/**
	 * Process content images.
	 *
	 * @param string $content The content to process.
	 * @param array  $block Optional. Block data when called as a block filter. Default null.
	 *
	 * @return string Modified content.
	 */
	public function process_content_images( $content, $block = null ) {
		// Schnelle Prüfung, ob Verarbeitung notwendig ist.
		if ( empty( $content ) ) {
			return $content;
		}

		// Prüfe auf CSS-Hintergrundbilder mit --background-url.
		if ( false !== strpos( $content, '--background-url:url(' ) ) {
			preg_match_all( '/--background-url:url\((.*?)\)/', $content, $bg_matches );

			if ( isset( $bg_matches[1] ) && ! empty( $bg_matches[1] ) ) {
				foreach ( $bg_matches[1] as $key => $bg_url ) {
					$background_url = trim( $bg_url, '\'"' );
					$image_id       = dwc_ai_marker()->utils->get_id_from_url( $background_url );

					if ( $image_id && dwc_ai_marker()->utils->is_ai_generated( $image_id ) ) {
						// Finde den Container mit diesem Hintergrundbild.
						$container_pattern = '/<div[^>]*--background-url:url\(' . preg_quote( $bg_url, '/' ) . '\)[^>]*>(.*?)<\/div>/s';
						preg_match( $container_pattern, $content, $container_match );

						if ( isset( $container_match[0] ) && false === strpos( $container_match[0], 'ai-image-badge' ) ) {
							$options    = dwc_ai_marker()->get_options();
							$badge_text = isset( $options['badge_text'] ) ? $options['badge_text'] : 'KI-generiert';

							// Erstelle ein Badge und füge es in den Container ein.
							$badge_html = '<div class="ai-image-badge">' . esc_html( $badge_text ) . '</div>';

							// Ersetze das erste > des Containers mit >Badge.
							$pos = strpos( $container_match[0], '>' );
							if ( false !== $pos ) {
								$new_container = substr_replace( $container_match[0], '>' . $badge_html, $pos, 1 );
								$content       = str_replace( $container_match[0], $new_container, $content );
							}
						}
					}
				}
			}
		}

		// Wenn keine <img> Tags vorhanden sind, können wir hier abbrechen.
		if ( false === strpos( $content, '<img' ) ) {
			return $content;
		}

		// Regular expression to find image tags - optimiert für weniger Aufwand.
		$pattern = '/<img[^>]+>/i';
		preg_match_all( $pattern, $content, $matches );

		if ( ! empty( $matches[0] ) ) {
			// Begrenze die Anzahl der zu verarbeitenden Bilder für bessere Performance.
			$max_images = min( count( $matches[0] ), 25 );  // maximal 25 Bilder pro Seite verarbeiten.

			for ( $i = 0; $i < $max_images; $i++ ) {
				$img_tag = $matches[0][ $i ];

				// Schnelle Prüfung, ob das Bild bereits verarbeitet wurde.
				if ( false !== strpos( $img_tag, 'ai-image-badge' ) ) {
					continue;
				}

				// Versuche, eine Bild-ID zu finden.
				$image_id = dwc_ai_marker()->utils->extract_image_id_from_tag( $img_tag );

				// Wenn wir eine gültige ID haben, prüfen wir, ob es KI-generiert ist.
				if ( $image_id > 0 && dwc_ai_marker()->utils->is_ai_generated( $image_id ) ) {
					// Stelle sicher, dass das Bild nicht bereits in einem ai-image-wrapper ist.
					if ( false === strpos( $content, '<div class="ai-image-wrapper">' . $img_tag . '</div>' ) &&
						false === strpos( $content, '<div class="ai-image-wrapper">' . PHP_EOL . $img_tag ) ) {

						$options    = dwc_ai_marker()->get_options();
						$badge_text = isset( $options['badge_text'] ) ? $options['badge_text'] : 'KI-generiert';

						// Ersetze das Bild-Tag mit AI-generiert Badge.
						$new_img_tag = '<div class="ai-image-wrapper"><div class="ai-image-badge">' .
										esc_html( $badge_text ) .
										'</div>' . $img_tag . '</div>';

						$content = str_replace( $img_tag, $new_img_tag, $content );
					}
				}
			}
		}

		return $content;
	}

	/**
	 * Process GenerateBlocks image block.
	 *
	 * @param string $block_content Block content.
	 * @param array  $block Block data.
	 *
	 * @return string Modified block content.
	 */
	public function process_generate_blocks_image( $block_content, $block ) {
		// Schnelle Prüfung, ob ein Badge bereits vorhanden ist.
		if ( false !== strpos( $block_content, 'ai-image-badge' ) ) {
			return $block_content;
		}

		// Versuche, die Bild-ID direkt aus den Block-Attributen zu erhalten.
		$image_id = isset( $block['attrs']['mediaId'] ) ? $block['attrs']['mediaId'] : null;

		if ( $image_id && dwc_ai_marker()->utils->is_ai_generated( $image_id ) ) {
			$options    = dwc_ai_marker()->get_options();
			$badge_text = isset( $options['badge_text'] ) ? $options['badge_text'] : 'KI-generiert';

			// DOMDocument-Operationen sind teuer, versuche eine einfachere Methode.
			if ( false !== strpos( $block_content, '<img' ) && false === strpos( $block_content, '</img>' ) ) {
				// Einfache Replacement-Strategie für einfache Fälle.
				$badge_html  = '<div class="ai-image-badge">' . esc_html( $badge_text ) . '</div>';
				$img_pattern = '/(<img[^>]+>)/i';
				$replacement = '<div class="ai-image-wrapper">' . $badge_html . '$1</div>';
				$new_content = preg_replace( $img_pattern, $replacement, $block_content, 1 );

				if ( $new_content !== $block_content ) {
					return $new_content;
				}
			}

			// Fallback auf DOMDocument, wenn nötig.
			$previous_value = libxml_use_internal_errors( true );
			$dom            = new DOMDocument();
			$html_content   = mb_convert_encoding( $block_content, 'HTML-ENTITIES', 'UTF-8' );
			$dom->loadHTML( $html_content, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD );
			libxml_clear_errors();
			libxml_use_internal_errors( $previous_value );

			// Finde das img-Element.
			$img = $dom->getElementsByTagName( 'img' )->item( 0 );
			if ( $img ) {
				// Erstelle einen Wrapper.
				$wrapper = $dom->createElement( 'div' );
				$wrapper->setAttribute( 'class', 'ai-image-wrapper' );

				// Füge das Badge hinzu.
				$badge = $dom->createElement( 'div' );
				$badge->setAttribute( 'class', 'ai-image-badge' );
				$badge->appendChild( $dom->createTextNode( $badge_text ) );

				// Erfasse das Elternelement und füge den Wrapper ein.
				// phpcs:disable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$parent_node = $img->parentNode;
				// phpcs:enable WordPress.NamingConventions.ValidVariableName.UsedPropertyNotSnakeCase
				$parent_node->insertBefore( $wrapper, $img );
				$wrapper->appendChild( $badge );
				$wrapper->appendChild( $img );

				$block_content = $dom->saveHTML();
			}
		}

		return $block_content;
	}

	/**
	 * Process GenerateBlocks container with background image.
	 *
	 * @param string $block_content Block content.
	 * @param array  $block Block data.
	 *
	 * @return string Modified block content.
	 */
	public function process_generateblocks_container_background( $block_content, $block ) {
		// Schnelle Prüfung, ob Badges schon vorhanden sind.
		if ( false !== strpos( $block_content, 'ai-image-badge' ) ) {
			return $block_content;
		}

		// Überprüfe, ob der Container einen Hintergrund hat.
		if ( isset( $block['attrs']['backgroundImage'] ) && ! empty( $block['attrs']['backgroundImage'] ) ) {
			// Extrahiere die Bild-URL.
			$background_image = $block['attrs']['backgroundImage'];

			// Wenn eine Bild-ID vorhanden ist.
			if ( isset( $background_image['id'] ) && $background_image['id'] > 0 ) {
				$image_id = intval( $background_image['id'] );

				// Überprüfe, ob das Bild KI-generiert ist.
				if ( dwc_ai_marker()->utils->is_ai_generated( $image_id ) ) {
					return dwc_ai_marker()->utils->add_badge_to_container( $block_content );
				}
			} elseif ( isset( $background_image['url'] ) && ! empty( $background_image['url'] ) ) {
				// Wenn keine ID, aber eine URL vorhanden ist, versuche die ID zu bestimmen.
				$image_id = dwc_ai_marker()->utils->get_id_from_url( $background_image['url'] );

				if ( $image_id && dwc_ai_marker()->utils->is_ai_generated( $image_id ) ) {
					return dwc_ai_marker()->utils->add_badge_to_container( $block_content );
				}
			}
		}

		// Prüfe auch CSS-Hintergrundbilder mit --background-url.
		if ( false !== strpos( $block_content, '--background-url:url(' ) ) {
			preg_match( '/--background-url:url\((.*?)\)/', $block_content, $matches );

			if ( isset( $matches[1] ) ) {
				$background_url = trim( $matches[1], '\'"' );
				$image_id       = dwc_ai_marker()->utils->get_id_from_url( $background_url );

				if ( $image_id && dwc_ai_marker()->utils->is_ai_generated( $image_id ) ) {
					return dwc_ai_marker()->utils->add_badge_to_container( $block_content );
				}
			}
		}

		return $block_content;
	}

	/**
	 * Process attachment image HTML.
	 *
	 * @param string $html HTML content.
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size Image size.
	 * @param bool   $icon Icon.
	 * @param array  $attr Attributes.
	 *
	 * @return string Modified HTML content.
	 */
	public function process_attachment_image( $html, $attachment_id, $size, $icon, $attr ) {
		// Schnelle Prüfung, ob bereits markiert.
		if ( false !== strpos( $html, 'ai-image-badge' ) ) {
			return $html;
		}

		if ( $attachment_id && dwc_ai_marker()->utils->is_ai_generated( $attachment_id ) ) {
			$options    = dwc_ai_marker()->get_options();
			$badge_text = isset( $options['badge_text'] ) ? $options['badge_text'] : 'KI-generiert';

			// Schnelles String-Wrapping ohne DOM-Manipulation.
			$html = '<div class="ai-image-wrapper"><div class="ai-image-badge">' . esc_html( $badge_text ) . '</div>' . $html . '</div>';
		}

		return $html;
	}

	/**
	 * Process thumbnail HTML.
	 *
	 * @param string $html HTML content.
	 * @param int    $post_id Post ID. Default 0.
	 * @param string $post_thumbnail Post thumbnail string. Default empty string.
	 * @param array  $attr Attributes array. Default empty array.
	 * @param string $size Image size string. Default empty string.
	 *
	 * @return string Modified HTML content.
	 */
	public function process_thumbnail_html( $html, $post_id = 0, $post_thumbnail = '', $attr = array(), $size = '' ) {
		// Schnelle Prüfung, ob bereits markiert.
		if ( false !== strpos( $html, 'ai-image-badge' ) ) {
			return $html;
		}

		// Versuche, eine Bild-ID zu finden.
		$image_id = 0;

		// Wenn post_id gegeben ist, versuche thumbnail_id zu bekommen.
		if ( $post_id ) {
			$image_id = get_post_thumbnail_id( $post_id );
		}

		// Wenn noch keine ID, versuche aus HTML zu extrahieren.
		if ( ! $image_id ) {
			$image_id = dwc_ai_marker()->utils->extract_image_id_from_tag( $html );
		}

		// Überprüfe, ob das Bild als KI-generiert markiert ist.
		if ( $image_id && dwc_ai_marker()->utils->is_ai_generated( $image_id ) ) {
			$options    = dwc_ai_marker()->get_options();
			$badge_text = isset( $options['badge_text'] ) ? $options['badge_text'] : 'KI-generiert';

			// Schnelles String-Wrapping ohne DOM-Manipulation.
			$html = '<div class="ai-image-wrapper"><div class="ai-image-badge">' . esc_html( $badge_text ) . '</div>' . $html . '</div>';
		}

		return $html;
	}

	/**
	 * Process image tag.
	 *
	 * @param string $html HTML content.
	 * @param int    $id Image ID.
	 * @param string $alt Alt text.
	 * @param string $title Title text.
	 * @param string $align Alignment.
	 * @param string $size Image size.
	 *
	 * @return string Modified HTML content.
	 */
	public function process_image_tag( $html, $id, $alt, $title, $align, $size ) {
		// Schnelle Prüfung, ob bereits markiert.
		if ( false !== strpos( $html, 'ai-image-badge' ) ) {
			return $html;
		}

		if ( $id && dwc_ai_marker()->utils->is_ai_generated( $id ) ) {
			$options    = dwc_ai_marker()->get_options();
			$badge_text = isset( $options['badge_text'] ) ? $options['badge_text'] : 'KI-generiert';

			// Schnelles String-Wrapping ohne DOM-Manipulation.
			$html = '<div class="ai-image-wrapper"><div class="ai-image-badge">' . esc_html( $badge_text ) . '</div>' . $html . '</div>';
		}

		return $html;
	}

	/**
	 * Process GeneratePress blog image.
	 *
	 * @param string $image_html Image HTML.
	 * @param int    $post_id Post ID.
	 *
	 * @return string Modified image HTML.
	 */
	public function process_generatepress_blog_image( $image_html, $post_id ) {
		// Schnelle Prüfung, ob bereits markiert.
		if ( false !== strpos( $image_html, 'ai-image-badge' ) ) {
			return $image_html;
		}

		// Versuche, die Bild-ID aus dem Image-HTML zu extrahieren.
		$image_id = 0;

		// Versuche es mit der post_thumbnail_id.
		if ( $post_id && has_post_thumbnail( $post_id ) ) {
			$image_id = get_post_thumbnail_id( $post_id );
		}

		// Wenn keine ID gefunden wurde, versuche aus dem HTML zu extrahieren.
		if ( ! $image_id && preg_match( '/class="[^"]*wp-image-(\d+)[^"]*"/i', $image_html, $class_match ) ) {
			$image_id = intval( $class_match[1] );
		}

		// Überprüfe, ob das Bild als KI-generiert markiert ist.
		if ( $image_id && dwc_ai_marker()->utils->is_ai_generated( $image_id ) ) {
			$options    = dwc_ai_marker()->get_options();
			$badge_text = isset( $options['badge_text'] ) ? $options['badge_text'] : 'KI-generiert';

			// Schnelles String-Wrapping ohne DOM-Manipulation.
			$image_html = '<div class="ai-image-wrapper"><div class="ai-image-badge">' .
							esc_html( $badge_text ) .
							'</div>' . $image_html . '</div>';
		}

		return $image_html;
	}

	/**
	 * Fügt eine CSS-Klasse zu KI-generierten Bildern hinzu, damit sie per JavaScript identifiziert werden können.
	 *
	 * @param array  $attr Bildattribute.
	 * @param object $attachment Das Attachment-Objekt.
	 * @param string $size Die Bildgröße.
	 *
	 * @return array Modifizierte Bildattribute.
	 */
	public function maybe_add_ai_image_class( $attr, $attachment, $size ) {
		// Überprüfe, ob das Bild als KI-generiert markiert ist.
		if ( $attachment->ID && dwc_ai_marker()->utils->is_ai_generated( $attachment->ID ) ) {
			// Füge CSS-Klasse für KI-generierte Bilder hinzu.
			if ( isset( $attr['class'] ) ) {
				$attr['class'] .= ' ai-generated-image';
			} else {
				$attr['class'] = 'ai-generated-image';
			}

			// Füge ein Datenattribut hinzu.
			$attr['data-ai-generated'] = 'true';
		}

		return $attr;
	}

	/**
	 * Verarbeitet Elementor Widget Content.
	 *
	 * @param string $widget_content Der Widget-Content.
	 * @param object $widget Das Widget-Objekt.
	 *
	 * @return string Modifizierter Widget-Content.
	 */
	public function process_elementor_widget_content( $widget_content, $widget ) {
		// Nur für Image-Widgets verarbeiten.
		if ( 'image' !== $widget->get_name() ) {
			return $widget_content;
		}

		// Schnelle Prüfung, ob bereits verarbeitet.
		if ( false !== strpos( $widget_content, 'ai-image-badge' ) ) {
			return $widget_content;
		}

		// Versuche, die Bild-ID aus den Widget-Einstellungen zu erhalten.
		$settings = $widget->get_settings_for_display();
		$image_id = isset( $settings['image']['id'] ) ? intval( $settings['image']['id'] ) : 0;

		// Wenn keine ID in den Einstellungen, versuche aus dem HTML zu extrahieren.
		if ( 0 === $image_id ) {
			$image_id = dwc_ai_marker()->utils->extract_image_id_from_tag( $widget_content );
		}

		// Überprüfe, ob das Bild KI-generiert ist.
		if ( $image_id > 0 && dwc_ai_marker()->utils->is_ai_generated( $image_id ) ) {
			$options    = dwc_ai_marker()->get_options();
			$badge_text = isset( $options['badge_text'] ) ? $options['badge_text'] : 'KI-generiert';

			// Prüfe, ob das Bild bereits in einem Wrapper ist.
			if ( false === strpos( $widget_content, 'ai-image-wrapper' ) ) {
				// Finde das img-Tag und umschließe es.
				$pattern     = '/(<img[^>]+>)/i';
				$replacement = '<div class="ai-image-wrapper"><div class="ai-image-badge">' . esc_html( $badge_text ) . '</div>$1</div>';
				$widget_content = preg_replace( $pattern, $replacement, $widget_content, 1 );
			}
		}

		return $widget_content;
	}

	/**
	 * Verarbeitet Elementor Section nach dem Rendern.
	 *
	 * @param object $element Das Element-Objekt.
	 *
	 * @return void
	 */
	public function process_elementor_section( $element ) {
		// Diese Methode wird für zukünftige Erweiterungen verwendet.
		// Aktuell wird die Verarbeitung über JavaScript durchgeführt.
	}
}
