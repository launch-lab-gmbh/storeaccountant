---
title: Feldzuordnung
description: Konfigurieren Sie Spalten, Spaltennamen, Reihenfolge und feldspezifische Optionen Ihrer Exporte.
---

# Feldzuordnung

Die Feldzuordnung steuert die Spalten einer Exportkonfiguration. Sie erreichen sie über
`Buchhaltung > Exporte > Exportkonfigurationen`, indem Sie eine Konfiguration öffnen und den Tab `Feldzuordnung`
auswählen.

Feldzuordnungen gelten für Exporte, die aus dieser Konfiguration gestartet werden. Quick Exports verwenden die
Standardfelder des jeweiligen Exporttyps.

## Was Sie konfigurieren können

In der Feldzuordnung können Sie je nach Exporttyp:

- Felder aktivieren oder deaktivieren.
- Spaltenüberschriften ändern.
- Die Reihenfolge der Spalten anpassen.
- Feldoptionen setzen, zum Beispiel Formatierungen für Datum, Uhrzeit oder Beträge.
- Custom Fields einbeziehen, wenn sie vom Exporttyp erkannt werden.
- Steuer- und Rechnungsfelder verwenden, wenn sie für die Konfiguration verfügbar sind.

[Screenshot: Feldzuordnung mit Spalten und Reihenfolge]

## Empfohlener Ablauf

1. Öffnen Sie `Buchhaltung > Exporte > Exportkonfigurationen`.
2. Öffnen Sie die gewünschte Konfiguration.
3. Wechseln Sie in den Tab `Feldzuordnung`.
4. Deaktivieren Sie Spalten, die Ihre Buchhaltung nicht benötigt.
5. Benennen Sie Spalten so, wie Ihr Zielsystem sie erwartet.
6. Sortieren Sie die Spalten in der benötigten Reihenfolge.
7. Speichern Sie die Konfiguration.
8. Starten Sie anschließend einen Export aus dieser Konfiguration.

## Neue oder fehlende Felder

StoreAccountant behandelt Feldzuordnungen robust:

- Neue Felder, die später durch StoreAccountant oder eine Erweiterung dazukommen, sind standardmäßig aktiv.
- Felder, die nicht mehr verfügbar sind, werden beim Anzeigen und Exportieren ignoriert.
- Wenn ein Anbieter später wieder verfügbar ist, kann eine zuvor gespeicherte Zuordnung wieder wirksam werden.

Das ist besonders wichtig bei optionalen Integrationen wie Rechnungsplugins oder zusätzlichen Exportfeldern.

## Steuerfelder bei Bestellungen

Bestellexporte können Steuerfelder in einfacher oder erweiterter Form enthalten. Die Auswahl treffen Sie in der
Konfiguration im Feld `Steuerfelder`. Wenn Sie diese Auswahl ändern, kann sich die verfügbare Feldliste ändern.
Prüfen Sie danach die Feldzuordnung erneut.

## Rechnungsfelder und Anhänge

Wenn ein unterstütztes Rechnungsplugin aktiv und in den StoreAccountant-Einstellungen ausgewählt ist, können
rechnungsbezogene Felder oder Anhänge für Bestellexporte verfügbar sein. Prüfen Sie dazu
`Plugins > Installierte Plugins > StoreAccountant > Einstellungen > Rechnungsanbieter`.
