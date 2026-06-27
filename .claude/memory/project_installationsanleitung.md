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

## System-Stammdaten die automatisch bei Erstinstallation angelegt werden müssen

Analog zu **Jarvis** (Benutzer id=2, username='system') gibt es System-Stammdaten die fix vorhanden sein müssen:

| Was | Artikelnummer | Zweck | Referenz im Code |
|---|---|---|---|
| Diverses (Kasse) | `99-9999` | FK-Platzhalter in auftrag_positionen für freie Kassen-Positionen (Divers-Artikel ohne echtem Artikel-Datensatz) | `KassenService::getDiversArtikelId()` sucht via artikelnummer, keine hardcodierte ID |

**Warum:** `auftrag_positionen.artikel_id NOT NULL` — Divers-Positionen an der Kasse brauchen einen echten Artikel-FK, sonst fehlen sie in der Auftrags-Übersicht.

**Wichtig für Installation:** Dieser Artikel muss VOR der ersten Kassenbuchung existieren. `getDiversArtikelId()` fällt graceful zurück (überspringt die Position wenn nicht gefunden), aber das ist nur ein Fallback. Korrekt: Migration 078 bei Erstinstallation ausführen.

## Zielgruppe

- Jacky selbst (beim Umzug auf Produktiv-Server)
- Weitergabe an andere Betriebe (MeaLana ERP als Produkt)

**Why:** Composer-Pakete (Twig, Dompdf, Barcode-Generator) müssen auf jedem Server neu via `composer install` installiert werden — vendor/ wird nicht im Git eingecheckt.
**How to apply:** Beim Produktiv-Umzug daran erinnern; Anleitung rechtzeitig vor dem Umzug schreiben.
