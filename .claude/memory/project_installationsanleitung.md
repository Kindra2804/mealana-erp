---
name: project-installationsanleitung
description: "Geplante Installationsanleitung: Server-Setup von 0, Composer, Migrations, Cronjobs — für Jacky + Weitergabe"
metadata: 
  node_type: memory
  type: project
  originSessionId: 3bbaa246-5729-4e26-8fb8-4785822652ed
---

## Status: GEPLANT — noch nicht geschrieben

Wird erstellt wenn System produktionsreif ist (oder kurz davor).

## Was rein muss

- PHP-Version + Extensions (GD, PDO, mbstring, intl, zip ...)
- MariaDB/MySQL Setup + Datenbank anlegen
- XAMPP für Windows-Lokalbetrieb vs. Apache/Nginx auf Linux-Server
- Composer installieren + `composer install` im Projektverzeichnis
- Alle Migrations ausführen (`php migrate.php` o.ä.)
- `storage/`-Verzeichnisse anlegen + Schreibrechte setzen
- Cronjobs einrichten (Mahnwesen, WC-Sync, Aktionen-DROPS)
- WireGuard VPN-Konfiguration (Remote-Zugriff)
- system_einstellungen Basis-Konfiguration (Firmenname, UID, IBAN ...)
- RKSV/BFR-BONit Registrierung + Kassen-Konfiguration (AT-Pflicht)
- Erster Benutzer (superadmin) anlegen

## Zielgruppe

- Jacky selbst (beim Umzug auf Produktiv-Server)
- Weitergabe an andere Betriebe (MeaLana ERP als Produkt)

**Why:** Composer-Pakete (Twig, Dompdf, Barcode-Generator) müssen auf jedem Server neu via `composer install` installiert werden — vendor/ wird nicht im Git eingecheckt.
**How to apply:** Beim Produktiv-Umzug daran erinnern; Anleitung rechtzeitig vor dem Umzug schreiben.
