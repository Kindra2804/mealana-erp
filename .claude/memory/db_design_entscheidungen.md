---
name: db-design-entscheidungen
description: "Alle DB-Schema-Entscheidungen – neue Tabellen, geänderte Tabellen, Prioritäten (zuletzt aktualisiert 2026-06-06)"
metadata:
  node_type: memory
  type: project
  originSessionId: f42de806-6c53-4c08-b255-b4829200b8a1
  modified: 2026-07-20T18:07:48.583Z
---

## UI/UX-Entscheidungen (Session 2026-06-06)

### Artikel-Formular: Kategorie-Zuweisung (A2)
**Modal-Ansatz** (kein Tab, kein Redirect): JavaScript-Overlay öffnet sich über dem Formular.
- Zeigt bestehende Kategorien als Baum mit Checkboxen
- "Neue Kategorie anlegen" direkt im Modal möglich
- "Übernehmen" überträgt Auswahl ins Formular, Modal schließt
- **Why:** Kein Datenverlust, kein Session-Hack nötig, kein Zurück-Problem

### Artikel-Detail: Tab-Navigation (Frontend — für später)
Vorbild JTL-Karteikarten, aber modernes sauberes Design. Geplante Tabs:

| Tab | Inhalt |
|-----|--------|
| Stammdaten | Aktuelle Felder in detail.php |
| Varianten/Kinder | Varianten-Übersicht, VarKombi-Kinder, VarKombi-Generator |
| Lager/Bestände | Lagerstand je Lager, Charge-Status, Bewegungslog |
| Lieferanten | Zugewiesene Lieferanten, EK-Preise, Lieferzeiten |
| Bestellhistorie | Wann zuletzt bestellt, von welchem Lieferanten, zu welchem EK |
| Verkaufshistorie | Wer hat wann gekauft, zu welchem Preis |
| Statistik | Verkaufsanzahl gesamt, Umsatz, Lagerumschlag → Topseller-Ranking |
| Dateien | Downloads, Anleitungen, Bilder (artikel_dateien) |

**Statistik-Datenquellen (wenn gebaut):**
- `lager_bewegungen` mit bewegungstyp='ausgang' → grobe Verkaufszahl jetzt schon möglich
- Kassenbons (Kassenmodul) → exakte Verkaufshistorie
- Topseller-Ranking: COUNT(ausgang) + Umsatz → für Shop-Integration verwendbar

**Why:** Viele Infos pro Artikel, Tab-Navigation verhindert überladene Seite. Statistik-Daten werden beim Aufbau der anderen Module (Kasse, Bestellung) automatisch befüllt wenn Tabellen stimmen.

---

## Arbeitsplatz-Erkennung + Concurrent Sessions (Session 2026-06-06)

### Anforderungen
- Global: max. gleichzeitige Benutzer systemweit (Lizenz-ähnlich)
- Pro Benutzer: max. gleichzeitige Sessions (z.B. Admin darf 3, Kassier nur 1)
- Auto-Logout an anderem Platz wenn Limit überschritten (konfigurierbar)

### Geräteerkennung — Entscheidung: Persistenter Geräte-Token
IP allein unzuverlässig (DHCP, NAT, Mobile wechselt IP). Lösung: **UUID-Token in localStorage/Cookie** — bleibt auf dem Gerät, überlebt Browser-Neustart.

```sql
arbeitsplaetze (
  id              INT PK AUTO_INCREMENT,
  name            VARCHAR(100) NOT NULL,   -- 'Kasse 1', 'Lager-Scanner', 'Büro'
  geraete_token   CHAR(36) NOT NULL UNIQUE, -- UUID, generiert beim ersten Login
  typ             VARCHAR(30),             -- 'kasse','lager','buero','mobil'
  aktiv           TINYINT(1) DEFAULT 1,
  erstellt_am     TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
```

Sessions-Tabelle bekommt zusätzlich:
```sql
ALTER TABLE sessions
  ADD COLUMN arbeitsplatz_id INT NULL FK → arbeitsplaetze.id,
  ADD COLUMN geraete_token   CHAR(36) NULL;   -- Kopie für schnellen Lookup ohne JOIN
```

Systemeinstellungen für Limits:
```sql
-- In system_einstellungen (noch zu erstellen):
-- 'max_gleichzeitige_benutzer'   → INT (systemweit)
-- 'max_sessions_pro_benutzer'    → INT (default, überschreibbar per Benutzer)
-- 'auto_logout_andere_session'   → BOOL

-- Auf benutzer-Tabelle:
ALTER TABLE benutzer ADD COLUMN max_sessions INT NULL;  -- NULL = system default
```

### Erster-Login-Workflow auf neuem Gerät
1. Frontend prüft localStorage: Geräte-Token vorhanden?
2. Nein → Dialog "Diesem Gerät einen Namen geben" (z.B. "Kasse 1")
3. UUID generieren, in localStorage speichern, in `arbeitsplaetze` anlegen
4. Bei jedem Login: Token mitschicken → Session verknüpft mit Arbeitsplatz

### Mobile (Inventur/Umbuchung)
PWA (Progressive Web App) — läuft im Handy-Browser, kein App-Store. EAN-Scan via Kamera-API. Geräte-Token funktioniert auch auf Mobile (localStorage bleibt erhalten).

**Why:** IP-basiertes Logging ist unzuverlässig und kann Datenschutzfragen aufwerfen. UUID-Token ist präzise, gerätespezifisch, und funktioniert auch für mobile Geräte die IP wechseln.

---

## Charge-Pflicht Design (Session 2026-06-05)

**`artikel.charge_pflicht TINYINT(1) DEFAULT 0`** — neues Feld (Migration 008)

