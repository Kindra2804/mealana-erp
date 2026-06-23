---
name: project-infrastruktur
description: "Geplantes Server/Netzwerk-Setup MeaLana (lokaler Server, VPN, Messe-Kasse Offline)"
metadata: 
  node_type: memory
  type: project
  originSessionId: 7c2206d0-2966-4b33-8077-f725a9bdff96
---

## Aktuelles Setup (Ist-Stand)

- **Server-PC**: Windows, MS SQL Server Express (10GB-Limit!), per Port-Forwarding + no-ip aus Internet erreichbar
- **Büro-PC (Babsi)**: lokales Netz, ERP-Arbeit
- **Packplatz-PC**: lokales Netz, Picklisten/Labels
- **Kasse 1 (Luwosoft)**: lokales Netz, eigenständig
- **Kasse 2 / Messe**: lokales Netz ODER eigener Internetanschluss, Luwosoft
- **Homeoffice (Jacky)**: Vollzugriff/Superadmin via Port-Forwarding
- **3 Webspaces**: WooCommerce-Shops, je eigene DB

## Ziel-Setup

**Server-PC (derselbe Windows-PC):**
- XAMPP + MariaDB (statt MS SQL) + PHP
- ERP läuft lokal auf Port 80
- Alle lokalen PCs: Browser → `http://erp.local`

**Remote-Zugang (Homeoffice Jacky):**
- WireGuard VPN ins lokale Netz → Browser → erp.local
- Ersetzt Port-Forwarding + no-ip komplett → sicherer (DSGVO!)

**Shops:**
- Bleiben auf Webspace mit eigenen DBs
- ERP synct via WooCommerce REST-API (kein direkter DB-Zugriff)

**Kasse 1 (Laden):**
- Eigenständig lokal, BFR-Dienst + Signaturkarte am Kassen-PC

**Messe-Kasse (Variante B — Offline):**
→ siehe unten

## Offline-Resilienz (Ziel = gleich wie heute)

| Szenario | Heute | Zukunft |
|---|---|---|
| Internetausfall | Kasse/Büro/Packplatz laufen ✓ | gleich ✓ |
| Homeoffice bei Ausfall | kein Zugang ✗ | kein VPN → kein Zugang ✗ |
| Shops bei Ausfall | laufen, kein Sync ✗ | gleich ✗ |

## Messe-Kasse: Variante B (Vollständig Offline)

**Warum machbar:** Messe-Kasse bedient sich ausschließlich vom Messe-Lager (K2, umschaltbar). Dadurch keine Sync-Konflikte mit dem Hauptlager.

**Ablauf:**
1. Tag vorher (noch im lokalen Netz):
   - Ware → Umlagerung Hauptlager → Messe-Lager (K2)
   - Pre-Sync: Artikelkatalog + Messe-Lager-Stand + Preise → lokale SQLite am Messe-Laptop
2. Während Messe (vollständig offline):
   - Kasse arbeitet auf lokaler SQLite
   - RKSV: BFR-Dienst + Signaturkarte direkt am Messe-Laptop (kein Internet nötig!)
   - Nur Abgänge aus Messe-Lager
   - Kunden = Laufkunde (kein Kundendatensatz nötig)
3. Nach Messe (zurück im lokalen Netz):
   - Post-Sync: Kassenbuchungen → ERP Umsatz, Lagerabgänge → Messe-Lager-Buchungen
   - RKSV-Belegkette → archivieren
   - Restbestand → Umlagerung zurück ins Hauptlager

**Sync-Konflikte:** minimal bis keine, weil Messe-Lager isoliert ist und niemand parallel darauf bucht.

## Datenschutz-Gewinn

- WireGuard VPN statt Port-Forwarding → kein öffentlicher Endpunkt am ERP
- Kundendaten bleiben lokal (AES-256-GCM in DB, wie gebaut)
- Backups: lokaler externer Datenträger + verschlüsselt Cloud (z.B. Backblaze B2 + rclone, ~2€/Monat)
- MS SQL Express → MariaDB: kein 10GB-Limit mehr

**Why:** Diskussion 2026-06-23: Sorge über Offline-Resilienz + Datenschutz beim Umstieg auf eigenes ERP.
**How to apply:** Kasse-Modul mit SQLite-Offline-Modus + Pre/Post-Sync planen; BFR immer lokal am Gerät installiert.
