<?php
/**
 * Hilfsfunktionen für das Plugin
 *
 * @package DWC_AI_Image_Marker
 * @subpackage DWC_AI_Image_Marker/includes
 */

// Sicherheitscheck.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Dwc_Ai_Marker_Utils
 *
 * Enthält allgemeine Hilfsfunktionen für das Plugin
 *
 * @since 1.1.0
 */
class Dwc_Ai_Marker_Utils {

	/**
	 * Standardwerte für Plugin-Einstellungen.
	 *
	 * @return array
	 */
	public static function get_defaults(): array {
		return array(
			'badge_text'       => 'KI-generiert',
			'position'         => 'top-left',
			'background_color' => '#000000',
			'font_family'      => '-apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif',
			'opacity'          => 0.7,
			'padding_top'      => 5,
			'padding_right'    => 10,
			'padding_bottom'   => 5,
			'padding_left'     => 10,
			'debug_enabled'    => false,
		);
	}

	/**
	 * Prüfen, ob ein Bild KI-generiert ist.
	 *
	 * @param int $image_id Die Bild-ID.
	 *
	 * @return bool True, wenn KI-generiert, sonst false.
	 */
	public function is_ai_generated( int $image_id ): bool {
		static $ai_cache = array();

		// Cache-Lookup für wiederholte Prüfungen.
		if ( isset( $ai_cache[ $image_id ] ) ) {
			return $ai_cache[ $image_id ];
		}

		$is_ai                 = get_post_meta( $image_id, '_is_ai_generated', true );
		$ai_cache[ $image_id ] = (bool) $is_ai;

		return (bool) $is_ai;
	}

	/**
	 * Holt die Attachment-ID aus einer URL.
	 *
	 * @param string $url Die URL des Bildes.
	 *
	 * @return int Die Bild-ID oder 0, wenn keine gefunden wurde.
	 */
	public function get_id_from_url( string $url ): int {
		static $url_cache = array();

		// Cache-Lookup für wiederholte URLs.
		if ( isset( $url_cache[ $url ] ) ) {
			return $url_cache[ $url ];
		}

		$id                = attachment_url_to_postid( $url );
		$url_cache[ $url ] = $id;

		return $id;
	}

	/**
	 * Extrahiert die Bild-ID aus einem img-Tag.
	 *
	 * @param string $img_tag Das img-Tag.
	 *
	 * @return int Die Bild-ID oder 0, wenn keine gefunden wurde.
	 */
	public function extract_image_id_from_tag( string $img_tag ): int {
		static $id_cache = array();

		// Cache-Lookup für wiederholte Tags.
		$tag_hash = md5( $img_tag );
		if ( isset( $id_cache[ $tag_hash ] ) ) {
			return $id_cache[ $tag_hash ];
		}

		$image_id = 0;

		// Suche nach data-image-id Attribut (GenerateBlocks).
		if ( preg_match( '/data-image-id="(\d+)"/i', $img_tag, $id_match ) ) {
			$image_id = intval( $id_match[1] );
		}

		// Suche nach data-id Attribut.
		if ( 0 === $image_id && preg_match( '/data-id="(\d+)"/i', $img_tag, $id_match ) ) {
			$image_id = intval( $id_match[1] );
		}

		// Suche nach class mit wp-image-ID.
		if ( 0 === $image_id && preg_match( '/class="[^"]*wp-image-(\d+)[^"]*"/i', $img_tag, $class_match ) ) {
			$image_id = intval( $class_match[1] );
		}

		// Wenn keine ID gefunden wurde, versuche es mit der URL.
		if ( 0 === $image_id ) {
			// Regular expression to find image src.
			if ( preg_match( '/src="([^"]+)"/i', $img_tag, $attributes ) ) {
				$image_url = $attributes[1];
				$image_id  = $this->get_id_from_url( $image_url );
			}
		}

		// Speichere im Cache.
		$id_cache[ $tag_hash ] = $image_id;

		return $image_id;
	}

	/**
	 * Fügt ein Badge zu einem Container hinzu.
	 *
	 * @param string $container_html Der Container-HTML-Code.
	 *
	 * @return string Der modifizierte Container-HTML-Code.
	 */
	public function add_badge_to_container( string $container_html ): string {
		// Stelle sicher, dass das Badge noch nicht hinzugefügt wurde.
		if ( strpos( $container_html, 'ai-image-badge' ) !== false ) {
			return $container_html;
		}

		$options    = dwc_ai_marker()->get_options();
		$badge_text = $options['badge_text'] ?? 'KI-generiert';

		// Badge direkt in den Container einfügen.
		$badge_html = '<div class="ai-image-badge">' . esc_html( $badge_text ) . '</div>';

		// Füge das Badge nach dem öffnenden <div> ein.
		$pos = strpos( $container_html, '>' );
		if ( false !== $pos ) {
			$container_html = substr_replace( $container_html, '>' . $badge_html, $pos, 1 );
		}

		return $container_html;
	}
}
