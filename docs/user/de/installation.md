---
title: Installation
description: Installieren Sie StoreAccountant über die WordPress-Plugin-Suche, per ZIP-Upload oder per FTP.
---

# Installation

StoreAccountant ist ein WordPress-Plugin für WooCommerce. Sie benötigen eine WordPress-Installation mit aktivem
WooCommerce und ein Benutzerkonto, das Plugins installieren und aktivieren darf.

Nach der Aktivierung finden Sie StoreAccountant im WordPress-Backend unter `Buchhaltung > Exporte`. Die
Plugin-Einstellungen öffnen Sie über `Plugins > Installierte Plugins > StoreAccountant > Einstellungen`.

## Installation über die WordPress-Plugin-Suche

Dies ist der normale Installationsweg für die meisten Shops.

1. Öffnen Sie im WordPress-Backend `Plugins > Installieren`.
2. Suchen Sie nach `StoreAccountant`.
3. Klicken Sie bei StoreAccountant auf `Jetzt installieren`.
4. Klicken Sie nach der Installation auf `Aktivieren`.
5. Öffnen Sie anschließend `Buchhaltung > Exporte`.

[Screenshot: Plugin-Suche im WordPress-Backend]

## Installation per ZIP-Upload

Nutzen Sie diesen Weg, wenn Sie eine Plugin-ZIP-Datei aus einem Release installieren möchten.

1. Laden Sie die aktuelle StoreAccountant-ZIP-Datei aus dem neuesten GitHub-Release herunter:
   [github.com/launch-lab-gmbh/storeaccountant/releases/latest](https://github.com/launch-lab-gmbh/storeaccountant/releases/latest)
2. Öffnen Sie im WordPress-Backend `Plugins > Installieren`.
3. Klicken Sie oben auf `Plugin hochladen`.
4. Wählen Sie die ZIP-Datei aus.
5. Klicken Sie auf `Jetzt installieren`.
6. Aktivieren Sie das Plugin nach der Installation.
7. Öffnen Sie `Buchhaltung > Exporte`.

[Screenshot: ZIP-Upload im WordPress-Backend]

## Installation per FTP

Dieser Weg ist sinnvoll, wenn der WordPress-Adminbereich keine ZIP-Uploads erlaubt oder die Installation manuell über
den Server erfolgen soll.

1. Laden Sie die aktuelle StoreAccountant-ZIP-Datei aus dem neuesten GitHub-Release herunter:
   [github.com/launch-lab-gmbh/storeaccountant/releases/latest](https://github.com/launch-lab-gmbh/storeaccountant/releases/latest)
2. Entpacken Sie die ZIP-Datei lokal.
3. Laden Sie den entpackten Plugin-Ordner per FTP in das WordPress-Verzeichnis `wp-content/plugins/`.
4. Öffnen Sie im WordPress-Backend `Plugins > Installierte Plugins`.
5. Aktivieren Sie StoreAccountant.
6. Öffnen Sie `Buchhaltung > Exporte`.

[Screenshot: Aktivierung in der Plugin-Liste]

## Nach der Installation

Prüfen Sie zuerst die Grundeinstellungen:

1. Öffnen Sie `Plugins > Installierte Plugins > StoreAccountant > Einstellungen`.
2. Prüfen Sie im Tab `Speicherorte`, ob mindestens ein Speicherort aktiv ist.
3. Prüfen Sie im Tab `Sicherheit`, ob ein globales Download-Passwort vorhanden ist.
4. Prüfen Sie im Tab `Transporte`, wie StoreAccountant Hintergrundjobs verarbeitet.
5. Weisen Sie bei Bedarf im Tab `Berechtigungen` weiteren Backend-Rollen Zugriff zu.

Eine ausführliche Beschreibung finden Sie unter [Plugin konfigurieren](plugin/konfiguration.md).
