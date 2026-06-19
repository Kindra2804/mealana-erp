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
lieferanten         → Supplier master data
artikel_lieferanten → Supplier-specific SKU, cost (netto_ek), MOQ, lead times
lieferanten_vertreter → Contact persons per supplier
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

## What's Implemented (Stand 2026-06-19, Session 3)

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

### Vater-Kind Vererbung (vollständig — 2026-06-19)
- **erstelleKombinationen()** erbt alle ~25 Felder vom Vater (vorher nur 4)
- **kopiereVaterRelationenZuKindern()** kopiert Kategorien, Merkmale, Lieferanten, Preise zu neu erstellten Kindern
- **propagiereZuKindern()** — ein UPDATE propagiert alle gemeinsamen Felder beim Vater-Update
- **syncKategorienZuKindern()** — Kategorien werden bei saveKategorien() auf alle Kinder synchronisiert
- Nicht propagiert: artikelnummer, name, url_slug, aktiv, ist_auslaufartikel (eigene Logik), zustand

### Varianten-System (vollständig — DB + UI + Generator fertig, Stand 2026-06-19)
- Migrations 022–041 ausgeführt: Achsen, Werte, Kombinationen, abhängige Achsen, Gruppenachse-Flag
- Kind-Artikel (Varianten) werden als artikel-Einträge mit vaterartikel_id gespeichert
- **Achsen-Verwaltung** (public/achsen/) — volles CRUD ✅
- **Achsen zuweisen** (public/artikel/achsen_zuweisen.php + achsen_speichern.php) ✅
  - Baumstruktur: Gruppenachse + eingerückte Sub-Achsen
  - Chip-Input: Werte hinzufügen, ◀▶ sortieren, ↔ zwischen Achsen verschieben
  - Granulare Sperrung: 🔒-Chip für in-use Werte (in Kombinationen verwendet), freie editierbar
  - Neue Werte hinzufügen + freie Werte löschen bleibt möglich auch wenn Varianten existieren
- **VarKombi-Generator** (in detail.php, Tab "Varianten") ✅
  - Achsen-Hierarchie-bewusst: Sub-Achsen-Werte immer UNION (nie Kreuzprodukt) in eine Dimension
  - Sub-Achsen-Name als Suffix: "gelb (02)" von UNI → "gelb (02) UNI"
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
5. **Bestellwesen** ← NÄCHSTE SESSION — Lieferantenbestellungen: wann/von wem/was/EK-Preis/Status. Wareneingang referenziert Bestellung.
6. **Kasse** — RKSV/Fiskaly, inkl. Duplikat-EAN-Dialog + Seriennummer-Zuweisung
7. **Packplatz/Picklisten** — Kommissionierung, Packliste
8. **Versandmodul** — Österr. Post/PLC fix eingebaut, erweiterbar: DHL/DPD/GLS/UPS. Paketschein, Tracking, Versandkosten. Verbunden mit Packplatz.
9. **Inventur/Umbuchung (MOBILE-FIRST)** — PWA, EAN-Scan via Kamera, Bestand + Reservierung prüfen, Zählliste, Abschluss mit LOG
10. **Shop-Anbindung** — REST API, Multi-Shop Hub-and-Spoke

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
