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
   - Pre-Sync: Artikelkatalog + Messe-Lager-Stand + Preise → lokal im Browser (IndexedDB)
2. Während Messe (vollständig offline):
   - Kasse arbeitet auf lokaler IndexedDB (kein SQLite, kein lokaler Server nötig — siehe [[project_kassen_verwaltung]] für die Architekturentscheidung 2026-07-03)
   - RKSV: Browser ruft BFR-Dienst direkt per `fetch()` an (127.0.0.1:8787), Signaturkarte am Messe-Laptop (kein Internet nötig!)
   - Nur Abgänge aus Messe-Lager
   - Kunden = Laufkunde (kein Kundendatensatz nötig — Verschlüsselungskey verlässt den Server ohnehin nie)
3. Nach Messe (zurück im lokalen Netz):
   - Post-Sync: Kassenbuchungen → ERP Umsatz, Lagerabgänge → Messe-Lager-Buchungen (Server-API dafür bereits fertig: `MesseSyncService::postSyncVerarbeiten()`/`rueckkehrVerarbeiten()`)
   - RKSV-Belegkette → archivieren
   - Restbestand → Umlagerung zurück ins Hauptlager

**Sync-Konflikte:** minimal bis keine, weil Messe-Lager isoliert ist und niemand parallel darauf bucht.
**Korrektur 2026-07-03:** ursprünglich war "lokale SQLite" geplant — bewusst verworfen zugunsten von IndexedDB + direktem Browser→BFR-Call, um dauerhafte Pflege zweier SQL-Dialekte (MariaDB vs. SQLite) zu vermeiden. Details siehe [[project_kassen_verwaltung]].

## Datenschutz-Gewinn

- WireGuard VPN statt Port-Forwarding → kein öffentlicher Endpunkt am ERP
- Kundendaten bleiben lokal (AES-256-GCM in DB, wie gebaut)
- Backups: lokaler externer Datenträger + verschlüsselt Cloud (z.B. Backblaze B2 + rclone, ~2€/Monat)
- MS SQL Express → MariaDB: kein 10GB-Limit mehr

**Why:** Diskussion 2026-06-23: Sorge über Offline-Resilienz + Datenschutz beim Umstieg auf eigenes ERP.
**How to apply:** Kasse-Modul mit SQLite-Offline-Modus + Pre/Post-Sync planen; BFR immer lokal am Gerät installiert.

## Update 2026-07-03: WireGuard VPN tatsächlich umgesetzt (nicht mehr nur geplant)

Server-PC (192.168.178.222, statische lokale IP — dort läuft auch der JTL-WAWI-Server mit eigener Portfreigabe) hat jetzt XAMPP+MariaDB (siehe [[project_installationsanleitung]]) UND WireGuard produktiv laufen.

- Adressschema: Server = `10.13.13.1/24`, Clients fortlaufend ab `10.13.13.2`. Port `51820/UDP` am Router (UPC/Magenta Fiber Box — nur klassische Portweiterleitung, kein eigenes VPN-Menü nötig) auf die Server-IP weitergeleitet. Bestehender no-ip-DDNS-Hostname wiederverwendet.
- Vollständige Schritt-für-Schritt-Anleitung inkl. "weiteren Client hinzufügen" steht in `docs/installation.md` Anhang C.
- **Zwei Stolpersteine beim Ersteinsatz, für nächstes Mal:** Windows-Firewall blockt auf dem neuen virtuellen WireGuard-Adapter standardmäßig sowohl ICMP (Ping) als auch TCP/80 (Apache) — beides braucht eine explizite `netsh advfirewall`-Freigabe auf dem Server, sonst Timeout trotz technisch funktionierendem Tunnel (sent/received zählt in der WireGuard-App schon hoch).
- Jeder weitere PC (z.B. Barbaras Büro-PC) braucht ein **eigenes** Schlüsselpaar + eigene `10.13.13.X`-Adresse, nie geteilte Client-Daten (Begründung siehe Anhang C in der Anleitung).
**How to apply:** Bei jedem weiteren VPN-Client diese Memory + Anhang C konsultieren statt von Null zu recherchieren.
