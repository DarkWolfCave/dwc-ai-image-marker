<?php
/**
 * Hintergrundverarbeitung für das Plugin
 *
 * @package DWC_AI_Image_Marker
 * @subpackage DWC_AI_Image_Marker/public
 */

// Sicherheitscheck
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Dwc_Ai_Marker_Background
 *
 * Verwaltet die Verarbeitung von Hintergrundbildern und JavaScript-Erkennung
 *
 * @since 1.1.0
 */
class Dwc_Ai_Marker_Background {

	/**
	 * Initialisiert die Hintergrundverarbeitung.
	 */
	public function __construct() {
		// Footer-Hook für JavaScript-Anpassungen
		add_action( 'wp_footer', array( $this, 'add_dynamic_background_handler' ), 99 );
	}

	/**
	 * Dynamische Hintergrundverarbeitung mit JavaScript hinzufügen.
	 * 
	 * Erkennt KI-generierte Hintergrundbilder mittels JavaScript und fügt entsprechende Badges hinzu.
	 * In Version 1.2.0 wurden die folgenden Verbesserungen implementiert:
	 * - Optimierte Datenbank-Abfragen mit Caching
	 * - Verbesserte KI-Bild-Erkennung mit mehreren Methoden
	 * - Bessere Leistung durch Begrenzung der zu verarbeitenden Bilder
	 * - Debug-Modus, der über die Plugin-Einstellungen gesteuert wird
	 * - Spezielle Behandlung für Bilder innerhalb der DWC_AI_Image_Marker-Klasse
	 *
	 * @since 1.1.0
	 * @updated 1.2.0 Verbesserte KI-Erkennung und Debug-Modus hinzugefügt
	 * @updated 1.2.1 Robustere Bildererkennung mit Wiederholungslogik für konsistente Badge-Anzeige
	 * 
	 * @return void
	 */
	public function add_dynamic_background_handler() {
		// Schnellprüfung, ob überhaupt KI-Bilder im System sind
		global $wpdb;
		$count = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = '1'",
				'_is_ai_generated'
			)
		);

		if ( empty( $count ) || $count == 0 ) {
			return;
		}

		// Caching der KI-IDs in einer Transient (24 Stunden gültig)
		$ai_image_ids = get_transient( 'dwc_ai_marker_image_ids' );
		
		if ( false === $ai_image_ids ) {
			$ai_image_ids = $wpdb->get_col(
				$wpdb->prepare(
					"SELECT post_id FROM $wpdb->postmeta WHERE meta_key = %s AND meta_value = '1'",
					'_is_ai_generated'
				)
			);
			set_transient( 'dwc_ai_marker_image_ids', $ai_image_ids, DAY_IN_SECONDS );
		}

		if ( empty( $ai_image_ids ) ) {
			return;
		}

		// Liste der bekannten KI-Bildnamen abfragen
		$known_ai_images = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT pm.meta_value FROM $wpdb->postmeta pm 
				JOIN $wpdb->posts p ON p.ID = pm.post_id
				JOIN $wpdb->postmeta pm2 ON pm2.post_id = p.ID AND pm2.meta_key = %s AND pm2.meta_value = '1'
				WHERE pm.meta_key = '_wp_attached_file'",
				'_is_ai_generated'
			)
		);
		
		// Hole alle an KI-Bilder angehängten Attachments
		$ai_image_attachments = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT p.ID, p.guid, pm.meta_value as file_path 
				FROM $wpdb->posts p
				JOIN $wpdb->postmeta pm ON p.ID = pm.post_id AND pm.meta_key = '_wp_attached_file'
				JOIN $wpdb->postmeta pm2 ON p.ID = pm2.post_id AND pm2.meta_key = %s AND pm2.meta_value = '1'
				WHERE p.post_type = 'attachment'",
				'_is_ai_generated'
			),
			ARRAY_A
		);

		$options = dwc_ai_marker()->get_options();
		$debug_enabled = isset( $options['debug_enabled'] ) ? $options['debug_enabled'] : false;
		
		?>
		<script>
		(function() {
			// Globale Variable für verarbeitete Elemente um Duplikate zu vermeiden
			let processedElements = new WeakSet();
			let processedCount = 0;
			const maxToProcess = 200; // Erhöht auf 200 für mehr Bilder pro Durchlauf
			
			// Führe die Verarbeitung mehrfach durch, um sicherzustellen, dass alle Bilder erfasst werden
			let processAttempts = 0;
			const maxAttempts = 3;
			
			// Hauptfunktion zur Verarbeitung aller Bilder
			function processImages() {
				// Liste der KI-generierten Bild-IDs
				const aiImageIds = <?php echo wp_json_encode( $ai_image_ids ); ?>;
				
				// Liste der bekannten KI-Bildnamen (Dateinamen)
				const knownAiImageNames = <?php echo wp_json_encode( $known_ai_images ); ?>;
				
				// Liste aller KI-Bild-Attachments mit vollständigen Informationen
				const aiImageAttachments = <?php echo wp_json_encode( $ai_image_attachments ); ?>;
				
				// Debug-Modus-Einstellung aus den Plugin-Optionen
				const debugEnabled = <?php echo $debug_enabled ? 'true' : 'false'; ?>;
				
				// Map für schnellen Lookup nach Teilen des Dateinamens
				const aiFilePathMap = {};
				aiImageAttachments.forEach(attachment => {
					if (attachment.file_path) {
						// Vollständigen Pfad und nur Dateinamen speichern
						aiFilePathMap[attachment.file_path] = true;
						const filename = attachment.file_path.split('/').pop();
						aiFilePathMap[filename] = true;
						
						// Auch eine Version ohne Größenangaben speichern (z.B. aus file-300x200.jpg wird file.jpg)
						const baseFilename = filename.replace(/-\d+x\d+\.(jpe?g|png|gif|webp)$/, '.$1');
						aiFilePathMap[baseFilename] = true;
					}
				});
				
				// Optimiertes Array für schnelle Lookups statt Iteration
				const aiImageLookup = {};
				aiImageIds.forEach(id => aiImageLookup[id] = true);
				
				// Grundlegende Informationen immer anzeigen, unabhängig vom Debug-Modus
				if (processAttempts === 0) {
					console.log("AI Marker Plugin aktiv - KI-Bild-IDs verfügbar:", aiImageIds.length);
				}
				
				// Debug-Funktion für bessere Lesbarkeit der Logs, respektiert Debug-Einstellung
				function debug(message, ...args) {
					if (!debugEnabled) return; // Keine Debug-Ausgabe, wenn nicht aktiviert
					
					if (args.length > 0) {
						console.log("[AI-Marker]", message, ...args);
					} else {
						console.log("[AI-Marker]", message);
					}
				}
				
				// Im Debug-Modus zeigen wir mehr Details an
				if (debugEnabled && processAttempts === 0) {
					console.log("[AI-Marker] Debug-Modus aktiviert");
					console.log("[AI-Marker] Bekannte KI-Bildnamen:", Object.keys(aiFilePathMap).length);
					console.log("[AI-Marker] Durchgang:", processAttempts + 1, "von", maxAttempts);
				}
				
				// Bilder nach ID und URL prüfen - zentrale Funktion für alle Checks
				const checkImageId = (url) => {
					if (!url) return false;
					
					// URL von Parameterzeichen bereinigen
					const cleanUrl = url.split('?')[0];
					
					// Extrahiere Dateinamen aus URL
					const filename = cleanUrl.split('/').pop();
					
					// 0. Methode: Direkter Lookup in der Dateinamen-Map
					if (aiFilePathMap[filename]) {
						debug(`KI-Bild durch direkten Dateinamen erkannt: ${filename}`);
						return true;
					}
					
					// 0.1. Methode: Prüfe, ob der Pfad in der URL einem bekannten KI-Bild entspricht
					for (const path in aiFilePathMap) {
						if (cleanUrl.includes(path)) {
							debug(`KI-Bild durch Pfadübereinstimmung erkannt: ${path} in ${cleanUrl}`);
							return true;
						}
					}
					
					// 1. Methode: Direkte ID-Extraktion aus Dateinamen
					const idMatch = filename.match(/(?:[-_])(\d+)(?:\.|$|\-)/);
					if (idMatch && idMatch[1] && aiImageLookup[idMatch[1]]) {
						debug(`KI-Bild durch ID erkannt: ${idMatch[1]}`);
						return true;
					}
					
					// 2. Methode: Suche nach ID im gesamten URL-Pfad
					// Suche nach ID-Mustern wie /123/ oder -123. oder _123_
					const pathIdMatches = cleanUrl.match(/[\/\-_](\d+)(?:[\/\-_\.])/g);
					if (pathIdMatches) {
						for (const match of pathIdMatches) {
							const id = match.replace(/[\/\-_\.]/g, '');
							if (aiImageLookup[id]) {
								debug(`KI-Bild durch Pfad-ID erkannt: ${id}`);
								return true;
							}
						}
					}
					
					// 3. Methode: WordPress Standard-Attachments haben oft Größenangaben im Dateinamen
					// z.B. image-123-300x200.jpg
					const sizeMatch = filename.match(/[-_](\d+)-\d+x\d+\./);
					if (sizeMatch && sizeMatch[1] && aiImageLookup[sizeMatch[1]]) {
						debug(`KI-Bild durch Größen-ID erkannt: ${sizeMatch[1]}`);
						return true;
					}
					
					// 4. Methode: Direkte Suche nach der Attachment-ID nach dem Upload-Pfad
					const wpContentPos = cleanUrl.indexOf('/wp-content/uploads/');
					if (wpContentPos !== -1) {
						const relativePath = cleanUrl.substring(wpContentPos + 18); // "/wp-content/uploads/".length
						if (aiFilePathMap[relativePath]) {
							debug(`KI-Bild durch relativen Pfad erkannt: ${relativePath}`);
							return true;
						}
					}
					
					// Keine Übereinstimmung gefunden
					debug(`Kein KI-Bild erkannt für: ${url}`);
					return false;
				};
				
				// Prioritäre Verarbeitung für die benutzerdefinierte Klasse DWC_AI_Image_Marker
				const customMarkedElements = document.querySelectorAll('.DWC_AI_Image_Marker');
				debug(`Gefundene Elemente mit benutzerdefinierter Klasse: ${customMarkedElements.length}`);
				
				if (customMarkedElements.length > 0) {
					customMarkedElements.forEach(function(container) {
						// Vermeide doppelte Verarbeitung
						if (processedElements.has(container)) return;
						
						// In diesem Container nach Hintergrundbildern suchen
						const bgContainers = container.querySelectorAll('*[style*="background-image"], *[style*="background-url"], .gb-container.lazyloaded');
						debug(`Gefundene Hintergrund-Container innerhalb des markierten Bereichs: ${bgContainers.length}`);
						
						// Container als verarbeitet markieren
						processedElements.add(container);
						
						bgContainers.forEach(function(bgContainer) {
							if (processedCount >= maxToProcess) return;
							if (processedElements.has(bgContainer)) return;
							
							// Prüfe, ob bereits ein Badge vorhanden ist
							if (bgContainer.querySelector('.ai-image-badge')) {
								return;
							}
							
							// Inline-Style und computed Style prüfen
							const style = window.getComputedStyle(bgContainer);
							const inlineStyle = bgContainer.getAttribute('style') || '';
							
							// Liste der zu prüfenden CSS-Eigenschaften
							const cssProps = ['--background-image', '--background-url', 'background-image'];
							
							// Für jede Eigenschaft prüfen
							for (const prop of cssProps) {
								// Prüfen sowohl im computed style als auch direkt im style-Attribut
								let bgValue = style.getPropertyValue(prop);
								
								// Wenn kein Wert im computed style, versuche ihn aus dem inline style zu extrahieren
								if (!bgValue && inlineStyle) {
									const match = inlineStyle.match(new RegExp(prop + '\\s*:\\s*url\\([\'"]?(.*?)[\'"]?\\)'));
									if (match && match[1]) {
										bgValue = 'url(' + match[1] + ')';
									}
								}
								
								if (!bgValue) continue;
								
								// URL aus dem CSS-Wert extrahieren
								const urlMatch = bgValue.match(/url\(['"]?(.*?)['"]?\)/);
								if (!urlMatch || !urlMatch[1]) continue;
								
								const imageUrl = urlMatch[1];
								debug(`Hintergrundbild gefunden im markierten Bereich (${prop}): ${imageUrl}`);
								
								// WICHTIG: Hier prüfen wir, ob das Bild tatsächlich KI-generiert ist
								if (checkImageId(imageUrl)) {
									debug(`KI-Bild bestätigt und Badge wird hinzugefügt: ${imageUrl}`);
									addBadgeToElement(bgContainer);
									processedCount++;
									processedElements.add(bgContainer);
								} else {
									debug(`Kein KI-Bild, kein Badge wird hinzugefügt: ${imageUrl}`);
								}
								break;
							}
						});
						
						// Auch nach normalen Bildern im Container suchen
						const images = container.querySelectorAll('img');
						images.forEach(function(img) {
							if (processedCount >= maxToProcess) return;
							if (processedElements.has(img)) return;
							
							const parent = img.parentElement;
							if (parent && (parent.classList.contains('ai-image-wrapper') || parent.querySelector('.ai-image-badge'))) {
								return;
							}
							
							const src = img.getAttribute('src');
							if (checkImageId(src)) {
								debug(`KI-Bild in img-Tag erkannt: ${src}`);
								wrapImageWithBadge(img);
								processedCount++;
								processedElements.add(img);
							}
						});
					});
				}
				
				// STANDARD-VERARBEITUNG FÜR ANDERE BILDER
				
				// Alle GB-Container außerhalb der benutzerdefinierten Klasse verarbeiten
				const allGbContainers = document.querySelectorAll('.gb-container.lazyloaded:not(.DWC_AI_Image_Marker .gb-container)');
				debug(`Gefundene GB-Container (außerhalb custom): ${allGbContainers.length}`);
				
				allGbContainers.forEach(function(container) {
					if (processedCount >= maxToProcess) return;
					if (processedElements.has(container)) return;
					
					// Prüfe, ob bereits ein Badge vorhanden ist
					if (container.querySelector('.ai-image-badge')) {
						return;
					}
					
					// Alle möglichen CSS-Hintergrundbildeigenschaften prüfen
					const style = window.getComputedStyle(container);
					const inlineStyle = container.getAttribute('style') || '';
					const props = ['--background-image', '--background-url', 'background-image'];
					
					for (const prop of props) {
						// Prüfen sowohl im computed style als auch direkt im style-Attribut
						let bgValue = style.getPropertyValue(prop);
						
						// Wenn kein Wert im computed style, versuche ihn aus dem inline style zu extrahieren
						if (!bgValue && inlineStyle) {
							const match = inlineStyle.match(new RegExp(prop + '\\s*:\\s*url\\([\'"]?(.*?)[\'"]?\\)'));
							if (match && match[1]) {
								bgValue = 'url(' + match[1] + ')';
							}
						}
						
						if (!bgValue) continue;
						
						// URL aus dem CSS-Wert extrahieren
						const urlMatch = bgValue.match(/url\(['"]?(.*?)['"]?\)/);
						if (!urlMatch || !urlMatch[1]) continue;
						
						const imageUrl = urlMatch[1];
						
						// Prüfen, ob es ein KI-Bild ist
						if (checkImageId(imageUrl)) {
							addBadgeToElement(container);
							processedCount++;
							processedElements.add(container);
							break;
						}
					}
				});
				
				// Allgemeine Hintergrundbilder verarbeiten
				const bgElements = document.querySelectorAll('[style*="background-image"]:not(.DWC_AI_Image_Marker *), [style*="--background"]:not(.DWC_AI_Image_Marker *)');
				debug(`Gefundene Elemente mit Hintergrundbildern: ${bgElements.length}`);
				
				bgElements.forEach(function(element) {
					// Prüfe, ob bereits verarbeitet oder Limit erreicht
					if (processedCount >= maxToProcess) return;
					if (processedElements.has(element)) return;
					
					// Bereits verarbeitete GB-Container überspringen
					if (element.classList.contains('gb-container') && element.classList.contains('lazyloaded')) {
						return;
					}
					
					// Prüfe, ob bereits ein Badge vorhanden ist
					if (element.querySelector('.ai-image-badge')) {
						return;
					}
					
					// Alle möglichen CSS-Hintergrundbildeigenschaften prüfen
					const style = window.getComputedStyle(element);
					const inlineStyle = element.getAttribute('style') || '';
					const props = ['--background-image', '--background-url', 'background-image'];
					
					for (const prop of props) {
						// Prüfen sowohl im computed style als auch direkt im style-Attribut
						let bgValue = style.getPropertyValue(prop);
						
						// Wenn kein Wert im computed style, versuche ihn aus dem inline style zu extrahieren
						if (!bgValue && inlineStyle) {
							const match = inlineStyle.match(new RegExp(prop + '\\s*:\\s*url\\([\'"]?(.*?)[\'"]?\\)'));
							if (match && match[1]) {
								bgValue = 'url(' + match[1] + ')';
							}
						}
						
						if (!bgValue) continue;
						
						// URL aus dem CSS-Wert extrahieren
						const urlMatch = bgValue.match(/url\(['"]?(.*?)['"]?\)/);
						if (!urlMatch || !urlMatch[1]) continue;
						
						const imageUrl = urlMatch[1];
						
						// Prüfen, ob es ein KI-Bild ist
						if (checkImageId(imageUrl)) {
							addBadgeToElement(element);
							processedCount++;
							processedElements.add(element);
							break;
						}
					}
				});
				
				// Verarbeite normale Bilder (img-Tags)
				const allImages = document.querySelectorAll('img:not(.DWC_AI_Image_Marker *)');
				debug(`Gefundene Bilder: ${allImages.length}`);
				
				allImages.forEach(function(img) {
					// Prüfe, ob bereits verarbeitet oder Limit erreicht
					if (processedCount >= maxToProcess) return;
					if (processedElements.has(img)) return;
					
					// Überprüfe, ob der Elternteil bereits ein Badge hat oder ein Wrapper ist
					const parent = img.parentElement;
					if (parent && (
						parent.classList.contains('ai-image-wrapper') || 
						parent.querySelector('.ai-image-badge')
					)) {
						return;
					}
					
					// Überprüfe, ob das Bild selbst bereits ein Badge hat
					if (img.nextElementSibling && img.nextElementSibling.classList.contains('ai-image-badge')) {
						return;
					}
					
					// Prüfe das src-Attribut
					const src = img.getAttribute('src');
					if (checkImageId(src)) {
						debug(`KI-Bild in img-Tag erkannt: ${src}`);
						wrapImageWithBadge(img);
						processedCount++;
						processedElements.add(img);
					}
					// Prüfe auch data-src für Lazy-Loading Bilder
					else {
						const dataSrc = img.getAttribute('data-src');
						if (dataSrc && checkImageId(dataSrc)) {
							debug(`KI-Bild in img-Tag (data-src) erkannt: ${dataSrc}`);
							wrapImageWithBadge(img);
							processedCount++;
							processedElements.add(img);
						}
					}
				});
				
				// Prüfe auf lazy-loading Bilder mit Klassen
				const lazyImages = document.querySelectorAll('.lazyload, .lazy');
				debug(`Gefundene Lazy-Load Bilder: ${lazyImages.length}`);
				
				lazyImages.forEach(function(element) {
					if (processedCount >= maxToProcess) return;
					if (processedElements.has(element)) return;
					
					// Prüfe, ob es bereits ein Badge gibt
					if (element.parentElement && element.parentElement.classList.contains('ai-image-wrapper')) {
						return;
					}
					
					// Prüfe verschiedene lazy-loading Attribute
					const dataSrc = element.getAttribute('data-src');
					const dataBgSrc = element.getAttribute('data-bg');
					
					if (dataSrc && checkImageId(dataSrc)) {
						debug(`Lazy-Load KI-Bild erkannt (data-src): ${dataSrc}`);
						if (element.tagName.toLowerCase() === 'img') {
							wrapImageWithBadge(element);
						} else {
							addBadgeToElement(element);
						}
						processedCount++;
						processedElements.add(element);
					} else if (dataBgSrc && checkImageId(dataBgSrc)) {
						debug(`Lazy-Load KI-Bild erkannt (data-bg): ${dataBgSrc}`);
						addBadgeToElement(element);
						processedCount++;
						processedElements.add(element);
					}
				});
				
				debug(`Durchgang ${processAttempts + 1} abgeschlossen, ${processedCount} Bilder verarbeitet`);
				
				// Prüfen, ob weitere Durchläufe notwendig sind
				processAttempts++;
				if (processAttempts < maxAttempts) {
					// Nach einer kurzen Verzögerung erneut ausführen, um sicherzustellen, dass alle Bilder erfasst werden
					setTimeout(processImages, 1000); // 1 Sekunde warten
				}
			}
			
			// Hilfsfunktion zum direkten Hinzufügen eines Badges zu einem Element
			function addBadgeToElement(element) {
				// Prüfe, ob das Element bereits ein Badge hat
				if (element.querySelector('.ai-image-badge')) {
					return;
				}
				
				// Stelle sicher, dass das Element relative Position hat
				if (window.getComputedStyle(element).position === 'static') {
					element.style.position = 'relative';
				}
				
				// Erstelle ein Badge
				const badge = document.createElement('div');
				badge.className = 'ai-image-badge';
				badge.textContent = '<?php echo esc_js( $options['badge_text'] ); ?>';
				
				// Füge das Badge zum Element hinzu
				element.appendChild(badge);
			}
			
			// Hilfsfunktion zum Umschließen eines Bildes mit einem Wrapper und Badge
			function wrapImageWithBadge(img) {
				const parent = img.parentElement;
				
				// Erstelle einen Wrapper um das Bild
				const wrapper = document.createElement('div');
				wrapper.className = 'ai-image-wrapper';
				
				// Positioniere den Wrapper korrekt
				if (img.style.display === 'inline-block' || window.getComputedStyle(img).display === 'inline-block') {
					wrapper.style.display = 'inline-block';
				}
				
				// Anpassung der Breite, wenn das Elternelement vorhanden ist
				if (parent) {
					wrapper.style.width = img.offsetWidth > 0 ? img.offsetWidth + 'px' : 'auto';
					wrapper.style.position = 'relative';
				}
				
				// Erstelle ein Badge
				const badge = document.createElement('div');
				badge.className = 'ai-image-badge';
				badge.textContent = '<?php echo esc_js( $options['badge_text'] ); ?>';
				
				// Ersetze das Bild mit dem Wrapper und füge Badge hinzu
				if (parent) {
					parent.insertBefore(wrapper, img);
					wrapper.appendChild(badge);
					wrapper.appendChild(img);
				}
			}
			
			// MutationObserver, um dynamisch geladene Bilder zu überwachen
			function setupMutationObserver() {
				// Konfiguration des Observers: überwache Änderungen im DOM-Baum und Attribute
				const config = { 
					childList: true, 
					subtree: true,
					attributes: true,
					attributeFilter: ['src', 'style', 'data-src', 'data-bg']
				};
				
				// Callback-Funktion, die bei Änderungen aufgerufen wird
				const callback = function(mutationsList, observer) {
					for (const mutation of mutationsList) {
						// Nur verarbeiten, wenn bereits einige Zeit seit dem letzten Durchgang vergangen ist
						if (processAttempts >= maxAttempts && !processingDebounce) {
							processingDebounce = true;
							setTimeout(() => {
								// Reset für einen neuen Durchlauf
								processAttempts = 0;
								processImages();
								processingDebounce = false;
							}, 500);
							break;
						}
					}
				};
				
				// Observer erstellen
				const observer = new MutationObserver(callback);
				
				// Observer starten
				observer.observe(document.body, config);
			}
			
			// Variable zur Steuerung des Debouncing
			let processingDebounce = false;
			
			// Starte die Verarbeitung, wenn die Seite vollständig geladen ist
			// Verwende sowohl DOMContentLoaded als auch load-Event
			document.addEventListener('DOMContentLoaded', function() {
				// Erste Verarbeitung nach kurzem Timeout, um sicherzustellen, dass die meisten Elemente geladen sind
				setTimeout(processImages, 100);
				
				// Starte den MutationObserver für dynamisch geladene Inhalte
				setupMutationObserver();
			});
			
			// Nochmals beim vollständigen Laden der Seite verarbeiten (für Bilder, die später geladen werden)
			window.addEventListener('load', function() {
				// Falls wir noch nicht die maximale Anzahl an Versuchen erreicht haben
				if (processAttempts < maxAttempts) {
					// Wir setzen auf den letzten Versuch, um einen weiteren Durchlauf zu erzwingen
					processAttempts = maxAttempts - 1;
					setTimeout(processImages, 500);
				}
			});
		})();
		</script>
		<?php
	}
} 