### Logik beim Wareneingang
- Charge eingetragen → `charge_status = 'erfasst'` (immer)
- Keine Charge + `charge_pflicht = 1` → `charge_status = 'nachzutragen'`
- Keine Charge + `charge_pflicht = 0` → `charge_status = NULL`
- `'unbekannt'` wird langfristig obsolet (Altdaten bleiben, neue entstehen nicht mehr)

### Flag-Vererbung
- Flag sitzt auf `artikel` (Vater), Kinder erben über JOIN
- Kein Flag auf `artikel_varianten` — wäre Redundanz

### Flag-Wechsel (Retroaktiv)
- Flag 0→1: Altbestand bleibt unverändert (kein Auto-Update). Optionaler Dialog: "Bestehenden Bestand auf 'nachzutragen' setzen?"
- Flag 1→0: Altbestand mit Charges bleibt sichtbar (historische Fakten). Neue Buchungen → NULL.
- Nachtrag-Liste filtert: `charge_status='nachzutragen' AND artikel.charge_pflicht=1` → deaktivierte Artikel fallen automatisch raus

### Nachtrag-Workflow (zwei Wege)
1. **Mikro-Inventur-Liste**: Alle `charge_status='nachzutragen'` + `charge_pflicht=1` → Charge eintragen → Log → Status 'erfasst'
2. **Am Kassenpunkt**: Wenn nur 'nachzutragen'-Bestand verfügbar → Charge vor Verkauf abfragen (Kassemodul)

---

## Artikel-Typen Vereinfachung (Session 2026-06-06 — Nachmittag)

### Entscheidung: Typ 2 gestrichen
Keiner der großen WAWIs (JTL, Shopware, WooCommerce, Sage) kennt "geteilter Lagerstand mit informativen Varianten" als eigenen Typ. Wir übernehmen das nicht.

### Finale Artikel-Konstellationen
| Typ | Beschreibung | vaterartikel_id | hat_eigenen_lagerstand |
|-----|---|---|---|
| 1 | Standardartikel — kein Achsen-Setup | NULL | 1 |
| 3 | Variationsartikel mit eigenem Lagerstand (Achsen/Werte definiert, lagerbestand per Variante) | NULL | 1 |
| 4a | VarKombi-Kind mit eigenem Lagerstand (eigene Artikelnummer, EAN, Preis) | gesetzt | 1 |
| 4b | VarKombi-Kind ohne eigenen Lagerstand (bucht auf Vater-Lagerstand) | gesetzt | 0 |

**Typ 4b Anwendungsfall:** Rollmaßband in Blau/Gelb/Grün — Kunden entscheiden Farbe, wird in Bestellung/Kassenbon notiert, aber Gesamtlagerstand läuft auf dem Vater. Akzeptiert: man weiß wie viele Rollmaßbänder *gesamt* verfügbar sind, nicht wie viele blaue/gelbe/grüne.

### Neue DB-Felder auf `artikel`
```sql
ALTER TABLE artikel
    ADD COLUMN vaterartikel_id INT UNSIGNED NULL AFTER ist_vater,
    ADD COLUMN hat_eigenen_lagerstand TINYINT(1) NOT NULL DEFAULT 1 AFTER vaterartikel_id,
    ADD CONSTRAINT fk_artikel_vater FOREIGN KEY (vaterartikel_id) REFERENCES artikel(id) ON UPDATE CASCADE;
```

**Why:** VarKombi-Kinder sind echte `artikel`-Zeilen (eigene Artikelnummer, EAN, Preise) — nicht mehr nur `artikel_varianten`.

---

## VarKombi-Generator (UX-Konzept — Session 2026-06-06)

Orientiert an JTL "Kindartikel erstellen"-Dialog (Screenshots vorhanden).

### Workflow
1. Vater-Artikel hat Achsen + Werte definiert (z.B. Stärke: 3mm, 3.25mm, 3.5mm)
2. Generator zeigt kartesisches Produkt aller Achsen-Kombinationen als Tabelle
3. Pro Kombination editierbar: Artikelname (mit Template), Artikelnummer (Bausteine), EAN (optional leer), Preis
4. Artikelnummer-Bausteine (drag&drop Reihenfolge): Vater-Artikelnummer + Trennzeichen + Variationswert-Name/-Nr + fortlaufende Zahl → Live-Vorschau
5. Einmalige Frage beim Generator-Start: "Eigener Lagerstand pro Kind?" → wird auf alle erstellten Kinder propagiert als `hat_eigenen_lagerstand`
6. `charge_pflicht` wird automatisch vom Vater auf alle Kinder übernommen (nie vergessen!)
7. Bereits erstellte Kinder: eigene Tabelle darunter, nicht mehr im Auswahl-Grid

### Business Rule (von JTL übernommen)
Ein VarKombi-Vater kann **nicht gleichzeitig eine Stückliste** sein. Gegenseitiger Ausschluss wird beim Speichern validiert.

### Aufpreis pro Variationswert
`varianten_achse_werte` bekommt ein optionales Feld `aufpreis DECIMAL(10,2) NULL` — z.B. Nadelstärke 12mm kostet 2€ mehr. (Von JTL Variationen-Screenshot erkannt.)

---

## EAN-Qualitätslisten (Session 2026-06-06)

`artikel_codes`-Tabelle ist bereits vorhanden (kein extra EAN-Feld nötig, kein UNIQUE-Constraint → App-seitige Duplikat-Erkennung korrekt).

