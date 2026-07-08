# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Projekt

Ein modulares Warenwirtschaftssystem für MeaLana, eine Wollboutique in Österreich.

**Entwickler**: Indranet (Jacky)  
**GitHub**: https://github.com/Kindra2804/mealana-erp

### Lernziel
Jacky ist Anfänger (frisch aus zertifiziertem Kurs).  
Claude ist Trainer – Konzepte erklären, selbst schreiben lassen, Fehler mit Erklärungen korrigieren. **Nicht einfach Code liefern!**

## Stack

- **PHP** 8+, OOP, kein Framework (bewusst!)
- **MariaDB** (XAMPP verwendet MariaDB, nicht MySQL 8 — relevant für CHECK CONSTRAINT Syntax: `DROP CONSTRAINT name` statt `DROP CHECK name`)
- **Vanilla JavaScript** (kein Framework)
- **XAMPP** on Windows (D:\ERP\mealana\ root)
- **VSC** + Git + PowerShell
- **Symlink**: C:\xampp\htdocs\mealana → D:\ERP\mealana\erp\public

## Setup & Development Commands

### Initial Setup
```powershell
# 1. Clone repo
git clone https://github.com/Kindra2804/mealana-erp D:\ERP\mealana

# 2. Copy database config
Copy-Item D:\ERP\mealana\database.example.php -Destination D:\ERP\mealana\erp\config\database.php
# Edit database.php with local credentials

# 3. Create symlink (run as Admin)
New-Item -ItemType SymbolicLink -Path "C:\xampp\htdocs\mealana" -Target "D:\ERP\mealana\erp\public"

# 4. Start XAMPP MySQL + Apache
# Access at: http://localhost/mealana/artikel/liste.php
```

### Database

```powershell
# Create database from SQL schema (in phpMyAdmin or command line)
# File: erp/database/mealana_erp.sql

# Seeding: Hersteller, Steuerklassen, Kundengruppen, Lager (in migrations/)
```

### View & Test

```powershell
# No build step needed!
# Direct file access: C:\xampp\htdocs\mealana
# Browser: http://localhost/mealana/artikel/liste.php
```

## Architecture Overview

### Layered Pattern

```
HTTP Request
    ↓
View (public/*.php)
    ↓
Controller (src/modules/*/Controller.php)
    ↓
Service (src/modules/*/Service.php) ← Business Logic, Validation
    ↓
Repository (src/modules/*/Repository.php) ← Pure DB Access
    ↓
Database (src/core/Database.php) ← PDO Singleton
    ↓
MySQL
```

**Golden Rule**: Each layer only knows the layer directly below it. Views never touch Repository.

### File Organization

```
D:\ERP\mealana\
├── erp/
│   ├── public/              ← Apache DocumentRoot (öffentlich)
│   │   ├── artikel/
│   │   │   ├── liste.php             ← GET /artikel/liste.php
│   │   │   ├── neu.php               ← Form to create
│   │   │   ├── speichern.php         ← POST handler
│   │   │   ├── bearbeiten.php        ← Edit form
│   │   │   ├── aktualisieren.php     ← PATCH handler
│   │   │   ├── detail.php            ← Single article
│   │   │   ├── delete.php            ← Soft delete
│   │   │   ├── variante_neu.php      ← Create variant
│   │   │   ├── variante_speichern.php
│   │   │   ├── variante_bearbeiten.php
│   │   │   └── variante_aktualisieren.php
│   │   ├── lager/
│   │   │   ├── wareneingang.php           ← Goods receipt form
│   │   │   ├── wareneingang_speichern.php ← POST handler
│   │   │   └── variante_suche.php         ← JSON API (barcode scan)
│   │   └── includes/
│   │       └── nav.php (main navigation)
│   │
│   ├── src/
│   │   ├── core/
│   │   │   └── Database.php     ← Singleton PDO instance
│   │   │
│   │   └── modules/
│   │       ├── artikel/
│   │       │   ├── ArtikelRepository.php
│   │       │   ├── ArtikelService.php
│   │       │   └── ArtikelController.php
│   │       ├── lager/
│   │       │   ├── LagerRepository.php
│   │       │   ├── LagerService.php
│   │       │   └── LagerController.php
│   │       ├── lieferanten/
│   │       │   ├── LieferantenRepository.php
│   │       │   └── LieferantenController.php
│   │       └── merkmale/
│   │           ├── MerkmalRepository.php
│   │           └── MerkmalController.php
│   │
│   ├── database/
│   │   ├── schema_current.sql   ← Aktueller Dump (33 Tabellen, Stand 2026-06-11)
│   │   └── migrations/
│   │       ├── 001_hersteller.sql
│   │       ├── 002_steuerklassen.sql
│   │       └── ...
│   │
│   └── config/
│       └── database.php     ← in .gitignore! (never commit)
│
├── docs/
│   ├── anforderungen.md     ← Business requirements
│   └── kontext.md           ← Project context & status
│
├── shop/                    ← Future online shop module
├── .gitignore
├── README.md
├── database.example.php
└── CLAUDE.md               ← This file
```

## Data Model: 33 Tabellen (Stand 2026-06-11)

### Core Domain
```sql
hersteller          → Manufacturers (supplier records)
steuerklassen       → Tax classes (20% normal, 10% reduced for AT)
artikel_typen       → Artikeltypen (code: GARN/NADEL/METERWARE/DOWNLOAD/SET/STANDARD)
                      - hat_varianten, hat_lagerstand, ist_download, ist_set flags
                      - Erweiterbar ohne Schema-Änderung (kein ENUM mehr)
```

### Articles & Variants (Vater/Kind Pattern)
```sql
artikel             → Master article record
                      - artikeltyp_id FK → artikel_typen (kein ENUM mehr)
                      - ist_vater TINYINT(1): Vater-Artikel (hat Achsen/Kinder)
                      - vaterartikel_id INT NULL FK → artikel.id: Kind-Artikel zeigen auf Vater
                      - hat_eigenen_lagerstand TINYINT(1) DEFAULT 1: 0 = bucht auf Vater-Lagerstand
                      - ueberverkauf_erlaubt TINYINT(1): Verkauf auch bei Bestand ≤ 0 (Shop + Kasse)
                      - charge_pflicht TINYINT(1): Chargennummer beim Wareneingang Pflicht
                      - ist_auslaufartikel TINYINT(1): Auslaufend — auto-deaktiviert bei Bestand=0, auto-reaktiviert bei Wareneingang
                      - seriennummer_pflicht TINYINT(1): Seriennummer pro Stück Pflicht [GEPLANT]

-- Artikel-Konstellationen:
-- Typ 1: Standard         — kein vaterartikel_id, keine Achsen
-- Typ 3: Variationsartikel — Achsen/Werte definiert, lagerbestand per Variante (Kind-Artikel)
-- Typ 4a: VarKombi-Kind  — vaterartikel_id gesetzt, hat_eigenen_lagerstand=1
-- Typ 4b: VarKombi-Kind  — vaterartikel_id gesetzt, hat_eigenen_lagerstand=0 (Vater-Pool)

artikel_codes       → GTIN/ISBN/internal codes per article
                      - Kein UNIQUE-Constraint: Duplikat-Erkennung App-seitig
                      - Qualitätslisten: fehlende EAN bei Kind-Artikeln, doppelte EAN systemweit

-- Varianten-System (Achsen/Werte — Migrations 022–027, fertig):
varianten_achsen    → Globale Achsen (Farbe, Stärke, Länge) — darstellungsform VARCHAR(30)
varianten_achse_werte → Werte je Artikel+Achse (Rot, 3mm) — aufpreis, wert_zusatz (z.B. Hex)
artikel_achsen      → Zuweisungen Achse→Artikel, inkl. bedingte Anzeige (bedingungs_achse_id/wert_id)
varianten_kombination_werte → Verknüpft Kind-Artikel mit ihren Achswerten (composite PK)

-- [GEPLANT] seriennummern → Einzelstück-Tracking (analog Chargen)
--   status: lager|reserviert|verkauft|defekt|verloren
--   Anbindung an Kasse/Bestellmodul beim Verkauf
```

### Pricing
```sql
kundengruppen       → Customer segments: Endkunde, Händler, Vertriebspartner
artikel_preise      → Price matrix per (article, customer_group, date_range)
```

### Warehouse & Inventory
```sql
lager               → Warehouses: Ladengeschäft, Messe, Extern, Lager

lagerbestand        → Stock levels per (artikel, warehouse)
                      - UNIQUE(artikel_id, lager_id, charge) — charge kann NULL sein
                      - charge tracking for yarn (Farbkonsistenz critical!)
                      - charge_status: erfasst|unbekannt|nachzutragen

reservierungen      → Überverkauf-Reservierungen (wenn ueberverkauf_erlaubt=1 und Bestand ≤ 0)
                      - kanal VARCHAR(30): 'shop'|'kasse'|'manuell'
                      - referenz_tabelle + referenz_id: polymorphic link (z.B. bestellungen)
                      - status VARCHAR(20): 'offen'|'erledigt'|'storniert'

lager_bewegungen    → Immutable audit log of all movements
                      - Movement type: eingang|ausgang|korrektur|inventur
                      - Always track: bestand_vorher, bestand_nachher
                      - referenz (invoice, order number) for traceability
```

### Attributes & Classification
```sql
merkmal_gruppen     → Attribute groups (e.g. "Fasergehalt", "Maschenbreite")
merkmale            → Individual attributes with data type & unit
artikel_merkmale    → Values assigned to articles
```

### Suppliers
```sql
laender             → ISO-3166 Länder-Referenz (iso_code PK, name_de, ist_eu_mitglied)
lieferanten         → Supplier master data
                      - firma/firmenzusatz (offizieller Name, name bleibt Such-/Kurzbezeichnung)
                      - land FK → laender.iso_code, ustid, steuerregel ENUM(inland/eu_igl/drittland_einfuhr/reverse_charge)
                      - standard_lieferkosten (Vorbelegung Bestellung), iban/bic/bank_name/kontoinhaber
artikel_lieferanten → Supplier-specific SKU, cost (netto_ek), MOQ, lead times
lieferanten_vertreter → Contact persons per supplier (+ anrede)
```

