---
name: project-rechte-rollen
description: "Rollen, Rechte, Lizenz-Instanzierung, Manager-Override — vollständiges Design (2026-06-27)"
metadata: 
  node_type: memory
  type: project
  originSessionId: c2be1558-2ea4-4ebb-8128-43fd08ac683b
---

## Zwei getrennte Konzepte

| Konzept | Tabelle | Bedeutung |
|---|---|---|
| **Lizenz** | `modul_lizenzen` | Was darf diese *Installation* (per Kunde gekauft) |
| **Rechte** | `benutzer_rollen` + `rollen_rechte` | Wer darf was *innerhalb* der Installation |

---

## Rollen (Hierarchie von oben nach unten)

| Rolle | Kernrechte | Geldgeschäfte | Besonderheit |
|---|---|---|---|
| **Super-Admin** | alles + Lizenzierung + Branding + Setup-Wizard | ✓ alles | Erster User bei Neuinstallation |
| **Admin** | alles außer Lizenzierung/Branding | ✓ alles | |
| **Manager** | alles außer Einstellungen | ✓ alles inkl. Gutschriften | Gibt Manager-Codes frei |
| **Kassier** | Kasse + Artikel lesen | ✓ Kasse (Verkauf/Rückgeld) — Auszahlungen nur mit Manager-Override | |
| **Lager** | Lager + Bestellwesen + Artikel lesen | ✗ | |
| **Packplatz** | Packplatz scan/versenden + Retouren erfassen | ✗ — Gutschrift braucht Manager-Bestätigung | Retoure anlegen ≠ Gutschrift auslösen |
| **Praktikant** | Artikel CRUD + Bilder hochladen (Datenwartung) | ✗ | Kein Dashboard-Zugriff; alles geloggt |
| **Readonly** | alle Module nur lesend | ✗ | |

**Why:** Alles was Geld bewegt ist heikel → extra Freigabe-Ebene.

---

## Manager-Override (Popup-Freigabe)

Überall wo Geld zurückfließt und der aktuelle Benutzer kein Manager/Admin ist:
- **Kasse: Auszahlung nach Retoure** (Bargeld raus)
- **Packplatz: Gutschrift auslösen**

**Ablauf (wie Lidl-Kassensystem):**
1. Kassier/Packplatz-User löst Aktion aus
2. Popup erscheint: "Manager-Freigabe erforderlich — Manager-Code eingeben"
3. Manager gibt seinen persönlichen PIN/Code ein
4. System prüft: Code gehört einem User mit Rolle ≥ Manager?
5. Ja → Aktion wird durchgeführt + Log-Eintrag mit beiden User-IDs (Auslöser + Freigebender)
6. Nein → Popup bleibt, Fehlermeldung, erneut versuchen

**Log-Eintrag:** `{ aktion: 'manager_override', ausgeloest_von: user_id, freigegeben_von: manager_id, kontext: 'kasse_auszahlung' }`

**How to apply:** Beim Bauen der Kasse-Retoure und Packplatz-Gutschrift: vor der Ausführung Manager-Override-Check einbauen. Separates Modal, eigener PHP-Endpunkt zur Code-Validierung.

---

## Atomare Rechte (ca. 25-30 Stück)

Werden **nicht einzeln vergeben** — nur über Rollen. Aber als Fangpunkte im Code:

```
artikel.lesen / artikel.erstellen / artikel.bearbeiten / artikel.loeschen
lager.lesen / lager.bewegung / lager.umbuchung
bestellwesen.lesen / bestellwesen.erstellen / bestellwesen.wareneingang
kasse.zugriff / kasse.auszahlung (→ Manager-Override)
auftraege.lesen / auftraege.erstellen / auftraege.stornieren
packplatz.zugriff / packplatz.retoure / packplatz.gutschrift (→ Manager-Override)
kunden.lesen / kunden.bearbeiten
einstellungen.lesen / einstellungen.bearbeiten
lizenz.verwalten (nur Super-Admin)
dashboard.zugriff (Praktikant: explizit NEIN)
```

**Fangpunkte:** Überall wo heute geloggt wird (`aktivitaeten`-Tabelle) ist ein natürlicher Ort für eine Rechteprüfung. Log-Kategorie und Recht haben dieselbe Struktur (`modul.aktion`).

---

## Lizenz-Instanzierung

`modul_lizenzen` bekommt eine zusätzliche Spalte:

```sql
ALTER TABLE modul_lizenzen ADD COLUMN max_instanzen INT NULL;
-- NULL = unbegrenzt, 1 = eine Instanz, 2 = zwei, usw.
```

| Modul-Code | max_instanzen | Bedeutung |
|---|---|---|
| `kasse` | 1 | nur K1 aktiv schaltbar |
| `kasse` | 3 | bis zu 3 Kassen |
| `shop_sync` | 1 | nur 1 Shop sync_aktiv |
| `shop_sync` | NULL | unbegrenzt viele Shops |

