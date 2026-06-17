---
title: Diagnosepakete
description: Erfahren Sie, wie Sie die StoreAccountant-Diagnose aktivieren und ein Diagnosepaket an den Support senden.
---

# Diagnosepakete

Diagnosepakete helfen dem Support dabei, technische StoreAccountant-Fehler zu untersuchen, ohne dass interne
Fehlerdetails direkt in der Administrationsoberfläche angezeigt werden müssen.

Die Diagnose-Protokollierung ist standardmäßig deaktiviert. Ein Benutzer mit der passenden StoreAccountant-Berechtigung
kann sie in den Plugin-Einstellungen aktivieren.

## Diagnose-Protokollierung aktivieren

1. Öffnen Sie die StoreAccountant-Plugin-Einstellungen in WordPress.
2. Wechseln Sie zum Diagnose-Tab.
3. Aktivieren Sie die Diagnose-Protokollierung.
4. Speichern Sie die Einstellungen.

Nachdem die Diagnose-Protokollierung aktiviert wurde, kann StoreAccountant ein Diagnosepaket erstellen, wenn eine
unterstützte Admin-Aktion fehlschlägt, zum Beispiel beim Speichern einer Exportkonfiguration oder beim Ausführen eines
Exports.

## Was angezeigt wird

Wenn ein Fehler auftritt, zeigt StoreAccountant weiterhin die normale kurze Fehlermeldung an. Wenn ein Diagnosepaket
erstellt wurde und Ihr Benutzerkonto Diagnosepakete herunterladen darf, enthält der Hinweis zusätzlich:

- eine Support-ID für den protokollierten Fehler
- einen Link zum Herunterladen des Diagnosepakets

Senden Sie die heruntergeladene Datei und die Support-ID an Ihren StoreAccountant-Supportkontakt.

Wenn Sie nur die normale Fehlermeldung sehen, ist die Diagnose-Protokollierung möglicherweise deaktiviert, Ihr
Benutzerkonto hat eventuell keine Berechtigung zum Herunterladen von Diagnosepaketen, oder für diesen konkreten Fehler
wurde kein Diagnosepaket erstellt.

## Was das Paket enthält

Das Paket enthält technische Informationen zur fehlgeschlagenen StoreAccountant-Aktion, zum Beispiel Fehlerquelle,
Support-ID, Zeitpunkt, Plugin-Kontext, Fehlercodes und Exception-Details.

Das Paket soll keine Passwörter, Zugriffstoken, Nonces, Authentifizierungs-Cookies oder Inhalte generierter Exportdateien
enthalten. Je nach fehlgeschlagener Aktion können technische IDs enthalten sein, zum Beispiel IDs von
Exportkonfigurationen oder Storage-Adaptern. Prüfen Sie die Datei nach den Vorgaben Ihres Unternehmens, bevor Sie sie
außerhalb Ihrer Organisation versenden.

## WordPress Debug Log

StoreAccountant sendet den Diagnoseeintrag zusätzlich an den WordPress-Debug-Log-Mechanismus. Dieser Eintrag erscheint
nur dann in der WordPress-Debug-Logdatei, wenn die WordPress-Installation für das Schreiben von Debug-Logs konfiguriert
ist.