### RKSV / BFR (Kassen-Signatur, Migrations 097–104)
```sql
kassen              → + bfr_url, bfr_umsatzzaehler, bfr_aktiv_seit (Stichtag: Belege davor nie signieren)
kassen_bons          → + steuer_a..e, bfr_status(signiert/ausstehend/fehler), bfr_fehlergrund, signiert_am, nachsignierungs_lauf_id
bfr_nachsignierungs_laeufe → Sammelbeleg-Protokoll: wann wie viele Belege nachsigniert wurden
bfr_nullbelege       → Monats-/manuelle Nullbelege, eigener Belegnummernkreis
bfr_kassen_registrierungen → Protokoll/Backup der FinanzOnline-Meldung (nicht die Quelle der Wahrheit — das BFR-Admin-Tool macht die echte Meldung)
```

### Auth & Permissions (RBAC)
```sql
benutzer            → User accounts
                      - username (UNIQUE), passwort (bcrypt hash), formularname (display name)
                      - aktiv = 0 for soft-disable (never hard delete)
                      - System-User "Jarvis" (id=2, username='system', passwort='!', aktiv=0) für automatische Log-Einträge
                      - max_sessions INT NULL [GEPLANT]: NULL = system default, sonst eigenes Limit

rollen              → Roles: superadmin, admin, mitarbeiter (+ future: kassier, etc.)
berechtigungen      → Permissions in modul.aktion format (e.g. artikel.anlegen, api.zugriff)
                      - 47 permissions across 12 modules defined
rollen_berechtigungen → Pivot: which role has which permission (composite PK)
benutzer_rollen     → Pivot: which user has which role (composite PK, user can have multiple)
```

### Session & Audit
```sql
sessions            → Active login sessions per user
                      - id = PHP session ID (VARCHAR 128, PK)
                      - ip_adresse, user_agent for device tracking
                      - arbeitsplatz_id FK → arbeitsplaetze [GEPLANT]
                      - geraete_token CHAR(36) [GEPLANT]: Kopie für schnellen Lookup
                      - letzte_aktivitaet: auto-updated on activity

-- [GEPLANT] arbeitsplaetze → Geräte-/Platz-Erkennung via UUID-Token (localStorage)
--   name: 'Kasse 1', 'Lager-Scanner', 'Büro' — beim ersten Login vergeben
--   geraete_token: UUID, persistent im Browser-localStorage, NICHT IP-basiert
--   typ: 'kasse','lager','buero','mobil'
--   Grund: IP ändert sich (DHCP/Mobile) → UUID-Token ist gerätegebunden und zuverlässig

-- [GEPLANT] system_einstellungen → Key-Value für globale Limits
--   'max_gleichzeitige_benutzer': systemweites Limit
--   'max_sessions_pro_benutzer': default, überschreibbar per benutzer.max_sessions
--   'auto_logout_andere_session': Bool — älteste Session killen wenn Limit überschritten

aktivitaeten        → Immutable activity log (Logbuch)
                      - benutzer_id FK RESTRICT (log survives user deactivation)
                      - aktion: modul.aktion format (e.g. 'artikel.anlegen')
                      - referenz_tabelle + referenz_id: polymorphic link to any record
                      - details JSON: old/new values, context
```

### Permission Matrix (Roles → Modules)
| Modul | superadmin | admin | mitarbeiter |
|---|---|---|---|
| artikel/varianten | voll | voll | nur anzeigen |
| lager/wareneingang/bestand | voll | voll | buchen + korrigieren |
| inventur | voll | voll | nur anzeigen |
| lieferanten | voll | voll | nur anzeigen |
| benutzer | voll | anlegen+bearbeiten | — |
| api.zugriff | ✓ | — | — |
| shopabgleich | ✓ | — | — |
| berichte | voll | voll | anzeigen+drucken |
| packplatz/kasse | ✓ | ✓ | ✓ |

## Code Patterns & Conventions

### Naming

```php
// Classes: PascalCase
class ArtikelService { }
class LagerRepository { }

// Methods: camelCase
public function findAll(): array { }
public function upsertBestand(array $data): bool { }

// Variables: camelCase
$artikelId = 5;
$bruttoVk = 19.99;

// DB columns: snake_case
artikel_id, farbe_name, brutto_vk

// Foreign key constraints: fk_{table_abbrev}_{column}
fk_artvar_artikel_id
fk_artlief_artikel_id
```

### Request Flow: Example – Create Article

#### 1. View (Form)
```
erp/public/artikel/neu.php
- Session['fehler'], Session['formdata'] for form repopulation on validation error
- old() helper to restore values in input fields
- selected() helper for dropdowns
- Inline JS for conditional fields (Grundpreis only for GARN/METERWARE)
- JS for brutto/netto price calculation based on selected tax class
```

#### 2. Handler (POST endpoint)
```php
// erp/public/artikel/speichern.php
if ($_SERVER['REQUEST_METHOD'] !== 'POST') redirect;

// Filter $_POST to allowed fields only
$artikelData = array_intersect_key($_POST, array_flip([...]));

// Convert empty strings to NULL before service
foreach ($artikelData as &$val) if ($val === '') $val = null;

$service = new ArtikelService();
$result = $service->save($artikelData);

if ($result['erfolg']) {
    $_SESSION['erfolg'] = 'Artikel gespeichert!';
    redirect to detail.php?id={$result['id']};
} else {
    $_SESSION['fehler'] = $result['fehler'];
    $_SESSION['formdata'] = $artikelData;
    redirect back to neu.php;
}
```

#### 3. Service (Validation + Business Logic)
```php
// src/modules/artikel/ArtikelService.php
public function save(array $data): array
{
    // 1. Validate
    $errors = $this->validiere($data);
    if (!empty($errors)) {
        return ['erfolg' => false, 'fehler' => $errors];
    }

    // 2. Extract prices (they're separate table)
    $bruttoVk = $data['brutto_vk'] ?? null;
    $nettoVk  = $data['netto_vk'] ?? null;
    unset($data['brutto_vk'], $data['netto_vk']);

    // 3. Delegate to repository
    $id = $this->repo->insert($data);

    // 4. Handle related data (prices, variants)
    if ($bruttoVk && $nettoVk) {
        $this->repo->insertPreis($id, (float)$bruttoVk, (float)$nettoVk);
    }

    return ['erfolg' => true, 'id' => $id];
}

private function validiere(array $data): array
{
    $errors = [];

    if (empty($data['artikelnummer'])) {
        $errors[] = 'Artikelnummer ist Pflichtfeld';
    } else {
        // Check uniqueness (exclude self on update)
        if ($this->repo->findByArtikelnummer($data['artikelnummer'], $data['id'] ?? null)) {
            $errors[] = 'Artikelnummer existiert bereits!';
        }
    }

    if (empty($data['name'])) {
        $errors[] = 'Name ist Pflichtfeld';
    }

    return $errors;
}
```

#### 4. Repository (Pure DB Access)
```php
// src/modules/artikel/ArtikelRepository.php
public function insert(array $data): int
{
    $stmt = $this->db->prepare("INSERT INTO artikel (artikelnummer, name, ...) VALUES (...)");
    $stmt->execute($data);
    return (int)$this->db->lastInsertId();
}

public function findByArtikelnummer(string $num, ?int $excludeId = null): array|false
{
    // For update, exclude self: ... AND id != :exclude_id
    // Always use prepared statements with named parameters
}
```

#### 5. Controller (Optional – currently skipped in views)
```
Thin wrapper, rarely used in this project currently.
Views call Service/Repository directly for simplicity.
```

### Form Repopulation Pattern

When validation fails, **never lose user input**:

```php
// View: neu.php
$fehler = $_SESSION['fehler'] ?? [];           // Error messages
$formdata = $_SESSION['formdata'] ?? [];       // Original $_POST

// Helpers to restore state:
function old(string $field, array $formdata, string $default = ''): string {
    $value = $formdata[$field] ?? $default;
    return htmlspecialchars((string)($value ?? $default));
}

function selected(string $field, string $value, array $formdata): string {
    return ((string)($formdata[$field] ?? '')) === $value ? 'selected' : '';
}

// HTML:
<input type="text" name="name" value="<?= old('name', $formdata) ?>">
<select name="artikeltyp">
    <?php foreach ($artikelTypen as $typ): ?>
        <option value="<?= htmlspecialchars($typ['code']) ?>" <?= selected('artikeltyp', $typ['code'], $formdata) ?>>
            <?= htmlspecialchars($typ['name']) ?>
        </option>
    <?php endforeach; ?>
</select>
// $artikelTypen kommt von $service->getAllArtikelTypen()

// Handler: speichern.php
$_SESSION['fehler'] = $result['fehler'];
$_SESSION['formdata'] = $_POST;
header('Location: neu.php');  // Back to form, helpers restore values
```

### Service Return Value Pattern

All Service methods follow this structure:

```php
public function save(array $data): array
{
    // On success:
    return ['erfolg' => true, 'id' => $newId];

    // On error:
    return ['erfolg' => false, 'fehler' => ['Error 1', 'Error 2']];
}

public function update(array $data): array
{
    return ['erfolg' => true];  // or ['erfolg' => false, 'fehler' => [...]]
}
```

Handler checks `$result['erfolg']` and either redirects to success page or repopulates form.

### Database & PDO

