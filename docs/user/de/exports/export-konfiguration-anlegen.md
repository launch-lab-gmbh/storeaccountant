---
title: Exportkonfiguration anlegen
description: Erstellen und bearbeiten Sie wiederverwendbare Exportvorlagen für Bestellungen, Kunden und Produkte.
---

# Exportkonfiguration anlegen

Exportkonfigurationen sind Vorlagen für wiederkehrende Exporte. Sie speichern die fachlichen Einstellungen eines
Exports, ohne sofort eine Exportdatei zu erzeugen.

Sie finden die Konfigurationen unter `Buchhaltung > Exporte > Exportkonfigurationen`.

## Neue Konfiguration erstellen

1. Öffnen Sie `Buchhaltung > Exporte`.
2. Wechseln Sie in den Tab `Exportkonfigurationen`.
3. Klicken Sie auf `Exportkonfiguration erstellen`.
4. Vergeben Sie einen `Konfigurationstitel`.
5. Wählen Sie den `Exporttyp`.
6. Klicken Sie auf `Konfiguration speichern`.

[Screenshot: Neue Exportkonfiguration]

Nach dem ersten Speichern öffnet StoreAccountant die Detailansicht der neuen Konfiguration. Erst danach erscheinen die
typabhängigen Einstellungen wie Filter, Exportformat, Speicherort, Download-Passwort und Feldzuordnung.

Der Exporttyp kann nach dem Erstellen nicht mehr geändert werden. Wenn Sie einen anderen Exporttyp benötigen, erstellen
Sie eine neue Konfiguration.

## Allgemeine Einstellungen

Diese Felder sind je nach Exporttyp verfügbar:

- `Konfigurationstitel`: Name der Vorlage in der Konfigurationsliste und in der Exportauswahl.
- `Exporttyp`: Bestellungen, Kunden oder Produkte. Nach dem ersten Speichern gesperrt.
- `Exportformat`: Ausgabeformat, zum Beispiel CSV oder JSON.
- `Speicherort`: Ziel, an dem StoreAccountant die Exportdatei ablegt.
- `Download-Passwort`: Passwort für spätere Downloads aus Exporten, die mit dieser Konfiguration gestartet werden.
- `Batch-Größe`: Anzahl der Datensätze pro Verarbeitungsschritt. Der Standardwert ist `100`, der Mindestwert `10`.

Wenn kein Speicherort verfügbar ist, öffnen Sie `Plugins > Installierte Plugins > StoreAccountant > Einstellungen >
Speicherorte` und aktivieren Sie mindestens einen Speicherort.

## Bestellkonfiguration

Bei Bestellungen legen Sie fest:

- welches Bestelldatumsfeld für den Zeitraum gilt
- welcher Zeitraum exportiert wird
- welche Bestellstatus enthalten sind
- wie Steuerfelder aufgebaut werden
- ob Rechnungsfelder oder Rechnungsanhänge verfügbar sind, sofern ein unterstützter Rechnungsanbieter aktiv ist

Bei `Steuerfelder` stehen je nach Installation einfache oder erweiterte Steuerfelder zur Auswahl. Die erweiterte
Variante kann Steuerwerte nach WooCommerce-Steuersätzen aufteilen.

## Kundenkonfiguration

Bei Kunden legen Sie fest:

- welcher Zeitraum nach Kundenerstellung gilt
- ob das Rechnungsland oder Lieferland als Länderfilter verwendet wird
- welche Länder enthalten sind

Die Länderauswahl zeigt nur Länder, die in den vorhandenen WooCommerce-Kundendaten vorkommen.

## Produktkonfiguration

Bei Produkten legen Sie fest:

- welcher Zeitraum nach Produkterstellung gilt
- ob nur Elternprodukte exportiert werden
- ob Varianten als eigene Exportzeilen exportiert werden

## Feldzuordnung bearbeiten

Nach dem Speichern zeigt StoreAccountant zusätzliche Tabs für die Konfiguration. Bei den eingebauten Exporttypen gibt
es einen Tab `Feldzuordnung`. Dort legen Sie fest, welche Spalten exportiert werden und in welcher Reihenfolge sie in
der Datei erscheinen.

Eine ausführliche Anleitung finden Sie unter [Feldzuordnung](feldzuordnung.md).

## Konfiguration verwenden

Eine gespeicherte Konfiguration starten Sie über `Buchhaltung > Exporte` im Bereich `Neuen Export erstellen`.
Wählen Sie dort die Konfiguration aus, vergeben Sie einen Exporttitel und starten Sie den Export.

Bestehende Exporte bleiben erhalten, auch wenn die zugehörige Konfiguration später gelöscht wird. In den Exportdetails
wird dann angezeigt, dass die Konfiguration gelöscht wurde.
