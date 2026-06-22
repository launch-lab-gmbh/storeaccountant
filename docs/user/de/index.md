---
title: Anleitungen
description: Erfahren Sie, wie Sie mit StoreAccountant Ihre WooCommerce-Daten flexibel exportieren und für Buchhaltung, Controlling und weitere Geschäftsprozesse bereitstellen können.
---

![Header Image](images/header.png)

# StoreAccountant Anleitungen

> Willkommen in der deutschen Anleitung zum StoreAccountant WordPress-Plugin.

StoreAccountant erweitert WooCommerce um flexible Exportfunktionen für Bestellungen, Kunden und Produkte. Jeder Export
wird als eigener Datensatz gespeichert, im Hintergrund verarbeitet und anschließend mit Status, Details und geschütztem
Download in der WordPress-Administration angezeigt.

Die wichtigsten Bereiche im WordPress-Backend sind:

- `Buchhaltung > Exporte`: Exportübersicht, Quick Exports und Exporte aus gespeicherten Konfigurationen.
- `Buchhaltung > Exporte > Exportkonfigurationen`: wiederverwendbare Exportvorlagen mit Filtern, Format,
  Speicherort, Passwort und Feldzuordnung.
- `Plugins > Installierte Plugins > StoreAccountant > Einstellungen`: Speicherorte, Rechnungsanbieter,
  Hintergrundverarbeitung, Berechtigungen, Sicherheit und Diagnose.

## Schnellstart

1. Installieren und aktivieren Sie StoreAccountant wie in [Installation](installation.md) beschrieben.
2. Prüfen Sie unter [Plugin konfigurieren](plugin/konfiguration.md), ob mindestens ein Speicherort aktiv ist.
3. Erstellen Sie für einmalige Aufgaben einen [Quick Export](exports/export-anlegen.md).
4. Legen Sie für wiederkehrende Abläufe eine [Exportkonfiguration](exports/export-konfiguration-anlegen.md) an.
5. Passen Sie bei Bedarf die [Feldzuordnung](exports/feldzuordnung.md) an.
6. Laden Sie fertige Dateien aus der [Exportübersicht](exports/exporte-verwalten.md) herunter.

## Unterstützte Funktionen

- Exporte für WooCommerce-Bestellungen, Kunden und Produkte.
- Exportformate CSV und JSON.
- Filter nach Zeitraum, Bestellstatus, Kundenland und produktbezogenen Einstellungen.
- Konfigurierbare Spalten und Feldreihenfolge über Feldzuordnungen.
- Steuerfelder für Bestellexporte in einfacher oder erweiterter Form.
- Rechnungsfelder und Rechnungsanhänge, wenn ein unterstütztes Rechnungsplugin aktiv ist.
- Passwortgeschützte Downloadlinks für erzeugte Exportarchive.
- Hintergrundverarbeitung mit sichtbarem Fortschritt in der Exportliste.
- Rollenbasierte StoreAccountant-Berechtigungen für Backend-Benutzer.
- Diagnosepakete für Supportfälle.

## Dokumentation

- [Installation](installation.md)
- [Exporte](exports/index.md)
- [Export anlegen](exports/export-anlegen.md)
- [Exportkonfiguration anlegen](exports/export-konfiguration-anlegen.md)
- [Feldzuordnung](exports/feldzuordnung.md)
- [Exporte verwalten und herunterladen](exports/exporte-verwalten.md)
- [Download-Passwortschutz](exports/downloads-passwortschutz.md)
- [Plugin konfigurieren](plugin/konfiguration.md)
- [Berechtigungen](plugin/berechtigungen.md)
- [Diagnosepakete](Diagnostik.md)