```php
// src/core/Database.php
class Database
{
    private static ?PDO $instance = null;

    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            $config = require __DIR__ . '/../../config/database.php';
            $dsn = "mysql:host={$config['host']};dbname={$config['dbname']};charset={$config['charset']}";
            self::$instance = new PDO($dsn, $config['username'], $config['password'], [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]);
        }
        return self::$instance;
    }

    private function __construct() {}  // Prevent instantiation
}

// Repository usage:
$this->db = Database::getInstance();

// ALWAYS use prepared statements:
$stmt = $this->db->prepare("SELECT * FROM artikel WHERE id = :id");
$stmt->execute(['id' => $id]);

// NEVER embed variables:
// WRONG: "WHERE id = $id"
// RIGHT: "WHERE id = :id" then execute(['id' => $id])
```

### Security

- **Always prepared statements** with named parameters (`:id`, `:name`)
- **Never concatenate** variables into SQL
- **Convert empty strings to NULL** before insert/update (data integrity)
- **Escape HTML output** with `htmlspecialchars()` in templates
- **config/database.php** is in .gitignore – credentials never committed

### Lager Module: Goods Receipt Flow

```php
// 1. Form: lager/wareneingang.php
// - EAN scan via JavaScript → variante_suche.php API
// - Select warehouse, charge (important for yarn!), quantity

// 2. Handler: lager/wareneingang_speichern.php
$service = new LagerService();
$result = $service->wareneingang([
    'artikel_varianten_id' => 5,
    'lager_id' => 1,
    'menge' => 10,
    'charge' => 'LOT-2024-001',
]);

// 3. Service: LagerService::wareneingang()
// - Validates input
// - Gets current stock: getBestand($varianteId, $lagerId)
// - Calculates new stock: bestandNachher = bestandVorher + menge
// - Upserts lagerbestand (INSERT ... ON DUPLICATE KEY UPDATE)
// - Inserts movement record in lager_bewegungen (audit trail!)

// 4. Database state:
// lagerbestand: Updated stock + charge info
// lager_bewegungen: Immutable log of movement (bestand_vorher, bestand_nachher always tracked)
```

## What's Implemented (Stand 2026-07-08, Session 26)

### Echter BFR-Hardware-Test ✅ Startbeleg-Registrierung erfolgreich (2026-07-08)
Erste echte Kasse (id=4) mit A-Trust-Demo-Signaturkarte durch den kompletten Ablauf laut `BFR_Installationsanleitung.pdf` registriert (BFR-Tool: Karte testen, Kassen-ID `DEMOvNahtlOS`, Startbeleg erstellt; danach bei uns `kasse_registrierung.php`: `/state` abgerufen, Registrierung abgeschlossen). Arbeitsplatz-Bindung (2026-07-07 gebaut) funktionierte beim ersten echten Durchlauf sofort automatisch.

**Doku-Fund:** Läuft die ERP-Software auf einem anderen Rechner als BFR, muss im BFR-Tool "Zugriff für Netzwerkkassen erlauben" gesetzt sein, sonst nimmt BFR nur `127.0.0.1` an — `docs/installation.md` Abschnitt 13 braucht diesen Nachtrag.

**Vier echte Bugs gefunden + gefixt** (erste `bfr_url` überhaupt live, vorher nie durchlaufene Codepfade):
1. `KassenService::erstelleBon()`: `$steuer`-Array (Steuergruppen für BFR-Signatur) wurde von einer zweiten, gleichnamigen Akkumulator-Variable überschrieben → zwei echte signierte Testbelege gingen mit Steuergruppen=0,00 (nur Gesamtbetrag korrekt) an den BFR raus. Auf einer echten Karte wäre das ein Fall für eine Kassen-ID-Neuregistrierung gewesen. Fix: zweite Variable umbenannt (`$steuerBetrag`).
2. `bon_speichern.php`: `kasse_id` kam trotz gegenteiligem Kommentar aus dem Client-Payload statt aus der server-geprüften Arbeitsplatz-Bindung (`$aktuelleKasseId`). Gefixt.
3. Negativ-Zähler-Sperre (`wuerdeUmsatzzaehlerNegativWerden`) fehlte im Retour-Pfad (existierte nur in `storniereBon()`) — eine kassenübergreifende Retour (bezahlt auf Kasse A, zurückgegeben auf Kasse B) hätte den lokalen Zähler von Kasse B unter Null drücken können, NACH bereits erfolgter Barauszahlung. Fix: gleicher Vorab-Check jetzt auch in `bon_speichern.php`.
4. `zeileMinus()` in `bon.php` löschte eine Auftrags-Warenkorbzeile komplett bei Menge 1→0, statt sie auf 0 zu belassen — verhinderte die vollständige Rückgabe eines Einzelpostens. Gefixt, `zeilePlus()` kann das jetzt bis `original_menge` rückgängig machen.
5. Workflow: `auftragWaehlen()` zeigte bei `versendet`/`abgeschlossen`-Aufträgen den unpassenden "Mitnehmen/nur Zahlung"-Dialog — jetzt übersprungen zugunsten direkter Mengen-Anpassung (Retoure).

Alle Bugs live gegen Kasse 4 verifiziert (3 Test-Bons, 2 sauber storniert, künstlich niedriger Zähler zum Testen der Retour-Sperre).

### Redesign Auftrag-Lade-Flow ✅ FERTIG (gleicher Tag, 2026-07-08 Nachmittag)
Modus (Retoure/Abholung) wird jetzt direkt aus `lieferstatus`+`zahlungsstatus` beim Laden abgeleitet statt hinterher aus der Mengen-Differenz erraten. `versendet`/`teilgeliefert`/`abgeschlossen` → neue "↩ Retoure zu A-xxx"-Sektion (parallel zum normalen Warenkorb, ein gemeinsamer Bon), mit Chargen-Anzeige aus dem Warenausgang. Details + fünf dabei gefundene Bugs (darunter ein **ENUM-Bug in `kassen_bon_positionen.block`, das 'retour' komplett fehlte** — seit Feature-Bau 2026-06-29, Migration 119 behoben) in Memory `project_kasse_bon_design`.

### Extremtest + Doppel-Gutschrift-Sperre ✅ FERTIG (gleicher Tag, Abschluss 2026-07-08)
Jackys eigener Extremtest (zwei Aufträge, Kasse-Retoure + Abholung + ERP-Artikel + Freitext-Artikel kombiniert, danach alle Tabellen kontrolliert) deckte auf: `versendet`+`bezahlt` springt durch eine Auto-Logik im Warenausgang sofort auf `abgeschlossen` (jetzt überall als Retoure-Kandidat mitgezählt), und eine Kasse-Retoure ohne Rückverfolgung zur Original-Position hätte an vier Stellen doppelt gutgeschrieben werden können (Zahlungsverlauf "Offen" trotz Retoure, Teilgutschrift + Vollstorno ignorierten bereits Retourniertes, die Kasse selbst hätte beim zweiten Laden erneut die volle Menge angeboten). Neue Spalte `auftrag_positionen.menge_retourniert` (Migration 120, bewusst getrennt von `menge_geliefert`) an allen vier Stellen durchgesetzt, jeweils mit serverseitiger Prüfung (nicht nur Client-Grenzen). Details in Memory `project_kasse_bon_design`.

**Für nächste Session vorgemerkt** (siehe Memory `project_kasse_bon_design`, `project_kassen_verwaltung`):
- Freitext-Retour für JTL-Altbestand (Rückgaben ohne Auftrag im ERP)
- Packplatz-Benachrichtigung bei Kassen-Retour (offene Warteschlange statt automatischer oder gar keiner Lagerbuchung)
- Mehrere Aufträge gleichzeitig in einem Bon — wartet auf Barbara-Feedback, nicht spekulativ bauen
- Kasse-Name/-Nummer fehlt im Kasse-Header
- `docs/installation.md` Netzwerkkassen-Häkchen nachtragen
- Freitext-Artikel fehlen im K1-Auftrag-Spiegel (K1-Split-Filter verlangt artikel_id) — Bon selbst korrekt, nur interne Spiegelung unvollständig

### Logo + QR-Code-Größe auf Kassen-Belegen ✅ FERTIG (ganz zum Schluss desselben Tages)
QR-Code auf A4 war unscharf/zu klein (25mm bei 100px) → 30mm bei 300px. Firmenlogo fehlte auf beiden Vorlagen komplett — hing an einem nie befüllten, falschen Einstellungs-Schlüssel (`firmen_logo`); jetzt auf den echten, längst funktionierenden `shops.logo_pfad`-Mechanismus umgestellt (wie bei den normalen Web-Auftrags-Dokumenten). Details in Memory `project_kasse_bon_design`.

## What's Implemented (Stand 2026-07-05, Session 25)

### Benutzerverwaltung ✅ FERTIG
- `public/benutzer/liste.php` (Liste + Neu/Bearbeiten-Modal), `BenutzerRepository`/`BenutzerService`/`PasswortResetService` (`src/modules/benutzer/`)
- Passwort-Setzen-Link per Mail (Migration 108: `benutzer_passwort_tokens`, SHA-256-Hash, 24h gültig) ODER Admin setzt direkt — plus öffentliche "Passwort vergessen"-Seite (`passwort_vergessen.php` → `passwort_setzen.php`, verlinkt von `login.php`)
- Reservierter Username `system` (Jarvis) abgelehnt, ein Benutzer = eine Rolle (bewusst vereinfacht)

### Rollen & Rechte-Matrix ✅ FERTIG
- Migration 109: `rollen.rang`, 6 neue Rollen (Assistent/Manager/Kassier/Lager/Packplatz/Praktikant/Readonly), 27 neue Berechtigungen (insgesamt 72)
- `public/rollen/matrix.php` (`src/modules/rollen/`): Checkbox-Matrix, Rang-basierte Bearbeitungssperre (nur echt niedrigerer Rang), `lizenz.verwalten` für jede Rolle fix gesperrt
- Superadmin (Rang ≥ 100) hat `Auth::kann()` immer `true` — Code-Invariante statt Datenabhängigkeit