### Fehlende EAN — Kind-Artikel ohne GTIN
```sql
SELECT a.artikelnummer, a.name, v.artikelnummer AS vater_nr
FROM artikel a
JOIN artikel v ON v.id = a.vaterartikel_id
WHERE a.vaterartikel_id IS NOT NULL
  AND a.hat_eigenen_lagerstand = 1
  AND NOT EXISTS (
      SELECT 1 FROM artikel_codes ac
      WHERE ac.artikel_id = a.id AND ac.typ IN ('GTIN13','GTIN8')
  )
```

### Doppelte EAN — systemweit
```sql
SELECT ac.code, ac.typ, COUNT(*) AS anzahl,
       GROUP_CONCAT(a.artikelnummer SEPARATOR ', ') AS artikel
FROM artikel_codes ac
JOIN artikel a ON a.id = ac.artikel_id
WHERE ac.typ IN ('GTIN13','GTIN8')
GROUP BY ac.code, ac.typ
HAVING COUNT(*) > 1
```

### Kasse bei Duplikat-EAN
Scan trifft mehrere Artikel → Dialog mit Auswahl (alle Treffer anzeigen, Benutzer wählt). Verhindert Blockade an der Kasse, Kontrollliste ergänzend.

---

## Seriennummern-System (Konzept — Session 2026-06-06)

Pendant zu Chargen, aber für **Einzelstücke** mit individueller Verfolgbarkeit. Anwendungsfälle: limitierte Editionen, hochwertige Einzelstücke, Maschinen/Geräte.

### Flag auf `artikel`
```sql
ALTER TABLE artikel
    ADD COLUMN seriennummer_pflicht TINYINT(1) NOT NULL DEFAULT 0;
```
Logik analog zu `charge_pflicht` — gesetzt auf Vater, Kind-Artikel erben beim Erstellen.

### Tabelle `seriennummern`
```sql
seriennummern (
  id               INT PK AUTO_INCREMENT,
  artikel_id       INT NULL FK → artikel.id,
  variante_id      INT NULL FK → artikel_varianten.id,
  lager_id         INT NULL FK → lager.id,
  seriennummer     VARCHAR(100) NOT NULL,
  status           VARCHAR(20),  -- 'lager', 'reserviert', 'verkauft', 'defekt', 'verloren'
  wareneingang_ref INT NULL FK → lager_bewegungen.id,
  verkauf_ref      VARCHAR(100) NULL,  -- Bestell-/Bonnummer
  lieferant_id     INT NULL FK → lieferanten.id,
  erstellt_am      TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  geaendert_am     TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  UNIQUE KEY (seriennummer)
)
```

### Workflow
- Wareneingang mit seriennummer_pflicht=1: jede einzelne Einheit bekommt Seriennummer → in `seriennummern` anlegen (status='lager')
- Verkauf: Seriennummer der Warenposition zuordnen (status='verkauft', verkauf_ref gesetzt)
- Vollständige Traceability: welches konkrete Stück → wann eingekauft → welcher Lieferant → wann verkauft

### Details noch offen
Anbindung an Kassen-/Bestellmodul (konkretes Tabellendesign für verkauf_ref) noch zu besprechen wenn Kassenmodul gebaut wird.

**Why:** Wurde in vorheriger Session besprochen aber nicht gespeichert — zweimal durchgefallen, jetzt fest verankert.

---

## Geplante Strukturänderungen (Session 2026-06-06) — NOCH NICHT IMPLEMENTIERT

### KRITISCH: Varianten-System (artikel_varianten) — FINALES DESIGN (2026-06-11)

**Entscheidungen aus JTL-Export-Analyse + Design-Session:**
- Achsen sind **global** (einmal definieren, für alle Artikel wiederverwendbar) → ermöglicht Shop-Filter "alle roten Artikel"
- Werte sind **pro Artikel** (jeder Artikel hat seine eigenen Werte je Achse)
- Darstellungsform sitzt auf der **Achse**, nicht mehr auf `artikel.varianten_darstellung`
- `artikel.varianten_darstellung` wird **entfernt** (Migration)
- Modal für neue Achsen anlegen (wie Kategorien)

**Darstellungsformen (aus JTL-Export, 5 Typen):**
| Wert | Bedeutung |
|---|---|
| `swatches` | Farb-/Bild-Swatches |
| `dropdown` | Dropdown-Liste |
| `radiobutton` | Radio-Buttons |
| `freitext` | Optionale Texteingabe (Kunde tippt frei) |
| `pflichtfreitext` | Pflicht-Texteingabe (z.B. Name auf Schild) |

**Tabellen:**

```sql
-- Global definierte Achsen
varianten_achsen (
  id               INT PK AUTO_INCREMENT,
  name             VARCHAR(100),       -- 'Farbe', 'Nadelstärke', 'Länge'
  code             VARCHAR(50) UNIQUE, -- 'farbe', 'staerke', 'laenge' — für API/Shop
  darstellungsform VARCHAR(30),        -- swatches/dropdown/radiobutton/freitext/pflichtfreitext
  sort_order       INT DEFAULT 0,
  erstellt_am      TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)

-- Pro Artikel: welche Achsen, in welcher Reihenfolge, mit optionaler Bedingung
artikel_achsen (
  id                    INT PK AUTO_INCREMENT,
  artikel_id            INT FK → artikel.id,
  achse_id              INT FK → varianten_achsen.id,
  sort_order            INT DEFAULT 0,
  bedingungs_achse_id   INT NULL FK → varianten_achsen.id,  -- zeige NUR wenn...
  bedingungs_wert_id    INT NULL FK → varianten_achse_werte.id  -- ...diese Achse diesen Wert hat
)

-- Pro Artikel + Achse: die konkreten Werte
varianten_achse_werte (
  id           INT PK AUTO_INCREMENT,
  artikel_id   INT FK → artikel.id,
  achse_id     INT FK → varianten_achsen.id,
  wert         VARCHAR(100),       -- 'Rot', '3.5 mm', 'HAZEL'
  wert_zusatz  VARCHAR(100) NULL,  -- z.B. '#FF0000' bei Farben
  aufpreis     DECIMAL(10,2) DEFAULT 0,
  sort_order   INT DEFAULT 0
)

-- Welche Werte hat eine Kombination (artikel_varianten.id)?
varianten_kombination_werte (
  kombination_id INT FK → artikel_varianten.id,
  wert_id        INT FK → varianten_achse_werte.id,
  PRIMARY KEY (kombination_id, wert_id)
)
```

