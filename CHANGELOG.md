# Changelog

#### Deutsch

Alle signifikanten Änderungen in diesem Projekt werden in diesem Dokument festgehalten. 
The English version can be found below.

## [1.1.0] - 01.02.2025

### Hinzugefügt

- Standardwerte für das Badge hinzugefügt, einschließlich `badge_text`, `position`, `background_color`, `font_family`, `opacity` und Abstände (`padding_top`, `padding_right`, `padding_bottom`, `padding_left`).
- Registrierung neuer Einstellungen für `background_color`, `font_family`, `opacity` und `padding` in den WordPress-Einstellungen.
- Aktualisierung der Sanitize-Methoden zur Integration neuer Einstellungen: `background_color`, `font_family`, `opacity` und Abstandsangaben.
- Hinzugefügte CSS-Stile für neue Einstellungen zum Aussehen des Badges, einschließlich `background_color`, `font_family`, `opacity` und Abstände.
- **Verbesserungen der Administrationsseite:** Hinzufügen von Eingabefeldern auf der Admin-Seite, damit Benutzer CSS-Einstellungen wie `background_color`, `font_family`, `opacity` und Abstände direkt über die WordPress-Administrationsoberfläche anpassen können.
- JavaScript-Funktionalität zum Zurücksetzen auf Standardeinstellungen und Aktualisieren des Anzeigewerts für `opacity`.

### Geändert

- Aktualisierung der Aktivierungsfunktion zur Berücksichtigung neuer Standardeinstellungen für `background_color`, `font_family`, `opacity` und Abstände.

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

## [1.1.0] - 01.02.2025

### Added

- Default settings for the badge were added, including `badge_text`, `position`, `background_color`, `font_family`, `opacity`, and paddings (`padding_top`, `padding_right`, `padding_bottom`, `padding_left`).
- Registration of new settings for `background_color`, `font_family`, `opacity`, and `padding` in the WordPress settings.
- Sanitize methods were updated to include new settings: `background_color`, `font_family`, `opacity`, and paddings fields.
- Added CSS styling for new settings to the badge's appearance including `background_color`, `font_family`, `opacity`, and padding.
- **Admin Page Enhancements:** Added input fields on the admin settings page to allow users to customize CSS settings such as `background_color`, `font_family`, `opacity`, and paddings directly from the WordPress admin interface.
- JavaScript functionality for resetting to default settings and updating display value for `opacity`.

### Changed

- Updated the activation function to include new default settings for `background_color`, `font_family`, `opacity`, and paddings.

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
