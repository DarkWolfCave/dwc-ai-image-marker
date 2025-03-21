# Changelog

#### Deutsch

Alle signifikanten Änderungen in diesem Projekt werden in diesem Dokument festgehalten.
The English version can be found below.

## [1.2.1] - 19.03.2025

### Verbessert

- Robustere Bildererkennung mit mehrfachen Durchläufen für konsistente Badge-Anzeige
- Erhöhtes Verarbeitungslimit für eine zuverlässigere Erkennung aller Bilder
- Verbesserte Erkennung von Lazy-Loading-Bildern

### Behoben

- Problem mit intermittierend verschwindenden Badges nach dem Neuladen der Seite
- Verarbeitung von dynamisch geladenen Inhalten durch Hinzufügen eines MutationObservers
- Optimierte Ressourcennutzung durch Vermeidung von Doppelverarbeitungen

## [1.2.0] - 18.03.2025

### Hinzugefügt

- Neue Einstellung für Debug-Modus im Admin-Bereich
- Spezielle CSS-Anpassung für Badges innerhalb der `DWC_AI_Image_Marker`-Klasse
- Verbesserte Dateistruktur gemäß WordPress-Entwicklungsstandards (admin, public, includes, assets)
- Umfassendere Erkennung von KI-generierten Bildern in verschiedenen Kontexten
- Optimierte Datenbankabfragen mit Caching für bessere Performance
- Spezialisierte Klassen für verschiedene Plugin-Funktionalitäten

### Geändert

- Restrukturierung des Plugins in mehrere Dateien für bessere Wartbarkeit
- CSS-Dateien wurden in ein neues `assets/css`-Verzeichnis verschoben
- Verbesserte KI-Bild-Erkennung mit optimierten Lookup-Methoden
- Leistungsoptimierung durch Begrenzung der zu verarbeitenden Bilder
- Debug-Ausgaben werden nur angezeigt, wenn der Debug-Modus aktiviert ist

### Verbessert

- Bessere JavaScript-Erkennung von Hintergrundbildern
- Optimierte Verarbeitung von Bildern in benutzerdefinierten Bereichen
- Schnellere Bild-ID-Erkennung durch Verwendung von Lookup-Tabellen
- Reduzierter DOM-Verarbeitungsaufwand für bessere Leistung
- Zentralisierte Hilfsfunktionen in eigener Klasse

### Behoben

- Problem mit fehlenden Badges in bestimmten Situationen
- Duplizierte Badge-Hinzufügungen in verschachtelten Containern
- Erkennungsprobleme bei bestimmten Bildnamen und -pfaden
- Performance-Engpässe bei Seiten mit vielen Bildern

## [1.1.1] - 07.02.2025

### Hinzugefügt

- Einführung der Klasse `Dwc_Ai_Marker_Activator` zur Handhabung von Aktivierungs- und Deaktivierungshooks.
- Helfermethoden zur Bereitstellung von Plugin-URL und -Pfad implementiert.
- Unterstützung für Yoda Conditions zur Verbesserung der Code-Qualität hinzugefügt.
- Nonce-Validierungen und Sicherheitsprüfungen ergänzt, um Formulareingaben abzusichern.
- Bulk-Aktionen zum effizienten Aktualisieren von KI-markierten Bildern hinzugefügt.
- Erweiterte Benutzeroberfläche mit erklärenden Kommentaren und Labels zur Verbesserung der Benutzererfahrung.
- CSS-Stile in `dwc-ai-marker.css` zur Konsistenz und Darstellung angepasst.

### Geändert

- `Plugin URI` und `Description` aktualisiert, um eine genauere Beschreibung zu bieten.
- Kommentare und Dokumentation überarbeitet, um den Code verständlicher zu gestalten.

### Behoben

- Nicht zutreffend

### Sicherheit

- Nonce-Überprüfungen zur Sicherung von Formulareingaben hinzugefügt.

## [1.1.0] - 01.02.2025

### Hinzugefügt

- Standardwerte für das Badge hinzugefügt, einschließlich `badge_text`, `position`, `background_color`, `font_family`,
  `opacity` und Abstände (`padding_top`, `padding_right`, `padding_bottom`, `padding_left`).
- Registrierung neuer Einstellungen für `background_color`, `font_family`, `opacity` und `padding` in den
  WordPress-Einstellungen.
- Aktualisierung der Sanitize-Methoden zur Integration neuer Einstellungen: `background_color`, `font_family`, `opacity`
  und Abstandsangaben.
- Hinzugefügte CSS-Stile für neue Einstellungen zum Aussehen des Badges, einschließlich `background_color`,
  `font_family`, `opacity` und Abstände.