**Bedingte Achsen (Shop-Anzeige):**
`artikel_achsen.bedingungs_achse_id` + `bedingungs_wert_id` = NULL → Achse immer anzeigen.
Wenn gesetzt: Achse B nur anzeigen wenn Achse A den Wert Y hat. Frontend-Logik, DB speichert nur die Bedingung.

**Konfigurator (Bundles/Add-ons):** Eigenes Modul für später. NICHT Teil des Varianten-Systems.
freitext/pflichtfreitext in Variationen deckt Schilder-Usecase ab (kein Konfigurator nötig dafür).

**Migration:**
1. `varianten_darstellung` aus `artikel` entfernen
2. `farbe_name` + `farbe_hex` aus `artikel_varianten` entfernen
3. Bestehende Daten: Achse "Farbe" (darstellungsform='swatches') global anlegen, Werte aus farbe_name/farbe_hex je Artikel anlegen, Kombinationen verknüpfen

**Why:** Global = Shop-Filter möglich. Bedingte Achsen = Shop-Konfigurator ohne Extra-Modul. Darstellungsform pro Achse = ein Artikel kann Swatches + Dropdown gleichzeitig haben.

---

### artikeltyp: ENUM → Tabelle

**Problem:** ENUM auf 50M-Zeilen → ALTER TABLE teuer. Quelloffen = eigene Typen anlegen können.

```sql
artikel_typen (
  id               INT PK AUTO_INCREMENT,
  code             VARCHAR(50) UNIQUE,   -- 'GARN', 'NADEL', etc.
  name             VARCHAR(100),
  hat_varianten    TINYINT(1) DEFAULT 1,
  hat_lagerstand   TINYINT(1) DEFAULT 1,
  ist_download     TINYINT(1) DEFAULT 0,
  ist_set          TINYINT(1) DEFAULT 0,
  sortierung       INT DEFAULT 0,
  aktiv            TINYINT(1) DEFAULT 1
)
```

`artikel.artikeltyp ENUM` → `artikel.artikeltyp_id INT FK → artikel_typen.id`

---

### varianten_darstellung: ENUM → VARCHAR

**Lösung:** `artikel.varianten_darstellung VARCHAR(50)` — Werteliste wird in App validiert, nicht in DB. Startwerte: 'swatches', 'bilder', 'dropdown'. Erweiterbar ohne Migration.

---

### artikel_dateien (NEU) — Downloads + Anhänge

```sql
artikel_dateien (
  id            INT PK AUTO_INCREMENT,
  artikel_id    INT FK → artikel.id,
  variante_id   INT FK → artikel_varianten.id NULL,
  datei_typ     VARCHAR(50),     -- 'download_produkt', 'anleitung', 'datenblatt', 'bild_hires'
  datei_name    VARCHAR(255),
  datei_pfad    VARCHAR(500),
  mime_type     VARCHAR(100),
  datei_groesse INT UNSIGNED,
  oeffentlich   TINYINT(1) DEFAULT 0,   -- 0=nur nach Kauf, 1=frei zugänglich
  sortierung    INT DEFAULT 0,
  aktiv         TINYINT(1) DEFAULT 1,
  erstellt_am   TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)
```

**Why `datei_typ` als VARCHAR:** Quelloffen — andere Betreiber brauchen ggf. 'sicherheitsdatenblatt', 'zertifikat', etc.

---

### Multi-Shop-Architektur (NEU) — Session 2026-06-06

**Architektur-Begriff:** "Master-Datenbank mit API-Satelliten" — auch bekannt als **Hub-and-Spoke** oder im E-Commerce als **Headless-Commerce-Architektur**. Das ERP ist der Hub (Single Source of Truth), die Shops sind Satelliten die per API synchronisieren.

**Früher besprochen als:** vermutlich "Mandantenfähigkeit" — ist aber nicht ganz dasselbe. Mandanten = mehrere Firmen in einer DB. Hier: eine Firma, mehrere Verkaufskanäle mit eigenen Datenbanken.

