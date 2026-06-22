---
title: Exporte
description: Überblick über Quick Exports, Exportkonfigurationen, gespeicherte Exportdatensätze und Downloads.
---

# Exporte

StoreAccountant unterscheidet zwischen Exporten und Exportkonfigurationen.

Ein Export ist ein konkreter Lauf. Er hat einen Titel, einen Ausführungszeitpunkt, einen Status, eine Exportdatei und
einen geschützten Download. Sie finden diese Datensätze unter `Buchhaltung > Exporte`.

Eine Exportkonfiguration ist eine wiederverwendbare Vorlage. Sie speichert Exporttyp, Filter, Format, Speicherort,
Download-Passwort, Batch-Größe und die Feldzuordnung. Sie finden diese Vorlagen im Tab `Exportkonfigurationen` auf der
Exportübersicht.

## Welche Exporttypen gibt es?

StoreAccountant bringt diese Exporttypen mit:

- `Bestellungen`: WooCommerce-Bestellungen mit Zeitraum, Bestellstatus, Steuerfeldern und optionalen Rechnungsdaten.
- `Kunden`: WooCommerce-Kunden mit Zeitraum und Länderfilter.
- `Produkte`: WooCommerce-Produkte mit Zeitraum und optional separat exportierten Varianten.

## Welche Exportformate gibt es?

Aktuell können Exporte als `CSV` oder `JSON` erzeugt werden. Die verfügbaren Formate erscheinen im Feld
`Exportformat`.

## Welche Wege gibt es?

- [Export anlegen](export-anlegen.md): für einen einmaligen Quick Export oder einen Export aus einer gespeicherten
  Konfiguration.
- [Exportkonfiguration anlegen](export-konfiguration-anlegen.md): für wiederverwendbare Exportvorlagen.
- [Feldzuordnung](feldzuordnung.md): für Spalten, Spaltennamen, Reihenfolge und feldspezifische Optionen.
- [Exporte verwalten und herunterladen](exporte-verwalten.md): für Status, Details, Download und Fehleranalyse.
- [Download-Passwortschutz](downloads-passwortschutz.md): für globale, konfigurationsbezogene und exportspezifische
  Download-Passwörter.

## Noch nicht im UI enthalten

Geplante, automatisch wiederkehrende Exporte sind vorbereitet, werden in der aktuellen Benutzeroberfläche aber noch
nicht angeboten. Wiederkehrende Arbeitsabläufe werden derzeit über Exportkonfigurationen vorbereitet und manuell aus
`Buchhaltung > Exporte` gestartet.
