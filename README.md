# DWC AI Image Marker

**Plugin Name:** DWC AI Image Marker  
**Plugin URI:** [https://darkwolfcave.de](https://darkwolfcave.de)  
**Description:** Automatisches Markieren von KI-generierten Bildern mit einem Badge in WordPress.  
**Version:** 1.2.2  
**Author:** DarkWolfCave.de  
**License:** GPL v2 or later

## Beschreibung

Der DWC AI Image Marker ist ein WordPress-Plugin, das KI-generierte Bilder in der Medienbibliothek erkennt und mit einem
Badge kennzeichnet. Dadurch wird einem Webmaster die Verwaltung solcher Bilder erleichtert. Diese Version bietet
verbesserte Zuverlässigkeit bei der Badge-Anzeige und behebt Probleme mit intermittierend verschwindenden Badges nach
dem Neuladen der Seite.

## Features

- Automatische Erkennung und Markierung von KI-generierten Bildern
- Anpassbares Badge mit konfigurierbarem Text, Farbe, Position und Transparenz
- Verwaltung der Markierungen in der Medienübersicht
- Unterstützung von Bulk-Actions für effiziente Bearbeitung
- Optimierte Erkennung von Hintergrundbildern mittels JavaScript
- Spezielle Behandlung für die benutzerdefinierte Klasse `DWC_AI_Image_Marker`
- Leistungsoptimierungen für Seiten mit vielen Bildern
- Debug-Modus für erweiterte Fehlerbehebung
- Unterstützung von GenerateBlocks Image Blocks

### Neue Funktionen in Version 1.2.2

- **Verbesserte Code-Qualität**: Umfassende Einhaltung der WordPress Coding Standards
- **Optimierte Dokumentation**: Vollständige PHPDoc-Kommentare für alle Methoden und Parameter
- **Optimierter Datenbankzugriff**: Effizientere Datenbankabfragen mit WordPress-Caching-Mechanismen
- **Verbesserte Fehlerbehandlung**: Robustere DOM-Manipulation und Fehlerbehandlung
- **Neue Dateistruktur**: Strikte Trennung von Plug-in-Loader und Initialisierung nach WordPress Best Practices

### Neue Funktionen in Version 1.2.1

- **Konsistente Badge-Anzeige**: Verbesserte Zuverlässigkeit bei der Badge-Anzeige durch mehrfache Durchläufe und
  robustere Erkennung
- **MutationObserver-Integration**: Automatische Erkennung und Verarbeitung von dynamisch geladenen Inhalten
- **Verbesserte Lazy-Loading-Unterstützung**: Erkennung von Bildern mit verzögertem Laden durch verschiedene Attribute
- **Optimierte Ressourcennutzung**: Vermeidung von Doppelverarbeitungen durch Tracking bereits verarbeiteter Elemente

### Funktionen aus Version 1.2.0

- **Debug-Modus**: Über die Plugin-Einstellungen kann ein Debug-Modus aktiviert werden, der detaillierte Informationen
  in der Browser-Konsole anzeigt
- **Verbesserte Plugin-Struktur**: Das Plugin wurde in separate Dateien und Verzeichnisse aufgeteilt, die den
  WordPress-Entwicklungsstandards entsprechen
- **Optimierte KI-Bild-Erkennung**: Mehrere Methoden zur Erkennung von KI-Bildern, einschließlich Dateiname, URL und
  Datenbank-Informationen
- **Erweiterte CSS-Anpassungen**: Spezielle Positionierung für Badges innerhalb der `DWC_AI_Image_Marker`-Klasse
- **Performance-Verbesserungen**: Caching, Limit für die Anzahl der zu verarbeitenden Bilder, optimierte
  DOM-Verarbeitung

## Installation

1. Lade dir die [ZIP-Datei](https://github.com/DarkWolfCave/dwc-ai-image-marker/archive/refs/heads/main.zip) herunter.
2. In WordPress navigiere zu Plugins -> Neues Plugin hinzufügen -> Plugin hochladen -> installieren.
3. Aktiviere das Plugin.
4. Konfiguriere das Plugin über das 'Einstellungen' Menü unter 'AI Image Marker'.

## Anforderungen

- WordPress 6.0 oder höher
- PHP 7.4 oder höher

## Nutzung

Nach der Aktivierung des Plugins werden alle Medienbilder auf KI-Erzeugung geprüft, sofern diese Funktion aktiviert ist.
Gehe zu 'Einstellungen' > 'AI Image Marker', um die Badge-Attribute zu konfigurieren und neue CSS-Optionen festzulegen.

### Markieren von KI-Bildern

Um ein Bild als KI-generiert zu markieren:

1. Gehe zur Mediathek
2. Wähle das Bild aus oder lade ein neues hoch
3. Aktiviere das Kontrollkästchen "KI-generiertes Bild"
4. Speichere die Änderungen

Du kannst auch mehrere Bilder gleichzeitig über die Bulk-Aktionen in der Mediathek markieren.

## Änderungshistorie

Für alle Änderungen siehe dir bitte die [CHANGELOG](CHANGELOG.md) an.

## Lizenz

Dieses Plugin ist unter der GPL-Lizenz veröffentlicht. Weitere Informationen findest du in der [LICENSE](LICENSE).