```sql
-- Shop-Stammdaten
shops (
  id                      INT PK AUTO_INCREMENT,
  name                    VARCHAR(100),          -- 'MeaLana', 'Sockenwolle-Online'
  code                    VARCHAR(50) UNIQUE,    -- 'mealana', 'sockenwolle'
  farbe_hex               VARCHAR(7),            -- für UI-Farbchips
  url                     VARCHAR(255),          -- öffentliche Shop-URL
  api_url                 VARCHAR(255) NULL,     -- REST-Endpoint des Shops (wenn Push)
  api_key_hash            VARCHAR(255) NULL,     -- gehashter API-Key
  sync_intervall_minuten  INT DEFAULT 60,
  letzter_sync            TIMESTAMP NULL,
  sync_aktiv              TINYINT(1) DEFAULT 1,
  aktiv                   TINYINT(1) DEFAULT 1,
  erstellt_am             TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)

-- Artikel ↔ Shop Zuweisung
artikel_shops (
  artikel_id  INT FK → artikel.id,
  shop_id     INT FK → shops.id,
  PRIMARY KEY (artikel_id, shop_id)
)

-- Kategorie ↔ Shop Zuweisung
kategorie_shops (
  kategorie_id        INT FK → kategorien.id,
  shop_id             INT FK → shops.id,
  externe_kategorie_id VARCHAR(100) NULL,   -- ID im Remote-Shop-System
  PRIMARY KEY (kategorie_id, shop_id)
)

-- Sync-Protokoll (für Diagnose + Konfiguration)
sync_konfiguration (
  id          INT PK AUTO_INCREMENT,
  shop_id     INT FK → shops.id,
  modul       VARCHAR(50),     -- 'artikel', 'kategorien', 'preise', 'bestand'
  aktiv       TINYINT(1) DEFAULT 1,
  intervall_minuten INT DEFAULT 60,
  letzter_sync TIMESTAMP NULL,
  naechster_sync TIMESTAMP NULL
)

sync_log (
  id                  INT PK AUTO_INCREMENT,
  shop_id             INT FK → shops.id,
  modul               VARCHAR(50),
  gestartet_am        TIMESTAMP,
  beendet_am          TIMESTAMP NULL,
  status              VARCHAR(20),   -- 'läuft', 'erfolg', 'fehler'
  geaenderte_datensaetze INT DEFAULT 0,
  fehlermeldung       TEXT NULL
)
```

**UI-Konzept Farbchips:** Im Artikel-Formular und Kategorie-Formular werden je ein farbiger Badge pro Shop angezeigt (Farbe aus `shops.farbe_hex`). Checkbox-Toggle direkt im Chip → kein separates Untermenü nötig.

**Sync-Ablauf (geplant):**
1. ERP-Cron läuft regelmäßig (konfigurierbar per `sync_konfiguration`)
2. Holt alle seit `letzter_sync` geänderten Artikel/Kategorien (via `geaendert_am`)
3. Pushed Änderungen per REST an Shop-API (oder: Shop pollt ERP-API)
4. Loggt Ergebnis in `sync_log`
5. Sync-Status sichtbar im ERP-Backend (welcher Shop, wann, wie viele Änderungen)

**API-Absicherung:** HMAC-Signatur oder Bearer-Token (API-Key), nie Plaintext in DB — nur Hash.

**Shop-Adapter-Strategie (2026-06-12):**
Eigene Shops werden direkt vom ERP versorgt (ERP = Headless-Backend, eigenes Frontend). Für externe Shops: Adapter-Pattern — ein Adapter pro Shop-Typ, alle sprechen intern dasselbe ERP-Datenmodell.

**WooCommerce als primäre externe Plattform:**
- WooCommerce core ist kostenlos und bleibt es (Open Source, WordPress-Plugin, self-hosted). Bezahlte Erweiterungen existieren, werden aber nicht gebraucht.
- WooCommerce REST API v3 ist vollständig und stabil — unterstützt Variable Products (= Vaterartikel), Variations (= Kind-Artikel), Product Attributes (= ERP-Merkmale), Categories
- Merkmale aus ERP → WooCommerce Product Attributes → Shop generiert automatisch Filter-Facetten (Hersteller, Zusammensetzung, Nadelstärke etc.) — KEINE manuelle Filter-Kategorie mehr nötig
- Bilder: Vater-Artikel bekommt Stimmungsbild (mehrere Knäuel), Kind-Artikel bekommt Einzel-Farbbild → WooCommerce zeigt beim Variantenwechsel automatisch das Kind-Bild

**Konsequenz für ERP-Datenmodell:** Merkmale und Varianten-Achsen müssen sauber strukturiert sein (sind sie bereits — globale Achsen + Werte-Tabellen). Keine "virtuellen Filterkategorien" im ERP-Kategorienbaum.

**How to apply:** Beim Bauen des Shop-Moduls und der REST-API diese Entscheidungen als Basis verwenden. WooCommerce-Adapter als erstes externes Ziel implementieren.

**WooCommerce Kategorie-Sync (Entscheidung 2026-06-21):** ✅ implementiert 2026-07-20, siehe [[project_shop_sync]]. **Update 2026-07-20:** Der Hersteller-Ast unter dieser Kategorie-Struktur ist NICHT mehr die geplante Lösung für den Hersteller-Filter im Shop — siehe [[project_hersteller_shop_filter]] (WC-Produktattribut statt Kategorie, weil Hersteller mehrere Produktkategorien bedienen können).
- Beim Sync den **vollen Pfad** in WooCommerce anlegen (WolleundGarne → Hersteller → DROPS via `parent` field)
- Dem Artikel wird nur die **Blatt-Kategorie** zugewiesen (nicht alle Vorfahren)
- WooCommerce-Einstellung "Show products from subcategories" steuert Sichtbarkeit in Elternkategorien
- `kategorie_shops.externe_kategorie_id` speichert die WC-seitige Kategorie-ID pro Shop

**Kanal-Chips an Kategorien (Entscheidung 2026-06-21):** ✅ implementiert 2026-07-20, siehe [[project_shop_sync]]
- Chips werden **berechnet**, nicht manuell gepflegt
- Eine Kategorie gilt als "in Shop X aktiv" wenn mindestens ein Artikel in ihr (oder rekursiv in Kindkategorien) in Shop X aktiv ist
- Leere Elternkategorien erben Chips von Kindkategorien (rekursiv hochgeerbt)
- Kein manuelles Pflegen nötig → kein Pflegeaufwand

**Multi-Shop Kategorienbaum (Entscheidung 2026-06-21):**
- **Ein gemeinsamer Kategoriebaum** für alle Shops (nicht getrennte Wurzeln wie JTL)
- Sichtbarkeit pro Shop via bestehende `kategorie_shops`-Tabelle
- Begründung: Bio-wolle.at + MeaLana teilen Großteil des Sortiments → getrennte Bäume wären Doppelpflege