### Lagerverwaltung (Stammdaten-UI) ✅ FERTIG
- Migration 107, `public/lager/verwaltung*.php`: 3 Karten (Eigen/Partner-Bestand/Händler-Außenlager), `LagerRepository`/`LagerService` erweitert

### Rechteverwaltung: echte Durchsetzung ✅ FERTIG
- **Zentrale Regeltabelle statt 230 Einzel-Checks**: `src/core/Zugriffsregeln.php` — Array `[Verzeichnis][Datei] => Berechtigung`. `Auth::pruefeSeite()` (in `Auth.php`) schlägt dort bei jedem Seitenaufruf nach (aus `auth_check.php` aufgerufen, direkt nach `Auth::check()`). Kein Eintrag → kein Block (nur Login-Pflicht wie bisher)
- Zwei Antwortformen: normale Seiten → Redirect auf neue `zugriff_verweigert.php`; AJAX-Endpunkte (83 identifiziert) → `{erfolg:false, fehler:"..."}` passend zur bestehenden JS-Konvention
- `dashboard.php` leitet bei fehlendem `dashboard.zugriff` über `Auth::startseiteFuerBenutzer()` aufs erste erlaubte Modul um statt eine Fehlerseite zu zeigen
- Live getestet (curl gegen echten Apache): Fake-Superadmin kommt überall durch, eingeschränkte Testrolle nur dort wo erlaubt

### Manager-Override per PIN ✅ FERTIG
- Migration 110: `benutzer.manager_pin_hash` (bcrypt). `Auth::pruefeManagerPin()` sucht ohne Benutzername über alle Manager+ (Rang ≥ 70) mit gesetztem PIN
- PIN wird self-service in `benutzer/profil.php` gesetzt (nur für Manager+ sichtbar)
- **Kasse-Auszahlung** (`bon_speichern.php`, Retour eines bezahlten Web-Auftrags): Popup in `bon.php` (`ov-manager-pin`), Prüfung läuft vor jeder Buchung
- **Packplatz-Gutschrift** (`packplatz/retoure/speichern.php`): PIN-Feld direkt im Formular (`detail.php`), nur sichtbar ohne `packplatz.gutschrift`-Recht
- Beide loggen `manager_override` (Auslöser + freigebender Manager). Offline-Kasse bewusst nicht betroffen — hat keinen Kundenbezug/keine Retouren, der Pfad wird dort nie erreicht

### 🔴 Kritischer Bugfix: cron/mahnwesen.php lief noch nie fehlerfrei durch
- Drei Schema-Drift-Bugs seit dem Kundendatenbank-Verschlüsselungs-Umbau: `a.auftragsnummer` (richtig: `auftrag_nr`), `LEFT JOIN kunden k` auf inzwischen verschlüsselte `email`-Spalte, `INSERT INTO auftrag_status_log` (richtig: `auftrag_statuslog`, andere Spalten, jetzt über `AuftragRepository::logStatus()`)
- Migration 111: `mahnungen.typ` ENUM um `'hinweis'` ergänzt (fehlte für den Rechnung-30-Tage-Zweig); `'teilbezahlt'` fehlte im WHERE-Filter
- Getestet mit isoliertem, danach vollständig gelöschtem Test-Auftrag (künstlich 15 dann 31 Tage alt) — Erinnerungs- und Storno-Zweig laufen jetzt durch
- **Separater Kasse-Bug**: `bon_speichern.php` verglich `$auftragAnteil` (nur die aktuelle Transaktion) statt der kumulierten `auftrag_zahlungen`-Summe gegen `bruttobetrag` → Aufträge blieben bei Rundungsdifferenzen dauerhaft auf 'teilbezahlt' stehen. Fix analog zu `AuftragService::bucheZahlung()`, ein betroffener Dev-Auftrag korrigiert
- **Dashboard-Widget "Offene Kundenrechnungen"**: zeigte Tage-bis-Fälligkeit (bis zu 14 Tage lang `<1` bei jungen Vorkasse-Aufträgen) statt echtem Bestellalter — jetzt dieselbe Kennzahl wie im Cron, plus Filter gegen bereits ausgeglichene Salden

## What's Implemented (Stand 2026-07-04, Session 24)

### A4-Rechnungsdruck komplett ✅ (2026-07-04)
- **`src/modules/kasse/BonA4Renderer.php`** (neu): HTML/CSS-Aufbau aus `bon_a4.php` extrahiert in `render(int $bonId, bool $fuerPdf = false)` — `fuerPdf=true` unterdrückt die Druck/Schließen-Buttonleiste
- `kasse/bon_a4.php`: jetzt dünner Wrapper um `BonA4Renderer::render()`
- `kasse/bon_speichern.php`: Mailanhang (Abholbestätigung) nutzt jetzt die A4-Rechnung statt eines eigenen inline gebauten 68mm-Thermobon-PDFs; Dompdf-Papierformat auf A4 Hochformat umgestellt
- `kasse/bon_journal.php`: "A4"-Button neben dem bestehenden 🖨-Button für Verkauf/Storno-Zeilen (Nachdruck älterer Bons, vorher nur direkt nach Erstellung erreichbar)
- `auftraege/detail.php`: bei Kassen-Aufträgen zusätzlich "Als A4 drucken" neben "Kassenbon drucken"

### Rechnung-Mail: Zahlungsstatus korrekt anzeigen ✅ (2026-07-04)
- `templates/mails/rechnung_mail.html.twig`: 3 Zustände (`bezahlt` → Dank + Zahlungsübersicht, `teilbezahlt` → Teilzahlungen + Restbetrag, sonst → ursprünglicher "bitte zahlen"-Text) statt immer denselben Zahlungshinweis
- `packplatz/warenausgang/abschliessen.php` (Auto-Mail) + `auftraege/dokument_erstellen.php` (manueller Versand): beide ermitteln jetzt `zahlungsstatus`/`bezahlt_gesamt`/`offener_betrag` über `AuftragRepository::findZahlungen()` und übergeben sie ans Template

### Offline-Kasse (Messe) ✅ FERTIG — bereit für BFR-Hardware-Test (2026-07-04)
Kompletter Workflow: **Vorbereitung → Offline-Verkauf → Rückkehr**, nicht nur der Kassen-Client allein.
- `kasse/messe_vorbereiten.php` + `js/kasse_messe_vorbereiten.js`: Artikel scannen/suchen, Ziel-Kasse + Ziel-Lager + **Quell-Lager wählen** (war anfangs hart auf Lager 1 codiert — echter Bug, da mehrere Quelllager existieren können), bei chargenpflichtigen Artikeln echte Chargen-Auswahl mit +/−-Stepper (live "verfügbar"/"im Warenkorb", keine Übermengen möglich)
- `kasse/bon_offline.php` + `js/kasse_bon_offline.js` + `kasse/sw_bon_offline.js`: der eigentliche Offline-Client. **Überlebt Browser-Absturz/Neustart ganz ohne Serververbindung** (Service Worker cached die App-Hülle, IndexedDB hält Artikel/Chargen/noch nicht hochgeladene Bons) — Sync-Daten müssen nur einmalig online geladen werden. Freier Artikel (Divers-Platzhalter), Textsuche ab 2 Zeichen, echte Chargen-Auswahl (keine Freitext-Chargen!), BFR-Signierung direkt per `fetch()` vom Browser
- `kasse/messe_rueckkehr.php` + `js/kasse_messe_rueckkehr.js`: Rückbuchung pro Charge (Rückgabe/Schwund), danach retroaktiver Z-Bon pro Messetag möglich (Datumsfeld in `kassensturz.php`, leer = heute)
- `src/modules/kasse/MesseSyncService.php`: Chargen-Tracking durchgängig (`kassen_messe_umbuchungen.charge`, Migration 106), `preSyncExportieren()` liefert `bon_nr_zaehler`/`divers_artikel_id`/`chargen` an den Offline-Client
- **Korrekturrunde nach Praxis-Feedback**: ursprüngliche Version verlangte durchgehend geöffneten Browser-Tab (unbrauchbar bei mehrtägigen Messen) und erlaubte Freitext-Chargen beim Umbuchen (hätte Lagerstände korrumpiert) — beides überarbeitet, siehe [[project_kassen_verwaltung]]
- `docs/offline_kasse_anleitung.md`: vollständige Anleitung inkl. Feature-Vergleich online/offline und bekannte Lücken

### Bugfixes beim ersten echten Browser-Test gefunden (2026-07-04)
- **`kasse/shell_top.php` fehlte `window.BASE_PATH`**: beim gestrigen BASE_PATH-Umbau übersehen (nur die allgemeine `includes/shell_top.php` hatte es bekommen) — Kasse-Seiten mit externem JS (Messe-Vorbereitung/-Rückkehr) bauten dadurch kaputte URLs (`.../undefined/kasse/...`)
- **`ajax_messe.php` rief `Auth::requireLogin()`/`Auth::getUserId()`** — beide Methoden existieren nicht in der `Auth`-Klasse (Konvention im Projekt: `require auth_check.php` + `$_SESSION['benutzer']['id']`). Verursachte einen PHP-Fatal-Error → HTML statt JSON → "Netzwerkfehler" im Frontend
- **`MesseSyncService::umbuchungZurMesse()` legte bei jedem Klick ein neues Sync-Paket an** — jetzt wird ein offenes ("vorbereitet") Paket pro Kasse+Lager-Kombination wiederverwendet, gleiche Artikel+Charge werden addiert statt dupliziert
- **Artikel-Lagerbestand ohne Chargen-Zuordnung war unsichtbar**: `LagerRepository::findBestandChargeProLager()` filterte Zeilen mit `charge IS NULL` komplett aus der Chargen-Liste, zählte sie aber in der Gesamtsumme mit — führte zu "Gesamt: 3, aber nur 2 in Chargen auffindbar". Jetzt sichtbar (rot, "— ohne Charge —") bei chargenpflichtigen Artikeln; zusätzlich werden Chargen mit Bestand 0 komplett ausgeblendet (sonst sammeln sich bei guten Sellern nach Jahren viele längst leere Chargen an)
- **Bewegungslog-Chargen-Filter** (`artikel/detail.php`, Tab Lager): Dropdown mit allen historischen Chargen eines Artikels — Auswahl lädt per AJAX (`artikel/bewegungslog_ajax.php`) die vollständige Bewegungshistorie dieser einen Charge (EK bis letzter Verkauf) ohne die sonst übliche 10er-Anzeigegrenze