**Prüflogik:** Beim Aktivieren einer weiteren Instanz (Kasse aktivieren, Shop aktivieren):
- COUNT aktive Instanzen < max_instanzen? → OK
- Sonst: Fehlermeldung "Lizenz erlaubt nur X Instanzen"

---

## Lizenz-Pakete (Verkaufsmodell)

| Paket | Inhalt | max_instanzen |
|---|---|---|
| **Core** | Artikel, Lager, Lieferanten, Bestellwesen | — |
| **Verkauf** | Auftragsmodul, Dokumentenarchiv, Mahnwesen | — |
| **Kasse** | POS + Kassenbuch + RKSV | 1 Kasse |
| **Kasse Plus** | wie Kasse | 3 Kassen |
| **Partner** | Mietfächer, Kommission | — |
| **Shop-Sync** | WooCommerce-Adapter | 1 Shop |
| **Shop-Sync Plus** | WooCommerce-Adapter | unbegrenzt |
| **Buchhaltung** | DATEV-Export | — |

---

## Implementierungsreihenfolge (wenn es soweit ist)

1. `modul_lizenzen.max_instanzen` Migration
2. Rollen-Tabellen + Rechte-Tabellen (DB)
3. Login/Logout UI (Shell)
4. Benutzer-Profil UI
5. Rollen-Zuweisung im Admin
6. Rechteprüfung als PHP-Middleware / Service
7. Manager-Override Modal
8. Lizenzserver (wenn erste externe Installation)

**Why:** Reihenfolge so weil: ohne Login kein Rechtecheck; ohne Rechtecheck kein Manager-Override sinnvoll testbar.

## Korrektur 2026-07-03 (präzisiert, nach Jackys Rückfrage): Tabellen existieren, aber FAKTISCH KEINE Durchsetzung

Erste Korrektur war zu großzügig formuliert ("Basis-RBAC existiert schon") — Jacky hat zurecht nachgefragt, weil sein Erinnerungsbild (Zugriffssteuerung zu Artikel-bearbeiten usw. kommt erst noch) näher an der Wahrheit war. Genauer nachgeschaut: `Auth::kann()` wird im **gesamten Code nur an exakt einer Stelle** aufgerufen — `shell_top.php`, für einen deaktivierten "Lizenzverwaltung"-Menüpunkt (`href="#"`, Titel "Kommt bald"). Es gibt **keine einzige** Stelle, die prüft, ob ein Benutzer Artikel bearbeiten, Lager buchen, Preise ändern etc. darf. Tabellen `rollen`/`berechtigungen`/`rollen_berechtigungen`/`benutzer_rollen` (Migrationen 004/005) + 3 seed-Rollen existieren als reines DB-Gerüst, ohne jede funktionale Auswirkung. Login/Logout (`login.php`/`logout.php`) funktionieren, sind aber unabhängig von der Berechtigungsfrage (jeder eingeloggte Benutzer kann aktuell alles).

**Fazit:** Die von Jacky beschriebene Vision (Gruppen mit Berechtigungs-Pool, Admin weist zu, Superadmin schaltet neue Admins frei, granulare Rollen wie Kassier/Datenbearbeiter/Praktikant) ist **komplett ungebaut** — nur besprochen. Einzig vorhanden: das DB-Schema als möglicher Ausgangspunkt, kein funktionierendes Feature.
**Weiterhin fehlt:** feingranulare Rollen-Struktur, Manager-Override-Popup, Lizenz-Instanzierung (`max_instanzen`), Admin-UI zum Benutzer-Anlegen/Rollen-Zuweisen (nur `erp/database/create_admin.php` CLI existiert), UND jegliche tatsächliche Rechteprüfung im Code.

## Offener Punkt für die künftige "Neuen Benutzer anlegen"-Seite (Admin-UI)

Aktuell (Stand 2026-07-03) gibt es noch KEINE UI zum Anlegen neuer Benutzer — nur `erp/database/create_admin.php` (CLI, siehe [[project_installationsanleitung]]) und direktes SQL. Der System-User Jarvis (`username='system'`, wird automatisch per Migration 105 angelegt, siehe [[project_installationsanleitung]]) muss dort als **reservierter Username gesperrt** werden, sobald die Admin-Oberfläche für Benutzerverwaltung gebaut wird — sonst könnte jemand versehentlich einen echten Login-Benutzer mit dem Namen anlegen, der eigentlich für automatische/Cron-Log-Einträge reserviert ist.
**How to apply:** Beim Bauen von Schritt 5 der Implementierungsreihenfolge oben (Rollen-Zuweisung im Admin) bzw. einer separaten "Benutzer anlegen"-Seite: Validierung einbauen, die `username = 'system'` ablehnt.