---

## Lizenzierung & Deployment-Modell (beschlossen 2026-06-12)

**Modell: Self-hosted mit Online-Lizenzvalidierung**

Kunden installieren das ERP auf ihrem eigenen Server/Webspace (wie WordPress/WooCommerce).
Karl liefert Updates als Paket → Kunde spielt selbst ein. Keine gemeinsame Infrastruktur, keine mandant_id-Komplexität.

**Lizenzvalidierung: Online** (Kunden betreiben ohnehin Webshops → Internetverbindung vorhanden)
- ERP-Installation pingt Karls Lizenzserver bei Aktivierung + periodisch
- Server antwortet mit erlaubten Modulen + Gültigkeitsdatum
- Kein Hardware-Dongle (zu starr, zu teuer, keine Produktänderungen ohne Tausch)

**Annahme:** Alle Kunden haben Internetzugang (Webshop-Betrieb setzt das voraus)

**DB-Aufwand: minimal — kein mandant_id nötig**
```sql
-- Neue Tabelle je Installation:
modul_lizenzen (
  modul_code       VARCHAR(50) PRIMARY KEY,  -- 'artikel','lager','kassa','buchhaltung'...
  aktiv            TINYINT(1) DEFAULT 0,
  gueltig_bis      DATE NULL,
  lizenzschluessel VARCHAR(255) NULL,
  letzter_check    TIMESTAMP NULL
)

-- In system_einstellungen (bereits geplant):
-- logo_pfad, favicon_pfad, farbe_primaer, farbe_akzent, firma_name
```

**Super-Admin:** Erster User bei Neuinstallation (Setup-Wizard), hat Zugriff auf Lizenz-Aktivierung + Modul-Verwaltung + Branding.

**Updates:** Karl liefert Versions-Pakete (ZIP mit Migrations-Script), Kunde führt Update-Wizard aus. Standard-Modell wie alle gängigen Self-hosted-Produkte.

**Implementierungsaufwand:** ~1 Woche (Lizenz-Tabelle + PHP-Middleware + Super-Admin-UI + Branding-Einstellungen + Setup-Wizard)

