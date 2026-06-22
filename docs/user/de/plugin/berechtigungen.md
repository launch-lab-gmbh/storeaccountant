---
title: Berechtigungen
description: Weisen Sie Backend-Rollen gezielt StoreAccountant-Berechtigungen zu.
---

# Berechtigungen

StoreAccountant verwendet eigene WordPress-Berechtigungen für seine Backend-Aktionen. Administratoren haben immer alle
StoreAccountant-Berechtigungen. Andere Backend-Rollen können Sie gezielt freischalten.

Sie finden die Einstellungen unter:

`Plugins > Installierte Plugins > StoreAccountant > Einstellungen > Berechtigungen`

## Grundprinzip

Jede Berechtigung steht für eine konkrete Aktion, zum Beispiel:

- StoreAccountant-Adminbereich öffnen.
- Exporte anzeigen.
- Exporte erstellen.
- Exportdateien herunterladen.
- Exportkonfigurationen erstellen oder bearbeiten.
- Feldzuordnungen bearbeiten.
- Plugin-Einstellungen verwalten.
- Download-Passwörter anzeigen.
- Diagnose-Protokollierung verwalten.

Wenn eine Rolle mindestens eine StoreAccountant-Aktion erhält, bekommt sie automatisch die grundlegende
StoreAccountant-Adminzugriffsberechtigung.

## Rollen zuweisen

1. Öffnen Sie `Plugins > Installierte Plugins > StoreAccountant > Einstellungen > Berechtigungen`.
2. Suchen Sie die gewünschte Aktion.
3. Aktivieren Sie die Backend-Rollen, die diese Aktion ausführen dürfen.
4. Speichern Sie die Einstellungen.

[Screenshot: Berechtigungsmatrix]

StoreAccountant zeigt bewusst nur Rollen an, die grundsätzlich für das Backend geeignet sind. Reine Frontend-Rollen
wie Kunden oder Abonnenten erscheinen normalerweise nicht.

## Typische Rollenmodelle

Für Buchhaltungsmitarbeiter reicht häufig:

- Adminbereich öffnen.
- Exporte anzeigen.
- Exporte erstellen.
- Exportdateien herunterladen.
- Exportkonfigurationen anzeigen.

Für Benutzer, die Vorlagen pflegen sollen, ergänzen Sie:

- Exportkonfigurationen erstellen.
- Exportkonfigurationen bearbeiten.
- Feldzuordnungen bearbeiten.

Für technische Administratoren ergänzen Sie:

- Plugin-Einstellungen verwalten.
- Berechtigungen verwalten.
- Diagnose-Protokollierung verwalten.
- Download-Passwörter anzeigen, falls diese im Backend sichtbar sein sollen.

## Sicherheitshinweise

Exportdateien können sensible Kunden-, Bestell-, Steuer- und Rechnungsdaten enthalten. Vergeben Sie Download- und
Konfigurationsrechte daher nur an Rollen, die diese Daten wirklich benötigen.

Die Berechtigung `Download-Passwörter anzeigen` ist besonders sensibel. Sie ist nicht notwendig, um bekannte Passwörter
auf der Downloadseite einzugeben, erlaubt aber das Anzeigen gespeicherter Passwörter im Backend.
