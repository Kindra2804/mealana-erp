# CLAUDE.md

This file provides guidance to Claude Code (claude.ai/code) when working with code in this repository.

## Projekt

Ein modulares Warenwirtschaftssystem für MeaLana, eine Wollboutique in Österreich.

**Entwickler**: Indranet (Karl)  
**GitHub**: https://github.com/Kindra2804/mealana-erp

### Lernziel
Karl ist Anfänger (frisch aus zertifiziertem Kurs).  
Claude ist Trainer – Konzepte erklären, selbst schreiben lassen, Fehler mit Erklärungen korrigieren. **Nicht einfach Code liefern!**

## Stack

- **PHP** 8+, OOP, kein Framework (bewusst!)
- **MySQL 8** (utf8mb4, kein MariaDB)
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
│   │   ├── mealana_erp.sql   ← Schema (all 15 tables)
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

## Data Model: 22 Tables

### Core Domain
```sql
hersteller          → Manufacturers (supplier records)
steuerklassen       → Tax classes (20% normal, 10% reduced for AT)
```

### Articles & Variants (Vater/Kind Pattern)
```sql
artikel             → Master article record
                      - ENUM: GARN, NADEL, METERWARE, DOWNLOAD, SET, STANDARD
                      - Grundpreis (base price per unit) calculation support

artikel_varianten   → Color variants (each yarn/meterware has multiple colors)
                      - farbe_hex, farbe_name (e.g. "Rot", "#FF0000")
                      - bild_url (swatch image)
                      - brutto_vk (variant-specific price, may differ from master)

artikel_codes       → GTIN/ISBN/internal codes per article
```

### Pricing
```sql
kundengruppen       → Customer segments: Endkunde, Händler, Vertriebspartner
artikel_preise      → Price matrix per (article, customer_group, date_range)
```

### Warehouse & Inventory
```sql
lager               → Warehouses: Ladengeschäft, Messe, Extern, Lager

lagerbestand        → Stock levels per (variant, warehouse)
                      - UNIQUE(artikel_varianten_id, lager_id)
                      - charge tracking for yarn (Farbkonsistenz critical!)
                      - charge_status: erfasst|unbekannt|nachzutragen

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
                      - letzte_aktivitaet: auto-updated on activity
                      - Foundation for concurrent session limits (future: system setting)

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
    <option value="GARN" <?= selected('artikeltyp', 'GARN', $formdata) ?>>Garn</option>
</select>

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

## What's Implemented

### Artikel Module (CRUD Complete)
- List with search + active/inactive filter
- Create with form repopulation on error
- Edit
- Soft delete (aktiv = 0)
- Price storage (artikel_preise table, standard customer group)
- Detail view with variants

### Varianten Module (CRUD Complete)
- Create variant with color picker (farbe_hex)
- Edit variant
- Display in article detail (colored circles/swatches)

### Lager Module (Functional)
- Goods receipt with EAN barcode scan support
- Variant search API (variante_suche.php returns JSON)
- Stock UPSERT (create or increase)
- Movement audit log (lager_bewegungen)
- Charge tracking with status

### Navigation
- nav.php included on all pages
- Links to all modules

### Auth & RBAC (Datenbank fertig, PHP folgt)
- 7 Tabellen migriert: benutzer, rollen, berechtigungen, rollen_berechtigungen, benutzer_rollen, aktivitaeten, sessions
- 3 Rollen angelegt: superadmin, admin, mitarbeiter
- 47 Berechtigungen in 12 Modulen definiert
- Erster Admin-Benutzer in DB (username: admin)
- Migrations: `004_benutzer_rollen_log.sql`, `005_seed_rollen_berechtigungen.sql`

## What's Missing (Priority)

### Nächste Session (Auth PHP-Klassen)
1. **`src/core/Auth.php`** – login(), logout(), check(), kann('modul.aktion')
2. **`src/core/Logger.php`** – log($aktion, $referenzTabelle, $referenzId, $details)
3. **`public/login.php`** + **`public/logout.php`**
4. **`public/includes/auth_check.php`** – 1 Zeile auf jeder geschützten Seite
5. **ArtikelService + LagerService nachrüsten** – Logger-Aufrufe einbauen

### Danach: Artikelliste
6. **Bestand in Artikelliste** – LEFT JOIN SUM(lagerbestand) als extra Spalte

### Medium-term
7. **Warehouse overview** – View which articles stored where with quantities
8. **Charge post-entry** – Workflow for charge_status = 'nachzutragen'
9. **Supplier frontend** – CRUD for Lieferanten (backend exists)
10. **Attribute frontend** – CRUD for Merkmale (backend exists)
11. **Price table modal** – "+" button in artikel form to manage kundengruppen prices

### Planned Modules (Future)
- Customer database
- Point of sale with RKSV/Fiskaly
- Voucher system
- Packing station
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