## What's Implemented (Stand 2026-07-03, Session 23)

### Root-Pfad konfigurierbar + Versionsnummer ✅ (2026-07-03)
- **`erp/config/bootstrap.php`** (neu): definiert `BASE_PATH` (aus `$_SERVER['SCRIPT_NAME']`, erster Pfadteil — passt sich automatisch an, egal wie der Installationsordner heißt) und `APP_VERSION` (aus neuer Datei `erp/VERSION`, aktuell `0.1.0`). Eingebunden in `auth_check.php`, `login.php`, `index.php` — deckt praktisch jede Seite ab.
- `window.BASE_PATH` einmal in `shell_top.php` gesetzt, alle `.js`-Dateien nutzen das statt hartem `/mealana/`.
- Alle 449 hartcodierten `/mealana/`-Stellen in 122 Dateien ersetzt (PHP-Tokenizer-Script für den Großteil, ~19 Heredoc-Sonderfälle mit `{$basePath}`-Interpolation von Hand, JS-Dateien über einen zeichenweisen Quote-Parser).
- `APP_VERSION` wird in der Statusbar (`shell_bottom.php`) sowie im Footer von `kasse/index.php` und `packplatz/index.php` angezeigt.

### NAHTLOS-Branding ✅ (2026-07-03)
- Barbara hat ein eigenes Software-Logo designt (Nadel/Kleeblatt-Icon + Wortmarke "NahtlOS" + Tagline). Entscheidung: NAHTLOS ersetzt komplett das bisherige Kunden-/Shop-Logo im Header (wie JTL/Shopware ihre eigene Marke zeigen, nicht die des Kunden) — spart die "Logo pro Installation konfigurierbar machen"-Aufgabe aus der Weitergabe-Liste komplett ein.
- `erp/public/img/nahtlos.png` (Vollversion) + `nahtlos_icon.png` (per PHP-GD rausgeschnittenes Icon ohne Schriftzug, für den knappen Nav-Header)
- Header (`shell_top.php`): nur das Icon, gleiche Größe wie vorheriges Logo (36px)
- Vollversion prominent auf Login, Start-Seite, Kasse-Auswahl (`kasse/index.php`), Packplatz-Auswahl (`packplatz/index.php`) — auf dunklen Hintergründen in einer weißen Karte (PNG hat keinen Alpha-Kanal)

### Bugfixes Kasse/Packplatz (2026-07-03)
- **`ajax_parken.php`**: las `$_SESSION['user_id']` (existiert nicht) statt `$_SESSION['benutzer']['id']` — `kassierer_id` beim Bon-Parken war immer NULL
- **Teillieferung-Status**: `packplatz/warenausgang/abschliessen.php` prüft jetzt nach jeder Buchung ob wirklich noch offene Positionen übrig sind, statt blind der vom Formular übergebenen Teillieferung-Markierung zu vertrauen — verhindert dass Aufträge fälschlich auf `teilgeliefert` hängen bleiben
- **Picklisten schlossen nie, wenn über "Auftrag direkt verpacken" statt über die Pickliste selbst abgeschlossen** (`scan.php` erkennt die zugehörige Pickliste jetzt automatisch; `warenausgang/index.php` blendet Aufträge die schon auf einer Pickliste stehen aus der Direktauswahl aus). Einmalige Datenbereinigung: 15 bereits betroffene Picklisten auf `abgeschlossen` gesetzt.

### Rechte & Rollen / Kleinunternehmer — Ist-Stand richtiggestellt (kein Code, nur Klarheit)
- Rechte & Rollen: DB-Tabellen existieren (3 Rollen, ~40 Berechtigungen), aber `Auth::kann()` wird im gesamten Code nur an einer einzigen (deaktivierten) Stelle geprüft — **keine tatsächliche Zugriffssteuerung** auf Artikel/Lager/etc. Die von Jacky vorgesehene Gruppen-Logik (Pool an Berechtigungen, Admin weist zu, Superadmin schaltet Admins frei) ist komplett ungebaut.
- Kleinunternehmer-Modus: "Kein Steuerausweis auf Rechnungen" funktioniert (DokumentService + Twig-Template), "EK brutto verbuchen"/Lagerbewertung ist NICHT an den Schalter gekoppelt.

### RKSV/BFR — offene Rückfrage geklärt
Hersteller bestätigt: BFR antwortet immer entweder `RC='OK'` oder gar nicht — der Fall "erreichbar aber aktiv abgelehnt" existiert nicht in der Praxis. Kein Code-Änderungsbedarf, RKSV-Integration bleibt vollständig wie am 2026-07-02 fertiggestellt.

## What's Implemented (Stand 2026-07-03, Session 22)

### Erstes Live-Deployment ✅ (2026-07-03)
- Server-PC (separates Windows-System, 192.168.178.222, läuft daneben auch MS SQL Server Express für den alten JTL-WAWI) neu mit XAMPP (Apache+MariaDB+PHP 8.2) aufgesetzt, Code per `git archive` deployed (siehe `docs/installation.md`)
- **`erp/database/migrate.php`**: Migrations-Runner mit Tracking-Tabelle `schema_migrations` — `run`/`status`/`bootstrap`. Ersetzt manuelles Einspielen aller Migrationsdateien
- **`erp/database/baseline_schema.sql`**: Struktur-only-Dump (kein Daten) als Installationsgrundlage — nötig weil Migrationen 001–003 fehlen und `004`–`104` sich nicht von einer leeren DB weg durchspielen lassen (FK-Fehler ab Migration 006)
- **`erp/database/create_admin.php`**: interaktives CLI-Skript für den ersten Admin-Benutzer (kein manuelles Hash-Basteln mehr). Bewusst **kein** fix eingebauter Superadmin mit gleichem Passwort über alle Installationen (Sicherheitsrisiko, siehe `docs/installation.md` Anhang B)
- **Migration 105**: seedet Jarvis-Systembenutzer automatisch (idempotent, `INSERT IGNORE`, keine feste ID)
- **WireGuard-VPN** produktiv eingerichtet (Server `10.13.13.1`, erster Client `10.13.13.2`) — Remote-Zugriff ohne AnyDesk, Anleitung inkl. "weiteren Client hinzufügen" in `docs/installation.md` Anhang C
- **`.gitattributes`** mit `export-ignore` für `schema_current.sql`, echte Artikelbilder/Logos, `shell-test.php` — `git archive` liefert seither ein sauberes Deployment-Paket ohne Dev-/Produktivdaten
- `docs/installation.md` neu geschrieben — Zielgruppe: eigener Rollout UND spätere Weitergabe an Tester

### Bugfixes, beim ersten echten Live-Test gefunden (2026-07-03)
- **Migration 005**: fehlendes Semikolon brach Multi-Statement-Ausführung
- **`BfrService.php`**: Jarvis-Lookup war hart auf `id=2` codiert, jetzt (wie `LagerService`) per `username='system'`
- **`erp/cron/mahnwesen.php`**: `Logger::log()` ohne Session (Cron-Kontext) crashte an `aktivitaeten.benutzer_id NOT NULL` — Jarvis-ID jetzt explizit übergeben
- **`erp/public/dashboard.php`**: `max(1, ...array_column(...))` warf `TypeError` bei leerem Array (frische Installation ohne Kassenbons) — jetzt `max([1, ...])`
- **`erp/public/index.php`**: fehlte komplett — Apache zeigte Verzeichnisliste statt zum Login weiterzuleiten

### Backup-Strategie — geplant, noch nicht gebaut
- DB täglich, Artikelbilder quartalsweise (Jackys bewusste Entscheidung), `erp/storage/`-PDFs brauchen kein eigenes Backup (aus DB+Templates neu erzeugbar), `encryption.php` getrennt vom DB-Backup aufbewahren
- Speicherort offen — Jacky prüft eigene Proxmox-Infrastruktur (Kandidat: Proxmox Backup Server)

## What's Implemented (Stand 2026-07-02, Session 21)

### RKSV/BFR-Integration ✅ VOLLSTÄNDIG (2026-07-02)
- Migrations 097–104: `laender` (Referenztabelle + EU-Flag), `lieferanten`-Erweiterung (siehe unten), `bfr_nachsignierungs_laeufe`, `bfr_nullbelege`, `kassen_bons` (steuer_a-e, bfr_status, bfr_fehlergrund, signiert_am, nachsignierungs_lauf_id), `kassen` (bfr_url, bfr_umsatzzaehler, bfr_aktiv_seit), `bfr_kassen_registrierungen`
- **`src/modules/kasse/BfrService.php`**: Signierung Verkauf+Storno (`signiereAusstehende`, TN-Reihenfolge strikt eingehalten), Nullbeleg (monatlich automatisch + manuell), Gesamtumsatzzähler-Sperre (Storno vorab abgelehnt statt falsches "ausgefallen"-Signal), Kassen-Registrierung (Protokoll/Backup für FinanzOnline-Meldung, sperrt sich nach Abschluss), `bfr_aktiv_seit`-Stichtag verhindert Nachsignierung historischer/Kassen-ID-fremder Belege
- `KassenService::erstelleBon()`/`storniereBon()`: Steuergruppen-Berechnung + Signierung nach Commit (BFR-Ausfall darf Verkauf nie verhindern)
- `public/kasse/nacherfassung.php` — offene/fehlerhafte Belege + Sammelbeleg-Historie (Nachsignierungsläufe) + Retry
- `public/einstellungen/kasse_registrierung.php` — Kassen-ID/BFR-URL-Verwaltung, sperrt sich nach Abschluss (Hardware-Wechsel = neue Registrierung)
- `cron/bfr_nachsignierung.php` — alle 5 Min: Nullbeleg-Check + Nachsignierung pro Kasse
- `src/core/QrCode.php` (endroid/qr-code) — echter QR-Code live aus `rksv_qr` gerendert, kein Datei-Caching nötig
- X-Bon/Z-Bon sind laut RKSV NICHT signaturpflichtig (reine interne Berichte)