**How to apply:** Vor dem ersten produktiven Release einbauen. DB-Schema bleibt wie geplant — kein Umbau nötig. Lizenzserver (Karl's eigener Endpoint) wird parallel gebaut wenn erste externe Installation ansteht.

---

## Neue Features (Feedback Karls Frau, 2026-06-12)

### 1. Freitext-Artikel (Pflicht — vor Kassa/Auftrag)

Kein neuer Artikeltyp — `position_typ` auf Positions-Tabellen:

```sql
ALTER TABLE auftrag_positionen
  ADD COLUMN position_typ VARCHAR(20) NOT NULL DEFAULT 'artikel',
  -- Werte: 'artikel' | 'freitext' | 'rabatt' | 'versand'
  ADD COLUMN freitext_bezeichnung VARCHAR(500) NULL,
  ADD COLUMN freitext_einzelpreis DECIMAL(10,4) NULL,
  ADD COLUMN freitext_steuerklasse_id INT NULL FK → steuerklassen.id,
  ADD COLUMN freitext_versandklasse_id INT NULL FK → versandklassen.id;
-- Gleiche Änderung auf kassenbon_positionen
```

Wenn `position_typ = 'freitext'`: `artikel_id = NULL`, Freitext-Felder werden verwendet.

**UI-Trigger:**
- Kassa: Nur Preis + Enter → automatisch Freitext-Dialog (wie LS-POS "Freipreis")
- Auftrag: Button "+ Freitext-Position"

### 2. Bildverarbeitung beim Upload (vor Shop-Anbindung)

Kein DB-Redesign. Backend-Pipeline beim Upload:
- PHP Imagick/GD: Dateiformat + Größe prüfen
- Automatisch komprimieren + zu JPEG/WebP konvertieren (kein User-Dialog, läuft still)
- 3 Größen automatisch generieren: Thumbnail 150px, Medium 600px, Large 1200px
- `artikel_bilder.dateigroesse_kb INT` als Info-Feld (optional)
- Shops holen sich die passende Größe → kein 30MB-Bild mehr im Sync

Konfigurierbar über Tabelle `bild_groessen (id, name, breite, hoehe, qualitaet)` — wird erst gebaut wenn nötig.

### 3. "Anzeigen auch bei Nullbestand" + Kunden-Merkliste (vor Shop-Anbindung)

```sql
-- Auf artikel:
ALTER TABLE artikel
  ADD COLUMN bei_nullbestand_anzeigen TINYINT(1) NOT NULL DEFAULT 0;
  -- 1 = im Shop "Ausverkauft"-Badge zeigen, Jarvis darf NICHT deaktivieren

-- Neue Tabelle:
kunden_benachrichtigungen (
  id              INT PK AUTO_INCREMENT,
  kunde_id        INT FK → kunden.id,
  artikel_id      INT FK → artikel.id,
  variante_id     INT FK → artikel_varianten.id NULL,
  erstellt_am     TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  benachrichtigt_am TIMESTAMP NULL,
  UNIQUE KEY (kunde_id, artikel_id, variante_id)
)
```

**Auswirkungen auf andere Stellen:**
- **Jarvis**: Prüfbedingung + `AND bei_nullbestand_anzeigen = 0`
- **Shop-Sync**: Flag → Shop zeigt "Ausverkauft" + "Benachrichtigen"-Button
- **Dashboard**: neue KPI "X Artikel auf Merklisten" (wenn > 0)
- **Artikelliste**: neues Flag [🔔] ähnlich bestehenden Status-Flags
- **E-Mail-Trigger**: Bestand 0 → >0 + wartende Kunden → automatische Benachrichtigung

### Achsen = automatisch Merkmale (Architekturentscheidung 2026-06-12)

**Kernprinzip:** Varianten-Achsen sind eine Teilmenge der Merkmale — keine Doppelpflege.

- Eine globale Achse ("Nadelstärke") wird **einmal** angelegt
- Die Werte am Vater-Artikel (3.5mm, 4mm, 4.5mm) generieren: (a) Kinder-Artikel via VarKombi-Generator UND (b) automatisch Shop-Filter-Facetten
- Beim Shop-Sync: Achse → WooCommerce "attribute used for variations"; Achsenwerte → Variation-Attribute-Werte + automatischer Seitenfilter

**Unterschied Achse vs. reines Merkmal:**
| Typ | Beispiel | Kinder? | Shop-Filter? |
|-----|----------|---------|-------------|
| Achse | Nadelstärke, Farbe | ✓ eigene Artikel | ✓ automatisch |
| Merkmal | Fasergehalt, Lauflänge, Pflegeanweisung | ✗ | ✓ (separates Feld) |

**Was wegfällt:** Keine "Filter-Kategorien" mehr (JTL-Hack: Kategorie "Nadelstärke 3.5mm" mit manueller Artikel-Zuweisung). Wert einmal eingeben → fertig.

**How to apply:** Beim Shop-Sync-Adapter: `varianten_achsen` + `varianten_achse_werte` → WooCommerce variation attributes. `merkmal_gruppen` + `artikel_merkmale` → WooCommerce non-variation attributes. Beide erscheinen als Shop-Filter-Facetten.

### Kritische FK-Abhängigkeit: varianten_achse_werte.id (2026-06-16)

`varianten_kombination_werte.wert_id` referenziert `varianten_achse_werte.id`. Daher:
- **NIE** `deleteWerteByArtikelId()` aufrufen wenn Kind-Artikel existieren — bricht alle FK-Referenzen
- `achsen_zuweisen_ajax.php` verwendet Smart-Update: `findWertIdsInUse()` prüft ob eine wert_id in `varianten_kombination_werte` vorkommt → wenn ja: nur sort_order aktualisieren, nie löschen
- `VariantenRepository`: `findWertIdsInUse(int $artikelId)`, `deleteWert(int $id)`, `updateWertSortOrder(int $id, int $sortOrder)` neu seit 2026-06-16
- UI: Gesperrte Werte zeigen 🔒 statt ✕ im Achsen-Modal; Achse mit gesperrten Werten kann nicht abgewählt werden

---

## Fehlende Module (Benchmark große WAWIs) — Session 2026-06-06

### Priorität 1: Wird bald schmerzen
- **Lieferanten-Staffelpreise:** `artikel_lieferanten_preise (artikel_lieferant_id, ab_menge, netto_ek)` — ein EK-Preis reicht nicht
- **Bestellwesen/Bestellmodul:** Lieferantenbestellungen komplett: wann bestellt, Lieferant, Artikel+Mengen, EK-Preis, Lieferstatus (offen/teilgeliefert/komplett). Wareneingang referenziert dann konkrete Bestellung.
- **Seriennummern:** Neben Chargen für Einzelstücke (limitierte Editionen, Maschinen)

### Bestellwesen DB-Kern (Skizze)
```sql
bestellungen (
  id, lieferant_id FK, bestellnummer VARCHAR, bestellt_am DATE,
  erwartete_lieferung DATE NULL, status VARCHAR(20),  -- offen/teilgeliefert/abgeschlossen/storniert
  notiz TEXT, benutzer_id FK, erstellt_am TIMESTAMP
)
bestellpositionen (
  id, bestellung_id FK, artikel_id FK, variante_id FK NULL,
  menge_bestellt DECIMAL, menge_geliefert DECIMAL DEFAULT 0,
  netto_ek DECIMAL, waehrung CHAR(3)
)
```
Wareneingang bekommt `bestellposition_id FK NULL` → optionale Verknüpfung mit konkreter Bestellung.

### Priorität 2: Beim Kassenmodul
- **Versandklassen:** `versandklassen (id, name, gewicht_bis, preis)`
- **Zahlungsbedingungen:** `zahlungsbedingungen (id, name, tage_netto, skonto_prozent, skonto_tage)` für B2B
- **Kunden-Stammdaten:** Adresse, UID-Nummer, Steuerrelevanz, Newsletter-Opt-in

### Priorität 3: Vollständige WAWI
- **Inventur-Modul:** Zähllisten, Soll/Ist-Vergleich, Abschluss (Bewegungstyp 'inventur' ist schon im ENUM)
- **Retouren/Rücksendungen:** `retourgruende`, `retouren`-Tabelle, Rückbuchung
- **Preisregeln/Aktionen:** `preis_aktionen (artikel_id, kundengruppe_id, rabatt_prozent, von, bis)`
- **Wechselkurse:** Wenn Lieferant in CHF/USD fakturiert
- **Artikelzustand:** Neu / B-Ware / Sonderposten

---

## Neue Tabellen (nach Priorität) — Original Session 2026-06-04

### Priorität 1: Sofort (nächste Session)

**`kategorien`** — selbstreferenzierende Baumstruktur
```sql
kategorien (
    id          INT PK AUTO_INCREMENT,
    name        VARCHAR(255) NOT NULL,
    parent_id   INT NULL FK → kategorien.id,  -- NULL = Wurzel
    sortierung  INT DEFAULT 0,
    aktiv       TINYINT(1) DEFAULT 1,
    externe_id  VARCHAR(100) NULL,   -- JTL Kategorie-ID
    datenquelle VARCHAR(50) NULL     -- 'jtl', 'manual'
)
```
Max. genutzte Tiefe in JTL-Daten: 4 Ebenen. Schema unterstützt beliebige Tiefe.

**`artikel_kategorien`** — Pivot (Artikel kann in mehreren Kategorien sein)
```sql
artikel_kategorien (
    artikel_id    INT FK → artikel.id,
    kategorie_id  INT FK → kategorien.id,
    PRIMARY KEY (artikel_id, kategorie_id)
)
```
Nur Vaterartikel bekommen Kategorien zugewiesen. Kinder erben (Anwendungslogik, kein DB-Constraint).

**`artikel_externe_referenzen`** — Option B (flexible Mehrquellen-Referenz)
```sql
artikel_externe_referenzen (
    id            INT PK AUTO_INCREMENT,
    artikel_id    INT FK → artikel.id,
    datenquelle   VARCHAR(50) NOT NULL,   -- 'jtl', 'lieferant_xyz'
    externe_id    VARCHAR(100) NOT NULL,  -- JTL InterneArtikelID z.B. "36642"
    UNIQUE(datenquelle, externe_id)
)
```
Why Option B: Ein Artikel kann in JTL UND in Lieferantenlisten existieren — jede Quelle hat eigene ID.

### Priorität 2: Bald (beim Artikel-Form-Upgrade)

**`einheiten`** — ersetzt ENUM auf artikel.einheit + artikel.inhalt_einheit
```sql
einheiten (
    id      INT PK AUTO_INCREMENT,
    kuerzel VARCHAR(20),    -- 'g', 'kg', 'm', 'Stk', 'Knäuel'
    name    VARCHAR(100),   -- 'Gramm', 'Kilogramm', etc.
    typ     VARCHAR(20)     -- 'gewicht','laenge','stueck','volumen' — kein ENUM
)
```
Betroffene Spalten auf `artikel`: einheit, inhalt_einheit → werden zu FK auf einheiten.id.

**`artikeltyp_merkmal_vorlagen`** — Vorauswahl von Merkmalgruppen beim Anlegen
```sql
artikeltyp_merkmal_vorlagen (
    artikeltyp_id       INT FK → artikel_typen.id,
    merkmal_gruppen_id  INT FK → merkmal_gruppen.id,
    pflicht             TINYINT(1) DEFAULT 0,
    sortierung          INT DEFAULT 0,
    PRIMARY KEY (artikeltyp_id, merkmal_gruppen_id)
)
```

### Priorität 3: Shop-Phase

**`pflegeanweisungen`** — standardisierte ISO-3758-Symbole
```sql
pflegeanweisungen (
    id        INT PK AUTO_INCREMENT,
    code      VARCHAR(50),
    name      VARCHAR(100),
    icon      VARCHAR(100),
    kategorie VARCHAR(30)   -- 'waschen','bleichen','trockner','buegeln','reinigung'
)

artikel_pflegeanweisungen (
    artikel_id           INT FK → artikel.id,
    pflegeanweisung_id   INT FK → pflegeanweisungen.id,
    PRIMARY KEY (artikel_id, pflegeanweisung_id)
)
```

**`artikel_bilder`** — ersetzt bild_url auf artikel_varianten
```sql
artikel_bilder (
    id            INT PK AUTO_INCREMENT,
    artikel_id    INT FK NULL,
    variante_id   INT FK NULL,
    bild_url      VARCHAR(500),
    alt_text      VARCHAR(255),
    ist_hauptbild TINYINT(1) DEFAULT 0,
    ist_swatch    TINYINT(1) DEFAULT 0,
    sortierung    INT DEFAULT 0
)
```

**`artikel_crossselling`**
```sql
artikel_crossselling (
    artikel_id     INT FK → artikel.id,
    empfehlung_id  INT FK → artikel.id,
    typ            VARCHAR(20),   -- 'crosssell','upsell','zubehoer'
    sortierung     INT DEFAULT 0,
    PRIMARY KEY (artikel_id, empfehlung_id)
)
```

### Priorität 4: Bei Bedarf

**`stueckliste`** — für Artikeltyp SET
```sql
stueckliste (
    id              INT PK AUTO_INCREMENT,
    set_artikel_id  INT FK → artikel.id,
    komponente_id   INT FK → artikel.id,
    menge           DECIMAL(10,2) NOT NULL,
    einheit_id      INT FK → einheiten.id
)
```

---

## Unverändert (bewusste Entscheidung — Stand 2026-06-04, überholt durch Session 2026-06-06)

~~`artikel.varianten_darstellung` ENUM → bleibt vorerst~~ → wird VARCHAR(50)
~~`artikel.artikeltyp` ENUM → bleibt~~ → wird FK auf artikel_typen

---

## JTL-Export-Mapping (für Import-Modul)

Aus den Exporten in mealana/import/:
- `Interner Schlüssel` / `InterneArtikelID` → externe_id mit datenquelle='jtl'
- `IstVaterartikel` = 1 → artikel, = 0 → artikel_varianten (KIND)
- `Vaterartikel_InterneArtikelID` → verknüpft Kind mit Vater
- `KategorieIDs` (pipe-getrennt) → artikel_kategorien
- Kategorie-Ebene 1-10 → rekursiv in kategorien-Baum auflösen (max 4 Ebenen in Daten)
- `Merkmalgruppe` + `Merkmal` + `Merkmalwert` → merkmal_gruppen + merkmale + artikel_merkmale

**Why:** Import-API soll JTL-WAWI und Lieferantenlisten als Quellen unterstützen.
**How to apply:** Beim Bauen des Import-Moduls diese Mapping-Tabelle als Referenz verwenden.
