---
title: Plugin konfigurieren
description: Konfigurieren Sie Speicherorte, Rechnungsanbieter, Hintergrundverarbeitung, Sicherheit, Berechtigungen und Diagnose.
---

# Plugin konfigurieren

Die StoreAccountant-Einstellungen öffnen Sie über:

`Plugins > Installierte Plugins > StoreAccountant > Einstellungen`

Die sichtbaren Tabs hängen von Ihren Berechtigungen ab. Administratoren haben immer Zugriff auf alle
StoreAccountant-Bereiche.

## Speicherorte

Pfad im Backend:

`Plugins > Installierte Plugins > StoreAccountant > Einstellungen > Speicherorte`

Hier wählen Sie, welche Speicherorte für neue Exporte und Exportkonfigurationen auswählbar sind. Die eingebaute
Speicherart ist `Lokal`. Sie speichert Exportarchive geschützt unterhalb von `wp-content/uploads/storeaccountant`.

Wenn nur ein Speicherort verfügbar ist, bleibt er automatisch aktiv und kann nicht deaktiviert werden.

Mindestens ein Speicherort muss aktiv sein, damit Exporte gestartet und Konfigurationen gespeichert werden können.

## Rechnungsanbieter

Pfad im Backend:

`Plugins > Installierte Plugins > StoreAccountant > Einstellungen > Rechnungsanbieter`

Hier wählen Sie, welcher aktive Rechnungsanbieter Rechnungsfelder oder Rechnungsanhänge für Exporte bereitstellen darf.
Aktuell unterstützt StoreAccountant das Plugin
`PDF Invoices & Packing Slips for WooCommerce`, sofern es im Shop aktiv ist.

Es kann immer nur ein Rechnungsanbieter gleichzeitig aktiviert sein. Wenn kein unterstütztes Rechnungsplugin aktiv ist,
zeigt StoreAccountant einen entsprechenden Hinweis.

## Transporte

Pfad im Backend:

`Plugins > Installierte Plugins > StoreAccountant > Einstellungen > Transporte`

Transporte steuern, wie StoreAccountant Hintergrundjobs an die Verarbeitung übergibt. Exporte werden nicht als langer
Browser-Request ausgeführt, sondern als Hintergrundjobs verarbeitet.

In der Regel ist der Action-Scheduler-Transport die passende Auswahl, weil er mit der kostenlosen WooCommerce- und
WordPress-Infrastruktur arbeitet. Ein synchroner Transport kann für einfache lokale Tests hilfreich sein, ist für echte
Shops aber meistens nicht die beste Wahl.

### Loopback-Requests bei geschützten Websites

Für manuell gestartete Hintergrundexporte stößt StoreAccountant den Queue-Runner über einen internen HTTP-Loopback-
Request an:

`/storeaccountant/queue-loopback/`

Wenn die Website durch Basic Auth, `.htaccess`-Regeln, IP-Beschränkungen, einen Wartungsmodus oder ähnlichen
serverseitigen Zugriffsschutz geschützt ist, muss dieser Request vom Server selbst erlaubt werden. Andernfalls starten
Export-Batches möglicherweise nicht sofort und die Verarbeitung hängt davon ab, dass ein anderer Queue-Runner oder
WordPress-Cron die wartenden Jobs später aufgreift.

Der Loopback-Endpunkt akzeptiert keine beliebigen Requests. Jeder Request muss die Export-ID und einen temporären Token
enthalten, der nur für diesen Export gültig ist. Sie können daher den oben genannten Pfad freigeben, ohne den
WordPress-Adminbereich oder den generischen Endpunkt `wp-admin/admin-post.php` freizugeben.

## Berechtigungen

Pfad im Backend:

`Plugins > Installierte Plugins > StoreAccountant > Einstellungen > Berechtigungen`

Hier weisen Sie Backend-Rollen einzelne StoreAccountant-Aktionen zu, zum Beispiel Exporte ansehen, Exporte erstellen,
Exportdateien herunterladen oder Exportkonfigurationen bearbeiten.

Details finden Sie unter [Berechtigungen](berechtigungen.md).

## Sicherheit

Pfad im Backend:

`Plugins > Installierte Plugins > StoreAccountant > Einstellungen > Sicherheit`

Hier verwalten Sie das globale Download-Passwort für geschützte Exportdownloads. Lassen Sie das Feld leer, wenn das
aktuelle Passwort erhalten bleiben soll.

Details finden Sie unter [Download-Passwortschutz](../exports/downloads-passwortschutz.md).

## Diagnose

Pfad im Backend:

`Plugins > Installierte Plugins > StoreAccountant > Einstellungen > Diagnose`

Die Diagnose-Protokollierung ist standardmäßig deaktiviert. Aktivieren Sie sie nur, wenn Sie technische Fehler
untersuchen oder dem Support ein Diagnosepaket bereitstellen möchten.

Details finden Sie unter [Diagnosepakete](../Diagnostik.md).

## Zurück zur Exportübersicht

Auf der Einstellungsseite führt die Schaltfläche `Zurück zu Buchhaltung` wieder zu `Buchhaltung > Exporte`.