### Lieferanten-Erweiterung ✅ (2026-07-02)
- Migrations 097–099: `laender`-Tabelle (Land-Dropdown statt Freitext), `lieferanten` ALTER (firma, firmenzusatz, ustid, steuerregel-Enum, standard_lieferkosten, iban/bic/bank_name/kontoinhaber)
- `lieferanten_vertreter`: `anrede`-Feld; Vertreter-Anlage als Repeatable-Row im Lieferanten-Neuformular (`public/js/lieferanten_neu.js`)
- Kreditorennummer/DATEV-Zuordnung bewusst NICHT hier — kommt als eigene Liste im Buchhaltungsmodul

### Bugfix: Hersteller-Neuanlage ✅ (2026-07-02)
- `HerstellerService::save()`: verstecktes `id`-Feld aus dem Modal-Formular brach `insert()` (PDO `HY093: number of bound variables`) — mit `unset($data['id'])` behoben
- Systemweit geprüft: `ArtikelRepository` bereits sicher (`array_intersect_key`), `PartnerRepository::insert()` technisch gleiche Schwachstelle aber aktuell nicht ausgelöst (separates Neu-Formular ohne id-Feld)

## What's Implemented (Stand 2026-06-29, Session 19)

### Artikel Module (CRUD Complete)
- List with search + active/inactive filter
- Create with form repopulation on error
- Edit, Soft delete (aktiv = 0)
- Artikel kopieren (kopieren.php + kopieren_speichern.php + ArtikelService::kopiere())
- Price storage (artikel_preise table, standard customer group)
- Detail view mit Tab-Navigation (Stammdaten / Varianten+Kinder / Lager / Lieferanten)
- artikeltyp kommt aus DB (artikel_typen Tabelle, kein ENUM)
- Lieferanten-Tab in detail.php
- Filterung in liste.php (aktiv/inaktiv, Suche)
- **Bulk-Kategorie-Zuweisung** ✅ (2026-06-26): Mehrfachauswahl → Aktion → Modal mit Kategoriebaum → INSERT IGNORE (inkl. Kinder)
- **Fehlbest.-Chip**: nur wenn reserviert > gesamtbestand (nicht mehr bei Bestand=0 ohne Reservierung)
- **artikel_preise JOIN**: Datumsfilter via korrelierter Subquery — aktiver Sonderpreis schlägt Basispreis

### Vater-Kind Vererbung (vollständig — 2026-06-19)
- **erstelleKombinationen()** erbt alle ~25 Felder vom Vater (vorher nur 4)
- **kopiereVaterRelationenZuKindern()** kopiert Kategorien, Merkmale, Lieferanten, Preise zu neu erstellten Kindern
- **propagiereZuKindern()** — ein UPDATE propagiert alle gemeinsamen Felder beim Vater-Update
- **syncKategorienZuKindern()** — Kategorien werden bei saveKategorien() auf alle Kinder synchronisiert
- Nicht propagiert: artikelnummer, name, url_slug, aktiv, ist_auslaufartikel (eigene Logik), zustand

### Varianten-System (vollständig — DB + UI + Generator fertig, aktualisiert 2026-06-26)
- Migrations 022–041 ausgeführt: Achsen, Werte, Kombinationen, abhängige Achsen, Gruppenachse-Flag
- Kind-Artikel (Varianten) werden als artikel-Einträge mit vaterartikel_id gespeichert
- **Achsen-Verwaltung** (public/achsen/) — volles CRUD ✅
- **Achsen zuweisen** (public/artikel/achsen_zuweisen.php + achsen_speichern.php) ✅
  - Baumstruktur: Gruppenachse + eingerückte Sub-Achsen
  - Chip-Input: Werte hinzufügen, ◀▶ sortieren, ↔ zwischen Achsen verschieben, Inline-Bearbeiten
  - Granulare Sperrung: 🔒-Chip für in-use Werte (in Kombinationen verwendet), freie editierbar
  - **Aufpreis/Direktpreis pro Achse** (Migration 074): Toggle [+€][€] neben Achsenname, gilt für alle Werte
  - **sort_order**: wird beim Speichern korrekt persistiert (INSERT + UPDATE)
- **VarKombi-Generator** (in detail.php, Tab "Varianten") ✅
  - Achsen-Hierarchie-bewusst: Sub-Achsen-Werte immer UNION in eine Dimension
  - Kindname = Vatername + Achsenname + Wert (sortierbar in Liste)
  - EAN-Feld im Generator: direkt bei Erstellung eintragbar, wird in artikel_codes gespeichert
  - EAN wird in liste.php (Kind-Zeilen) und detail.php (Varianten-Tab) angezeigt
  - Bereits bestehende Kombis erkannt (nicht doppelt erstellt)
  - Kind-Artikel erben: alle Vater-Felder + Kategorien + Merkmale + Lieferanten + Preise
- AchsenRepository/Service + VariantenRepository/Service ✅
  - VariantenRepository: findWertIdsInUse, deleteWerteExcluding, deleteArtikelAchse
  - VariantenService: granulare speichereAchsenUndWerte, erstelleKombinationen (gibt IDs zurück)

### Lager Module (Functional + Shell-migriert ✅ 2026-06-19)
- Goods receipt with EAN barcode scan support
- Variant search API (variante_suche.php returns JSON)
- Stock UPSERT (create or increase)
- Movement audit log (lager_bewegungen)
- Charge tracking with status
- Charge Nachtrag-Workflow (nachtrag_liste.php + nachtrag_speichern.php)
- Shell-Design: uebersicht.php, wareneingang.php, nachtrag_liste.php alle migriert

### Lieferanten Module (CRUD Complete + Shell-migriert ✅ 2026-06-19)
- Lieferanten CRUD (neu/bearbeiten/löschen/detail)
- Vertreter CRUD pro Lieferant (neu/bearbeiten/löschen)
- Alle Handler als separate speichern.php / aktualisieren.php (kein Inline-POST)
- Shell-Design: liste, detail, neu, bearbeiten, vertreter_neu, vertreter_bearbeiten alle migriert

### Auth & RBAC (vollständig)
- Auth.php, Logger.php, login.php, auth_check.php — fertig
- 3 Rollen: superadmin, admin, mitarbeiter
- Logger::log() mit optionalem ?int $benutzerId — Fallback auf Session, Jarvis-Einträge mit System-User-ID

### Auslaufartikel + Überverkauf (2026-06-11)
- ist_auslaufartikel: Orange Highlighting, Auto-Reaktivierung bei Wareneingang, Vater folgt Kindern
- ueberverkauf_erlaubt: Checkbox in bearbeiten.php + neu.php, blauer Banner in detail.php
- reservierungen-Tabelle (Migration 021): Wird von Shop/Kasse befüllt wenn Bestand ≤ 0

### Aktions-Kategorie Zuweisung ✅ (2026-06-19)
- ⏰ Symbol in Shell-Kategoriebaum (links) UND im Artikel-Kategorie-Modal (orange=aktiv, grau=geplant)
- Modal für Aktionspreiseingabe direkt nach Kategorie-Speichern: zeigt alle betroffenen Aktionen (aktive + geplante), gruppiert pro Aktion, Preise direkt eintragbar via `aktion_preise_speichern.php`
- `KategorieRepository::updateArtikelKategoriezuweisungen` → gibt neue Aktionen zurück
- `AktionenRepository::getExistingPreiseFuerArtikel` → Pre-Fill vorhandener Preise

### Aktions-Kategorie ⏰ 3-Zustände ✅ (2026-06-19)
- `KategorieRepository::findAllMitEltern()`: 3 neue SQL-Spalten via LEFT JOIN auf aktionen_kategorien + aktionen
  - `aktion_aktiv`: gestartet=1 AND CURDATE() BETWEEN gueltig_ab AND gueltig_bis
  - `aktion_zukunft`: gueltig_ab > CURDATE() (kein gestartet-Filter — Entwurf+Zukunft = geplant)
  - `aktion_info`: GROUP_CONCAT(name + Datumsbereich) für Hover-Tooltip
- Rendering in `shell_top.php` + `kategorien_verwalten.php`: aktiv=orange ⏰, geplant=orange ⏰+dimmed, abgelaufen=grau ⏰+dimmed
- **Wichtig:** CSS `color:#aaa` hat keine Wirkung auf Farb-Emojis — `filter:grayscale(1)` verwenden!
- Hover-Tooltip (HTML `title`-Attribut): zeigt Aktionsname + Datumsbereich

### Testdaten-Cleanup-Script ✅ (2026-06-19)
- `erp/database/truncate_testdaten.sql` — löscht alle Artikel/Kunden/Aktionsdaten
- Behält: kategorien, steuerklassen, artikel_typen, einheiten, lager, hersteller, merkmale, benutzer, rollen, system_einstellungen
- Technik: `DELETE FROM` statt `TRUNCATE` (InnoDB FK-Constraint); selbstreferentielle FK (vaterartikel_id) → erst Kinder, dann Väter löschen

