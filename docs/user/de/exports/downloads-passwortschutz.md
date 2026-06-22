---
title: Download-Passwortschutz
description: Verstehen Sie globale, konfigurationsbezogene und exportspezifische Download-Passwörter.
---

# Download-Passwortschutz

StoreAccountant schützt öffentliche Exportdownloads mit einem Download-Token und einem Passwort. Der Token findet den
Exportdatensatz, ersetzt aber kein Passwort. Für den eigentlichen Zugriff muss das passende Download-Passwort
eingegeben werden.

## Wo Passwörter verwaltet werden

Es gibt drei Ebenen:

- Globales Passwort: `Plugins > Installierte Plugins > StoreAccountant > Einstellungen > Sicherheit`.
- Konfigurationspasswort: `Buchhaltung > Exporte > Exportkonfigurationen > Konfiguration öffnen`.
- Quick-Export-Passwort: im zweiten Schritt des Quick-Export-Formulars.

## Globales Download-Passwort

Das globale Passwort wird als Standard verwendet. Es wird bei Aktivierung oder beim ersten Initialisieren der
Einstellungen angelegt.

Sie ändern es unter:

`Plugins > Installierte Plugins > StoreAccountant > Einstellungen > Sicherheit`

Geben Sie im Feld `Globales Download-Passwort` ein neues Passwort ein und speichern Sie die Einstellungen. Wenn Sie das
Feld leer lassen, bleibt das vorhandene Passwort erhalten.

Benutzer mit der Berechtigung zum Anzeigen von Download-Passwörtern können das aktuelle Passwort im Backend sehen.

## Passwort in Exportkonfigurationen

Eine Exportkonfiguration speichert ihr eigenes Download-Passwort. Wenn Sie beim Speichern einer Konfiguration kein
Passwort eingeben, speichert StoreAccountant das aktuell globale Download-Passwort auf dieser Konfiguration.

Wichtig: Spätere Änderungen am globalen Passwort ändern nicht automatisch bereits gespeicherte Konfigurationspasswörter.
Öffnen und speichern Sie die Konfiguration mit einem neuen Passwort, wenn sie ein anderes Passwort verwenden soll.

## Passwort bei Quick Exports

Beim Quick Export können Sie ein Passwort direkt im Exportformular eintragen. Wenn Sie das Feld leer lassen, verwendet
StoreAccountant das aktuelle globale Download-Passwort für diesen Export.

## Exporte speichern Passwort-Snapshots

Jeder Export speichert beim Start das zu diesem Zeitpunkt wirksame Passwort. Dadurch bleiben ältere Downloads
nachvollziehbar und unabhängig von späteren Passwortänderungen.

Beispiele:

- Sie ändern das globale Passwort heute. Ein gestern erzeugter Quick Export verwendet weiterhin das alte Passwort.
- Sie ändern das Passwort einer Exportkonfiguration. Bereits erzeugte Exporte aus dieser Konfiguration behalten ihr
  altes Passwort.
- Ein neuer Export aus der geänderten Konfiguration verwendet das neue Konfigurationspasswort.

## Passwort anzeigen

Ob ein Benutzer gespeicherte Passwörter im Backend sehen darf, steuern Sie über die Berechtigung
`Download-Passwörter anzeigen`. Diese Einstellung finden Sie unter
`Plugins > Installierte Plugins > StoreAccountant > Einstellungen > Berechtigungen`.

Ohne diese Berechtigung zeigt StoreAccountant Passwörter als geschützt an. Downloads können trotzdem funktionieren,
wenn der Benutzer das Passwort kennt.

## Technische Voraussetzung

Für passwortgeschützte Downloads benötigt der Server Sodium oder OpenSSL. Wenn keines davon verfügbar ist, deaktiviert
StoreAccountant Passwortfelder und blockiert die Erstellung öffentlicher Downloads, statt ungeschützte Exportlinks zu
erzeugen.
