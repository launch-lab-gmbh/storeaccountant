---
title: Exporte verwalten und herunterladen
description: Verfolgen Sie Exportstatus, öffnen Sie Exportdetails, laden Sie fertige Dateien herunter und analysieren Sie Fehler.
---

# Exporte verwalten und herunterladen

Die Exportliste befindet sich unter `Buchhaltung > Exporte`. Sie zeigt alle gespeicherten Exportläufe.

Jeder Export bleibt als eigener Datensatz erhalten. Dadurch können Sie später nachvollziehen, wann ein Export erstellt
wurde, wer ihn gestartet hat, welche Konfiguration verwendet wurde und wo die Datei abgelegt wurde.

## Spalten in der Exportliste

Die Exportliste zeigt unter anderem:

- `Titel`: Name des Exportlaufs.
- `Fortschritt`: aktueller Fortschritt bei Hintergrundverarbeitung.
- `Exportiert am`: Zeitpunkt des Exports.
- `Exporttyp`: Bestellungen, Kunden oder Produkte.
- `Exportformat`: CSV oder JSON.
- `Ausgelöst von`: Benutzer, der den Export gestartet hat.
- `Konfiguration`: Quick Export oder Name der gespeicherten Konfiguration.
- `Status / Download`: aktueller Status und Downloadaktion, sobald verfügbar.

[Screenshot: Exportliste mit Status und Download]

## Exportstatus

StoreAccountant verwendet diese Statuswerte:

- `Wartet`: Der Export wurde angelegt und wartet auf Verarbeitung.
- `In Verarbeitung`: StoreAccountant verarbeitet die Daten im Hintergrund.
- `Abgeschlossen`: Die Datei wurde erzeugt und kann heruntergeladen werden.
- `Fehlgeschlagen`: Die Verarbeitung konnte nicht abgeschlossen werden.

Die Liste wird für wartende und laufende Exporte automatisch aktualisiert.

## Exportdetails öffnen

Klicken Sie in `Buchhaltung > Exporte` auf den Titel eines Exports, um die Detailansicht zu öffnen.

Der Tab `Exportdetails` zeigt zum Beispiel:

- Export-ID, Titel und Status.
- Aktueller Verarbeitungsschritt.
- Batches, Datensätze und Fortschritt.
- Startzeit, Endzeit und Laufzeit.
- Exporttyp, Exportformat und Speicherort.
- Speicherreferenz und Dateigröße.
- Auslösender Benutzer und zugehörige Konfiguration.
- Downloadlink und Download-Passwort, sofern Sie die Berechtigung zum Anzeigen haben.

Der Tab `Rohdaten` zeigt technische Exportkonfigurationsdaten. Er ist nützlich, wenn Support oder Entwicklung prüfen
müssen, welche Einstellungen für einen Export gespeichert wurden.

Der Tab `Log` erscheint nur für Benutzer mit entsprechender Berechtigung. Er zeigt technische Verarbeitungseinträge des
Exports.

## Datei herunterladen

Ein abgeschlossener Export kann aus der Liste oder aus der Detailansicht heruntergeladen werden.

1. Öffnen Sie `Buchhaltung > Exporte`.
2. Warten Sie, bis der Status `Abgeschlossen` angezeigt wird.
3. Klicken Sie auf die Downloadaktion.
4. Geben Sie auf der Downloadseite das passende Download-Passwort ein.
5. Laden Sie die Exportdatei herunter.

Bei lokalem Speicher legt StoreAccountant die erzeugten Dateien geschützt unterhalb von
`wp-content/uploads/storeaccountant` ab. Der Download erfolgt über einen geschützten Link, nicht über einen frei
erratbaren Dateipfad.

Mehr dazu finden Sie unter [Download-Passwortschutz](downloads-passwortschutz.md).

## Fehlgeschlagene Exporte

Wenn ein Export fehlschlägt:

1. Öffnen Sie den Export über `Buchhaltung > Exporte`.
2. Prüfen Sie im Tab `Exportdetails` die Fehlermeldung.
3. Prüfen Sie, ob Speicherort, Exportformat, Passwortschutz und Hintergrundverarbeitung korrekt konfiguriert sind.
4. Öffnen Sie bei Bedarf den Tab `Log`.
5. Aktivieren Sie für Supportfälle die [Diagnosepakete](../Diagnostik.md).

Wenn die Ursache nur vorübergehend war, kann ein Export je nach angezeigter Aktion erneut gestartet werden.