- **Verbesserungen der Administrationsseite:** Hinzufügen von Eingabefeldern auf der Admin-Seite, damit Benutzer
  CSS-Einstellungen wie `background_color`, `font_family`, `opacity` und Abstände direkt über die
  WordPress-Administrationsoberfläche anpassen können.
- JavaScript-Funktionalität zum Zurücksetzen auf Standardeinstellungen und Aktualisieren des Anzeigewerts für `opacity`.

### Geändert

- Aktualisierung der Aktivierungsfunktion zur Berücksichtigung neuer Standardeinstellungen für `background_color`,
  `font_family`, `opacity` und Abstände.

### Behoben

- Nicht zutreffend

### Sicherheit

- Nicht zutreffend

## [1.0.1] - 28.01.2025

### Hinzugefügt

- Initiale Version des Plugins.
- Funktion zur automatischen Markierung von KI-generierten Bildern.
- Admin-Menü zur Konfiguration des Plugins.
- Funktionen zum Hinzufügen/Entfernen von Markierungen über Bulk-Actions.
- Unterstützung für das GenerateBlocks Plugin.

---

## English

## [1.2.1] - 19.03.2025

### Improved

- More robust image detection with multiple passes for consistent badge display
- Increased processing limit for more reliable detection of all images
- Enhanced detection of lazy-loaded images

### Fixed

- Issue with intermittently disappearing badges after page reload
- Processing of dynamically loaded content by adding a MutationObserver
- Optimized resource usage by avoiding duplicate processing

## [1.2.0] - 18.03.2025

### Added

- New setting for debug mode in the admin area
- Special CSS adjustment for badges within the `DWC_AI_Image_Marker` class
- Improved file structure according to WordPress development standards (admin, public, includes, assets)
- More comprehensive detection of AI-generated images in various contexts
- Optimized database queries with caching for better performance
- Specialized classes for different plugin functionalities

### Changed

- Restructured the plugin into multiple files for better maintainability
- CSS files moved to a new `assets/css` directory
- Improved AI image detection with optimized lookup methods
- Performance optimization by limiting the number of images to process
- Debug outputs are only displayed when debug mode is activated

### Improved

- Better JavaScript detection of background images
- Optimized processing of images in custom areas
- Faster image ID detection using lookup tables
- Reduced DOM processing overhead for better performance
- Centralized helper functions in dedicated class

### Fixed

- Issue with missing badges in certain situations
- Duplicate badge additions in nested containers
- Detection problems with certain image names and paths
- Performance bottlenecks on pages with many images

## [1.1.1] - 07.02.2025

### Added

- Introduced `Dwc_Ai_Marker_Activator` class for handling activation and deactivation hooks.
- Implemented helper methods for providing plugin URL and path.
- Added support for Yoda Conditions to improve code quality.
- Included nonce validations and security checks to secure form inputs.
- Added bulk actions to efficiently update AI-marked images.
- Enhanced user interface with explanatory comments and labels for better user experience.
- Adjusted CSS styles in `dwc-ai-marker.css` for consistency and presentation.

### Changed

- Updated `Plugin URI` and `Description` for a more accurate representation.
- Revised comments and documentation to make the code more understandable.

### Fixed

- Not applicable

### Security

- Added nonce checks to secure form inputs.

## [1.1.0] - 01.02.2025

### Added

- Default settings for the badge were added, including `badge_text`, `position`, `background_color`, `font_family`,
  `opacity`, and paddings (`padding_top`, `padding_right`, `padding_bottom`, `padding_left`).
- Registration of new settings for `background_color`, `font_family`, `opacity`, and `padding` in the WordPress
  settings.
- Sanitize methods were updated to include new settings: `background_color`, `font_family`, `opacity`, and paddings
  fields.
- Added CSS styling for new settings to the badge's appearance including `background_color`, `font_family`, `opacity`,
  and padding.
- **Admin Page Enhancements:** Added input fields on the admin settings page to allow users to customize CSS settings
  such as `background_color`, `font_family`, `opacity`, and paddings directly from the WordPress admin interface.
- JavaScript functionality for resetting to default settings and updating display value for `opacity`.

### Changed

- Updated the activation function to include new default settings for `background_color`, `font_family`, `opacity`, and
  paddings.

### Fixed

- N/A

### Security

- N/A

## [1.0.1] - 28.01.2025

### Added

- Initial version of the plugin.
- Feature for automatically marking AI-generated images.
- Admin menu for plugin configuration.
- Functions to add/remove markers using bulk actions.
- Support for the GenerateBlocks plugin.