### Qualitätslisten ✅ (2026-06-19)
- Filter in `artikel/liste.php`: Keine EAN / Doppelte EAN / Keine Bilder (via `<optgroup>` im Status-Dropdown)
- SQL-Subqueries in `ArtikelRepository::findAll()` + `countAll()` für alle drei Qualitätsprüfungen
- Qualitäts-Chips werden nur bei aktivem Filter angezeigt (nicht jede Zeile)
- Für Druck-Aufbereitung vorgemerkt: wenn Druck-Modul kommt, diese Listen als erstes

### Shell-Check ✅ (2026-06-19)
- Kategoriebaum rechts jetzt auf ALLEN Artikel-Shell-Seiten: `bearbeiten.php`, `achsen_zuweisen.php`, `achsen/liste.php` hatten `$kategorienBaum` gefehlt → nachgetragen
- Sidebar-Links bereinigt: tote `#`-Links `Bilder`, `SEO`, `Statistik` entfernt (kein Zielseite vorhanden)
- Sidebar Artikel: Liste / Neu erstellen / Kategorien / Merkmale / Preise & Aktionen

### Bilder-Modul ✅ (2026-06-19)
- Migration 045: `artikel_bilder` (id/artikel_id UNSIGNED, dateiname, alt_text, position) + `artikel_bilder_shops` (bild_id+shop_id → external_id, sync_status)
- `BilderRepository.php` — CRUD, setzeHauptbild (Position 0), verschiebePosition (schützt Position 0)
- `bild_upload.php` — PHP GD Resize max 1920px JPEG 85%, MIME-Check, Ordner auto-erstellt
- `bild_ajax.php` — Sammel-Handler aktion=hauptbild|position|alt_text
- `bild_loeschen.php` — unlink + DB
- `bilder.js` — Event Delegation auf #bild-grid, aktualisiereAlleKarten() nach jeder Aktion
- Drag & Drop Upload, ☆ Hauptbild-Swap, ↑↓ Reihenfolge, Alt-Text on-blur, Löschen mit Confirm
- PHP GD aktivieren in XAMPP: php.ini `extension=gd` auskommentieren
- Wasserzeichen: ERP speichert clean Original → Wasserzeichen beim Shop-Sync (GD on-the-fly), Admin-Einstellung pro Shop geplant
- WooCommerce-Sync noch offen (braucht echten WC-Server)

### Kunden-Modul ✅ VOLLSTÄNDIG (2026-06-19)
- Migrations 046 (zahlungsbedingungen), 047 (6 Kunden-Tabellen: kunden, kunden_adressen, kunden_ansprechpartner, kunden_dsgvo_consent, kunden_shops, kunden_merge_queue)
- Laufkunde: id=1, ist_laufkunde=1, fest in DB — Kasse-Standardkunde, kein Login
- **`src/core/Encryption.php`** — AES-256-GCM, per-Record IV, HMAC-SHA256 für E-Mail-Suche
  - Keys in `erp/config/encryption.php` (gitignored!) als 256-bit Hex
  - `encrypt(?string): ?string` / `decrypt(?string): ?string` / `hash(?string): ?string`
  - Crypto-Shredding für DSGVO Art. 17 Löschungen vorbereitet
- **`KundenRepository`**: transparente Ver-/Entschlüsselung — Views sehen nur Klartext
  - `verschluesseln()` / `entschluesseln()` als private Helfer
  - `nextKundennummer()`: KD-00001, KD-00002, …
  - Adress-CRUD mit Standard-Flag-Verwaltung
- **`KundenService`**: Validierung, E-Mail-Duplikat via Hash, Kundengruppen-Default (ist_standard=1)
- **`public/kunden/`**: liste, neu/speichern, detail (4 Tabs), bearbeiten/aktualisieren, status_setzen
  - detail.php Tabs: Stammdaten | Adressen | DSGVO/Consent | Bestellungen (Platzhalter)
  - Adressen: Neu-Modal + Edit-Modal (data-Attribute → JS pre-fill, kein AJAX nötig)
  - DSGVO: Consent-Log unveränderlich, Eintragen-Formular direkt im Tab
- shell_top.php: Kunden-Sidebar (Liste + Neuer Kunde) ergänzt

## Nächste Schritte (Priorität)

### Für die nächste Session vorgemerkt (Stand 2026-07-04, Session 24)
1. **Echter BFR-Hardware-Test** — Offline-Kasse ist jetzt funktional fertig (siehe Session 24 oben), nächster Schritt ist der reale Test mit Demo-Signaturkarte beim BFR-Hersteller.
2. **Lager-Verwaltungs-UI** — neue Lager anlegen/bearbeiten geht aktuell nur per SQL. Bewusst zurückgestellt bis die Offline-Kasse funktional brauchbar war (jetzt der Fall). Sollte ein Flag "für Offline-Kassen auswählbar" bekommen statt sich auf `lager.typ='messe'` zu verlassen.
3. **Nummernkreis-Verwaltung** — weder Kassenbon-Nummern (pro Kasse) noch Dokumenten-Nummern (`dokument_nummern`) sind irgendwo einsehbar/konfigurierbar. Ebenfalls bewusst zurückgestellt.
4. **Zentrale Chargen-Nachverfolgung** — artikelübergreifende Seite (Artikelsuche → Chargen-Dropdown → volle Bewegungshistorie), vermutlich bei `erp/public/lager/` neben dem bestehenden Chargen-Nachtrag. Siehe [[project_chargen_nachverfolgung]]. Die artikel-eigene Variante (Tab Lager in `artikel/detail.php`) ist bereits fertig (Session 24).
5. **Kassen-Identität (Online-Kasse)** — `bon.php`/`kassensturz.php` etc. haben `getKasse(1)` hart codiert, kein Mechanismus für "welche Kasse bin ich" bei einer zweiten Ladenkasse. Betrifft nicht die Offline-Kasse (deren Identität kommt über die Sync-URL). Passendes, bereits geplantes (aber ungebautes) Feature dafür existiert im Datenmodell: `arbeitsplaetze`-Tabelle mit Geräte-UUID-Token.

### Varianten-System UI ✅ komplett
1. ~~Achsen-Verwaltung~~ ✅
2. ~~Artikel: Achsen zuweisen~~ ✅
3. ~~VarKombi-Generator~~ ✅

### UI-Redesign: Shell + Components ✅ (2026-06-12/13)

**CSS-Dateien:**
- `public/css/variables.css` — CSS Custom Properties: Farben, Abstände, Layout-Maße
- `public/css/layout.css` — App-Shell CSS Grid + `.actionbar-sep`, `.actionbar-right`, `.sidebar-module-header`
- `public/css/components.css` — `.card`, `.btn`/`.btn-primary`/`.btn-secondary`/`.btn-danger`/`.btn-sm`, `.chip`/`.chip-aktiv`/`.chip-inaktiv`/`.chip-auslauf`, `.erp-table`, `.filter-bar`, `.erp-input`, `.erp-select`

**PHP Includes:**
- `public/includes/shell_top.php` — dynamisch: `$pageTitle`, `$activeModule`, `$actionBarContent`; Sidebar auto-generiert per `match($activeModule)`
- `public/includes/shell_bottom.php` — schließt Shell
- `public/img/logo.png` — Logo (aus D:\ERP\LOGO.png kopiert)

**Migriert:** `artikel/liste.php` ✅ — Shell + alle Component-Klassen, Action Bar, Chips

**Verwendung auf jeder Seite:**
```php
$pageTitle        = "Seitenname";
$activeModule     = "modulname";   // dashboard|artikel|lager|kunden|verkauf|versand|retouren|einkauf|buchhaltung|lieferanten
$actionBarContent = <<<HTML
<a href="..." class="btn btn-primary btn-sm">+ Neu</a>
<button class="btn btn-secondary btn-sm">Kopieren</button>
<div class="actionbar-sep"></div>
<div class="actionbar-right">
    <button class="btn btn-secondary btn-sm">Aktion ▼</button>
</div>
HTML;
require_once __DIR__ . '/../includes/shell_top.php';
// → Seiteninhalt (Cards, Tabellen etc.)
require_once __DIR__ . '/../includes/shell_bottom.php';
```

**Finetuning (später):**
- URL-Rewriting via `.htaccess` (mod_rewrite) — hübsche URLs statt vollständiger `.php`-Pfade
- Größen-Feinabstimmung Nav/Sidebar exakt auf Mockup-Maße

~~**Nächste Schritte UI:** Restliche Seiten auf Shell migrieren (detail.php, neu.php, bearbeiten.php, lager/, lieferanten/)~~ ✅ FERTIG (2026-06-19)

### Artikel-Modul: Noch offen
- **Merkmale-UI** — Formular zum Befüllen (Nadelstärke, Garngruppe, Maschenprobe) — spätestens mit Shop
- **Preistabellen-UI** — alle Kundengruppen + Staffelpreise (derzeit UI nur für Endkunde)
- **Bestellvorschläge** — beim Einkaufsmodul vollenden
- Seriennummern — geplant
- ~~Bilder-Upload~~ ✅ fertig
- ~~Qualitätslisten~~ ✅ fertig

### Neue Module (Reihenfolge)
4. ~~**Kundendatenbank**~~ ✅ FERTIG (2026-06-19)
5. ~~**Bestellwesen**~~ ✅ FERTIG (2026-06-23):
   - Migrations 055–059; 059: lieferzeit_text auf bestellung_positionen
   - public/bestellungen/: liste, neu, detail, bearbeiten (Header+Positionen+neu hinzufügen), aktualisieren, speichern, stornieren, rechnung_speichern
   - public/wareneingang/: index (EAN-Scan+Kacheln+Sammelliste), detail (Scan+✏Artikel-bearbeiten), speichern, abschliessen
   - Typeahead-Suche: ?lieferant_id=X&q= (neu.php) / ?alle=1&q= (bearbeiten.php neue Positionen)
   - **Session-Breadcrumb Pattern** (WE → Artikel → zurück):
     - Punkt 2: EAN nicht gefunden → artikel_vorbereiten.php → artikel/neu.php (EAN vorbelegt) → zurück
     - Szenario B: ✏ in WE-Detail → artikel_bearbeiten_vorbereiten.php → artikel/bearbeiten.php → zurück
   - **Retroaktive Bestellung** (Sammelliste): Artikel ohne offene Bestellung → Sammelliste → bestellung_aus_durchlauf.php (anlegen+sofort buchen)
   - **ArtikelRepository Bugfix**: Qualitätslisten `typ='ean'` → `typ='GTIN13'`
