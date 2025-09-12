# Provider Settings Screen Überarbeitung

## Übersicht

Der `ProviderSettingsScreen` wurde vollständig überarbeitet, um den etablierten Best Practices aus dem `SystemSettingsScreen` und `UserListScreen` zu folgen. Das neue Design verwendet eine Tabellenansicht anstelle des vorherigen Tab-basierten Layouts.

## Geänderte Dateien

### Neue Layouts
- `app/Orchid/Layouts/ModelSettings/ProviderSettingsListLayout.php` - Tabellenlayout für die Provider-Liste
- `app/Orchid/Layouts/ModelSettings/ProviderSettingsFiltersLayout.php` - Filter-Layout für die Suche und Filterung
- `app/Orchid/Layouts/ModelSettings/ProviderSettingsEditLayout.php` - Edit-Layout für das Modal

### Überarbeitete Screens
- `app/Orchid/Screens/ModelSettings/ProviderSettingsScreen.php` - Hauptscreen mit Tabellenlayout
- `app/Orchid/Screens/ModelSettings/ProviderCreateScreen.php` - Vereinfacht und konsistent gemacht
- `app/Orchid/Screens/ModelSettings/ProviderEditScreen.php` - Neuer separater Edit-Screen

### Model-Erweiterungen
- `app/Models/ProviderSetting.php` - Erweitert um Filter-Support und Suchfunktionalität

### Routen
- `routes/platform.php` - Neue Edit-Route hinzugefügt

## Neue Funktionen

### Tabellenlayout
- **Provider Name** - Klickbar für Modal-Bearbeitung
- **API Format** - Anzeige des verwendeten API-Formats
- **Base URL** - Gekürzt für bessere Darstellung
- **Status** - Aktiv/Inaktiv mit Toggle-Funktion
- **Zeitstempel** - Erstellt/Zuletzt aktualisiert
- **Aktionen** - Dropdown mit Edit, Test Connection, Delete

### Filter und Suche
- **Suchfeld** - Durchsucht Provider-Name, URLs und API-Format
- **API Format Filter** - Dropdown mit verfügbaren Formaten
- **Status Filter** - Aktiv/Inaktiv/Alle

### Modal-Bearbeitung
- Schnelle Bearbeitung über Modal direkt aus der Tabelle
- Vollständige Validierung
- Password-Feld Handling (nur bei Änderung speichern)

### Zusätzliche Funktionen
- **Connection Test** - Test der Provider-Verbindung
- **Status Toggle** - Schnelles Aktivieren/Deaktivieren
- **Enhanced Validation** - Verbesserte Validierungsregeln
- **Bessere UX** - Konsistente Bedienung wie andere Screens

## Migration von altem Layout

Das alte Tab-basierte Layout wurde vollständig durch das neue Tabellenlayout ersetzt:
- Entfernt: Tab-Generierung und Provider-spezifische Layouts
- Hinzugefügt: Standard Orchid Table mit Filter- und Sortierfunktionen
- Verbessert: Einheitliche Bedienung mit anderen Admin-Screens

## Technische Verbesserungen

- **Filter-Support**: `Filterable` Trait im Model
- **Suchfunktionalität**: Custom `search` Scope im Model
- **Validierung**: Umfassende Request-Validierung
- **Error Handling**: Bessere Fehlerbehandlung
- **Performance**: Pagination und effiziente Queries
- **Konsistenz**: Einheitlicher Code-Style mit anderen Screens

## Berechtigungen

Das Screen nutzt die bestehende Permission:
```php
'platform.modelsettings.providers'
```

## Usage

Der Screen ist unter `/modelsettings/providers` erreichbar und bietet alle CRUD-Operationen für Provider-Einstellungen mit einer modernen, benutzerfreundlichen Tabellenschnittstelle.
