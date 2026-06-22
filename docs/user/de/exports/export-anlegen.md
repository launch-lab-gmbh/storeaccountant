---
title: Export anlegen
description: Erstellen Sie einen Quick Export oder starten Sie einen Export aus einer gespeicherten Exportkonfiguration.
---

# Export anlegen

Sie starten neue Exporte unter `Buchhaltung > Exporte`. Oberhalb der Exportliste befindet sich der Bereich
`Neuen Export erstellen`.

StoreAccountant kann einen Export auf zwei Arten starten:

- `Quick Export`: einmaliger Export mit direkt eingegebenen Einstellungen.
- gespeicherte Exportkonfiguration: Export aus einer vorbereiteten Vorlage.

Jeder gestartete Export wird als Datensatz in der Exportliste gespeichert. Die Verarbeitung läuft im Hintergrund; Sie
können den Status in der Liste verfolgen und die Datei herunterladen, sobald der Export abgeschlossen ist.

## Quick Export erstellen

Ein Quick Export eignet sich für einmalige Auswertungen oder Tests.

1. Öffnen Sie `Buchhaltung > Exporte`.
2. Wählen Sie im Bereich `Neuen Export erstellen` die Option `Quick Export`.
3. Klicken Sie auf `Auswählen` oder die entsprechende Startaktion.
4. Vergeben Sie im Feld `Titel` einen eindeutigen Namen für den Export.
5. Wählen Sie im Feld `Exporttyp` aus, ob Bestellungen, Kunden oder Produkte exportiert werden sollen.
6. Klicken Sie auf `Weiter`.

[Screenshot: Quick Export mit Titel und Exporttyp]

Im zweiten Schritt konfigurieren Sie die Details:

1. Wählen Sie die Filter für den Exporttyp.
2. Wählen Sie das `Exportformat`, zum Beispiel CSV oder JSON.
3. Wählen Sie den `Speicherort`.
4. Geben Sie optional ein `Download-Passwort` ein. Wenn Sie das Feld leer lassen, wird das aktuelle globale
   Download-Passwort verwendet.
5. Prüfen Sie die `Batch-Größe`. Wenn Sie unsicher sind, lassen Sie den Standardwert `100` stehen.
6. Klicken Sie auf `Quick Export starten`.

[Screenshot: Quick Export Details]

Danach kehren Sie zur Exportliste zurück. Der neue Export erscheint mit einem Status wie `Wartet`, `In Verarbeitung`,
`Abgeschlossen` oder `Fehlgeschlagen`.

## Filter im Quick Export

Welche Filter erscheinen, hängt vom gewählten Exporttyp ab.

Bei Bestellexporten wählen Sie:

- `Bestelldatumsfeld`: legt fest, ob der Zeitraum auf Erstellungsdatum, Änderungsdatum, Abschlussdatum oder
  Zahlungsdatum angewendet wird.
- `Monat`: erlaubt `Dieser Monat`, `Letzter Monat`, `Alle Zeiträume` oder einen konkreten Monat.
- `Jahr`: erscheint bei konkreten Monaten.
- `Bestellstatus`: mindestens ein Status muss ausgewählt sein.

Bei Kundenexporten wählen Sie:

- `Monat`: Zeitraum für Kunden nach Erstellungsdatum.
- `Kundenland-Feld`: Rechnungsland oder Lieferland.
- `Kundenländer`: alle Länder, nicht zugewiesene Kunden oder ausgewählte Länder mit vorhandenen Kundenbestellungen.

Bei Produktexporten wählen Sie:

- `Monat`: `Dieser Monat`, `Letzter Monat` oder `Alle Zeiträume`.
- `Produktvarianten`: entweder nur Elternprodukte oder Varianten als eigene Exportzeilen.

## Export aus Konfiguration starten

Dieser Weg eignet sich, wenn Sie denselben Export regelmäßig mit denselben Spalten, Filtern und Sicherheitseinstellungen
erzeugen möchten.

1. Öffnen Sie `Buchhaltung > Exporte`.
2. Wählen Sie im Bereich `Neuen Export erstellen` eine gespeicherte Exportkonfiguration.
3. Vergeben Sie im Feld `Titel` einen eindeutigen Namen für den neuen Export.
4. Starten Sie den Export.

[Screenshot: Export aus gespeicherter Konfiguration starten]

StoreAccountant übernimmt Exporttyp, Filter, Exportformat, Speicherort, Batch-Größe, Download-Passwort und
Feldzuordnung aus der Konfiguration. Dynamische Zeiträume wie `Dieser Monat` oder `Letzter Monat` werden beim Start des
Exports auf den dann gültigen Zeitraum festgeschrieben.

Wenn noch keine passende Vorlage existiert, legen Sie zuerst eine
[Exportkonfiguration](export-konfiguration-anlegen.md) an.