### Modulpflege ✅ FERTIG (2026-06-23)
- **JS auslagern**: 21 `.js`-Dateien aus 21 PHP-Seiten extrahiert — alle inline `<script>`-Blöcke raus
  - Muster: `<script>window.VAR = <?= $phpVar ?>;</script>` + `<script src="/mealana/js/xxx.js"></script>`
  - Alle JS-Dateien unter `erp/public/js/` — cachebar, übersichtlich
- **Bedienungsanleitung**: `public/bedienungsanleitung.php` mit TOC + Kapitel-Platzhaltern
  - Erreichbar via 📖 in der Top-Navigation (immer sichtbar)
  - Kapitel für alle Module + Fertig/Geplant-Badges — wird pro Modul weiter gefüllt

6. **Auftragsmodul/Verkauf** ✅ WEITGEHEND FERTIG (aktualisiert 2026-06-26):
   - Migrations 060–068, 076; AuftragRepository + AuftragService vollständig
   - public/auftraege/: liste, neu, detail, bearbeiten, speichern, aktualisieren, stornieren, status_ajax, artikel_ajax, kunden_ajax
   - Dokumente: Rechnung (PDF), Auftragsbestätigung, Lieferschein, Abholzettel, Gutschrift (Vollstorno + Teilgutschrift)
   - **Zahlung buchen** (Migration 076 — auftrag_zahlungen):
     - detail.php: Buchungsformular (Betrag vorausgefüllt, Datepicker, Notiz), Zahlungsverlauf-Box
     - Teilzahlung → Status 'teilbezahlt'; Vollzahlung → 'bezahlt'; Summe > Gesamt → Überbezahlt
     - liste.php: Chips Teilbezahlt/Überbezahlt + Filter für alle Zahlungsstatus inkl. Überbezahlt
   - **Mahnwesen-Cronjob** (erp/cron/mahnwesen.php): 14+Tage Erinnerungsmail, 30+Tage Storno+Mail
   - **Mail-System** ✅ VOLLSTÄNDIG (2026-06-28): basis_layout mit Logo+Social-Footer, persönliche Anrede in allen Templates, auftragsbestaetigung (ÜV-Warnung, Status-Nachricht, Bankverbindung), versandbestaetigung (Positionen, Tracking), zahlungseingang (NEU), Auto-Rechnung+Mail am Packplatz, Zahlungsmail nach Buchen
   - Migration 090: social_instagram/facebook/tiktok/youtube/pinterest/firma_web in system_einstellungen
   - Einstellungen/Firma: Karte "Online-Präsenz & Social Media"
   - DokumentService: holeOderErstelleRechnung() mit neu_erstellt-Flag
   - shell_bottom.php: Page-Loader Overlay global (zeigt bei Link/Form, Ausnahmen: _blank/Anker/data-no-loader/AJAX)
7. **Buchhaltung / Artikelgruppen** ✅ FERTIG (2026-07-01):
   - Migration 096: `artikel_gruppen` (11 Startwerte 4000–4900) + FK `artikel_gruppe_id` an `artikel` + `versandklassen`
   - `public/buchhaltung/artikel_gruppen.php` — CRUD mit Modal, Warnung getrennt Väter vs. Kinder
   - Versandklassen-CRUD in `versand/index.php` mit Artikelgruppen-Dropdown (unter PLC-Einstellungen)
   - `ArtikelRepository`: `artikel_gruppe_id` in insert/update/propagiereZuKindern/findById + Qualitätsfilter `keine_gruppe`
   - `ArtikelService::saveKind()` + `validiere()`: Pflichtfeld, VATER→KIND-Vererbung
   - Artikel-Formular (neu/bearbeiten): Dropdown; detail.php: 3er-Grid (Hersteller|Steuerklasse|Artikelgruppe) + Warnung bei leer
   - `KassenService`: Artikelgruppen-Umsatz in `sammleAbschlussDaten()` + `getPeriodeKennzahlen()`
   - Abschluss-Seiten (X/Z-Bon + Periodenabschluss): Tabelle "Umsatz nach Artikelgruppe" mit USt-Aufschlüsselung
   - Buchhaltung-Topnav-Link aktiviert (war zuvor `erp-nav-link-disabled`)
8. **Kasse** ✅ Phase 1+2 FERTIG (zuletzt 2026-06-29):
   - Migration 077: kassen, kassen_bons, kassen_bon_positionen, kassenbuch, offene_auswahl
   - public/kasse/: 16 Dateien — shell, index, bon, ajax_artikel, bon_speichern, bon_druck, kassenbuch, kassensturz, offene_auswahl, bon_journal, bon_stornieren
   - KassenService: erstelleBon, storniereBon, findArtikelByCode (FIFO-Charge), X-Bon/Z-Bon, Kassenbuch, Offene Auswahl
   - Features: EAN-Scan, Vater→Variante-Auswahl, Divers-Artikel (freier Preis), Rabatt, Bar+Rückgeld, Karte extern (SumUp/Bankomat), Gutschein, Kombi, 80mm Browser-Druck, Zählhilfe
   - **Abholbereit+bezahlt Flow ✅ (2026-06-29)**: exakt/retour/extra/mix; kein Bon wenn exakt, Retour-Bon mit Barauszahlung, Extra-Bon nur für Extras; Gutschein-Hook vorbereitet
   - **K1-Bon Laufkunde Bug ✅ (2026-06-29)**: kunden_snapshot immer vom Original-Auftrag kopieren
   - **Chargen-Dialog ✅ (2026-06-29)**: Overlay ov-charge — Charge wählen/nachzutragen/ohne; bon_speichern.php Rückbuchung liest Charge aus auftrag_positionen
   - **Namenssuche ✅ (2026-06-29)**: Artikel-Suche Modal, sucheArtikel() LIMIT PDO::PARAM_INT Fix
   - RKSV/BFR-BONit ✅ FERTIG (2026-07-02, siehe oben) — Phase 2 noch offen: Bon-Park, A4-Bon als Rechnung
   - Druckkonfiguration: 80mm Thermodrucker als Windows-Standarddrucker setzen; @page { size: 80mm auto }
8. **Packplatz/Picklisten** — Kommissionierung, Packliste
9. **Versandmodul** — Österr. Post/PLC fix eingebaut, erweiterbar: DHL/DPD/GLS/UPS. Paketschein, Tracking, Versandkosten. Verbunden mit Packplatz.
10. **Inventur/Umbuchung (MOBILE-FIRST)** — PWA, EAN-Scan via Kamera, Bestand + Reservierung prüfen, Zählliste, Abschluss mit LOG
11. **Shop-Anbindung** — REST API, Multi-Shop Hub-and-Spoke

### UI-Entscheidungen (festgelegt)
- **Kategorie-Auswahl im Artikel-Formular:** Modal (JS-Overlay), kein Tab/Redirect. Kategoriebaum + "Neue anlegen" + "Übernehmen". Kein Datenverlust.
- **Artikel-Detail:** Tab-Navigation (Stammdaten / Varianten+Kinder / Lager / Lieferanten / Bestellhistorie / Verkaufshistorie / Statistik / Dateien)
- **Statistik-Tab:** Topseller-Ranking aus Verkaufsanzahl + Umsatz — auch für Shop-Integration verwendbar
- **Barbara (Jackys Frau)** schaut bei UI-Entscheidungen mit — SVG-Mockup-Stufe nicht überspringen, bevor HTML gebaut wird

### Workflow
- Jedes neue Modul startet mit Referenz-Check: was machen große WAWIs, was brauchen wir extra

### Planned Modules (Future)
- Gutscheinmodul
- Import-Modul (JTL-WAWI CSV)
- Aktions-/Sonderpreismodul
- Bestellwesen (Lieferantenbestellungen)
- Inventur-Workflow
- Retouren
- Online shop integration via REST API
- Import module (JTL-WAWI, CSV)
- Promo/discount engine

## Commit Convention

```
feat:      New feature
fix:       Bug fix
chore:     Setup, configuration, dependencies
docs:      Documentation
refactor:  Refactoring without functional change
```

Example:
```
feat: Add bestand column to article list view
fix: Correct netto_vk calculation for 10% tax rate
docs: Update db schema in CLAUDE.md
```

## Business Context – MeaLana Specifics

- **Yarn (Garn)** is the core product – charge tracking essential for color consistency
- **Master/Child**: Article → many color variants
- **Multiple warehouses**: Retail store + trade show + storage
- **Base price (Grundpreis)**: Legally required in Austria (€/100g for yarn)
- **Tax rates**: Austria – 20% standard, 10% reduced
- **Future**: RKSV (Austrian cash register law), MOSS (EU VAT on online sales)
- **Multi-shop**: mealana.at, sockenwolle-online.at, bio-wolle.at, verschlusssache.at

## Tips for Contributors

1. **Before writing code**: Read the existing Service/Repository in the same module
2. **Validation always in Service**: Repository has no business logic
3. **Form repopulation**: Always use Session['fehler'] and Session['formdata']
4. **DB queries**: Every method should be easy to understand
5. **Charge is critical**: Yarn batches matter! Always preserve charge_status.
6. **Test locally**: XAMPP, http://localhost/mealana/, check browser console
7. **Soft delete**: Use aktiv = 0, never hard delete
8. **Error messages**: Helpful, customer-facing language (in German)

## References

- GitHub: https://github.com/Kindra2804/mealana-erp
- Requirements: docs/anforderungen.md
- Context: docs/kontext.md
