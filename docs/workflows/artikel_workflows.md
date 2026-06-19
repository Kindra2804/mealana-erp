# Artikel-Modul: Workflows

> **Zielgruppe:** Entwickler  
> **Enthält:** Seitenpfade, Validierungen, DB-Tabellen, Seiteneffekte, Fehlerpfade  
> **Handbuch (Frontend-only):** siehe `../handbuch/artikel_handbuch.md`

---

## Legende

| Symbol | Bedeutung |
|--------|-----------|
| Abgerundete Box | Start / Ende (Seite oder Redirect) |
| Rechteck | Verarbeitungsschritt (PHP, Service, Repository) |
| Raute | Entscheidung / Verzweigung |
| `DB:` | Betroffene Datenbanktabelle(n) |
| 🔴 | Fehler-/Abbruchpfad |
| 🟢 | Erfolgspfad |

---

## 1. Artikel anlegen (Standard)

**Seiten:** `artikel/neu.php` → `artikel/speichern.php` → `artikel/detail.php`  
**Service:** `ArtikelService::save()`  
**Repository:** `ArtikelRepository::insert()`, `insertPreis()`, `insertCode()`

```mermaid
flowchart TD
    START([User öffnet\nneu.php])
    FORM[Formular ausfüllen\n─────────────────\nArtikelnummer · Name\nArtikeltyp · Hersteller\nSteuerklasse · Einheit\nBrutto-VK · EAN\nKategorien wählen via Modal]

    POST[POST → speichern.php\nartikelData = array_intersect_key POST\nLeere Strings → NULL]

    VALID{"ArtikelService\n::validiere()"}

    ERR[🔴 Session: fehler + formdata\nRedirect → neu.php]
    REPOP[Formular neu befüllen\nvia old() + selected()]

    INS_ART[INSERT artikel\nDB: artikel\nalle Stammdaten-Felder]

    CHK_PREIS{"Brutto-VK\nangegeben?"}
    INS_PREIS[INSERT artikel_preise\nDB: artikel_preise\nkundengruppen_id = Standard-KG\nbrutto_vk · netto_vk]

    CHK_EAN{"EAN\nangegeben?"}
    INS_EAN[INSERT artikel_codes\nDB: artikel_codes\ntyp = GTIN13]

    CHK_KAT{"Kategorien\ngewählt?"}
    INS_KAT[INSERT artikel_kategorien\nDB: artikel_kategorien\nartikel_id + kategorie_id je Eintrag]

    LOG[Logger::log\naktion: artikel.anlegen\nDB: aktivitaeten]

    END([🟢 Redirect → detail.php?id=X\nSession: erfolg])

    START --> FORM --> POST --> VALID
    VALID -->|Fehler: Artikelnummer leer\noder bereits vergeben\noder Name leer| ERR
    ERR --> REPOP --> FORM
    VALID -->|OK| INS_ART
    INS_ART --> CHK_PREIS
    CHK_PREIS -->|Ja| INS_PREIS --> CHK_EAN
    CHK_PREIS -->|Nein| CHK_EAN
    CHK_EAN -->|Ja| INS_EAN --> CHK_KAT
    CHK_EAN -->|Nein| CHK_KAT
    CHK_KAT -->|Ja| INS_KAT --> LOG
    CHK_KAT -->|Nein| LOG
    LOG --> END
```

### Validierungsregeln

| Feld | Regel |
|------|-------|
| `artikelnummer` | Pflichtfeld + UNIQUE (prüft DB, excludiert eigene ID bei Update) |
| `name` | Pflichtfeld |
| `steuerklasse_id` | Pflichtfeld (Dropdown, immer vorbelegt) |
| `artikeltyp` | Pflichtfeld (aus `artikel_typen` Tabelle, kein ENUM) |
| `einheit_id` | Pflichtfeld |

### Betroffene DB-Tabellen

| Tabelle | Operation | Bedingung |
|---------|-----------|-----------|
| `artikel` | INSERT | immer |
| `artikel_preise` | INSERT | wenn `brutto_vk` angegeben |
| `artikel_codes` | INSERT | wenn `ean_gtin13` angegeben |
| `artikel_kategorien` | INSERT (mehrere) | wenn Kategorien gewählt |
| `aktivitaeten` | INSERT | immer (Logger) |

---

## 2. Varianten erstellen (VarKombi — zweistufig)

Der Varianten-Workflow besteht aus zwei getrennten Aktionen:
- **Stufe 1:** Achsen + Werte zuweisen (definiert die Dimensionen z.B. Farbe, Stärke)
- **Stufe 2:** Aus den Achswerten Kombinationen generieren (erstellt die Kind-Artikel)

### Voraussetzungen

- Vater-Artikel existiert (`artikel.ist_vater = 0` → wird beim Achsen-Zuweisen automatisch nicht geprüft, aber konzeptuell ein Vater-Artikel)
- Mindestens eine globale Achse in `varianten_achsen` vorhanden

---

### Stufe 1: Achsen + Werte zuweisen

**Seiten:** `artikel/achsen_zuweisen.php` → `artikel/achsen_speichern.php`  
**Service:** `VariantenService::speichereAchsenUndWerte()`  
**Repository:** `VariantenRepository`

```mermaid
flowchart TD
    START([User: detail.php\nButton: Achsen zuweisen])
    PAGE[achsen_zuweisen.php\n─────────────────\nAchsen aus globaler Liste wählen\nWerte als Chips eingeben\n◀▶ Sortierung · ↔ Verschieben zwischen Achsen]

    POST[POST → achsen_speichern.php\nachsen_ids[] · werte[] je Achse]

    INUSE{"Werte bereits in\nKombinationen verwendet?\nDB: varianten_kombination_werte"}

    LOCK[In-use Werte bleiben erhalten\n🔒 Chip — nicht löschbar]
    FREE[Nicht-in-use Werte:\nlöschen + neu schreiben]

    DEL_WERTE[DELETE varianten_achse_werte\nWO nicht in-use\nDB: varianten_achse_werte]

    CHK_ACHSE{"Achse nicht mehr\nin achsen_ids UND\nnicht geschützt?"}
    DEL_ACHSE[DELETE artikel_achsen\nDB: artikel_achsen]

    INS_ACHSE[INSERT artikel_achsen\nnur fehlende\nDB: artikel_achsen\nartikel_id + achse_id]

    INS_WERTE[INSERT varianten_achse_werte\nje Achse: wert, aufpreis,\nwert_zusatz, sortierung\nDB: varianten_achse_werte]

    LOG[Logger::log\naktion: achsenUndWerte.speichern\nDB: aktivitaeten]

    END([🟢 Redirect → achsen_zuweisen.php\nSession: erfolg])

    START --> PAGE --> POST --> INUSE
    INUSE -->|Ja: In-use vorhanden| LOCK
    INUSE -->|Nein| FREE
    LOCK --> DEL_WERTE
    FREE --> DEL_WERTE
    DEL_WERTE --> CHK_ACHSE
    CHK_ACHSE -->|Ja| DEL_ACHSE --> INS_ACHSE
    CHK_ACHSE -->|Nein| INS_ACHSE
    INS_ACHSE --> INS_WERTE --> LOG --> END
```

### Stufe 2: VarKombi-Generator (Kind-Artikel erstellen)

**Seiten:** `artikel/detail.php` (Tab Varianten) → `artikel/varkombi_erstellen.php`  
**Service:** `VariantenService::erstelleKombinationen()` + `ArtikelService::kopiereVaterRelationenZuKindern()`

```mermaid
flowchart TD
    START([User: detail.php\nTab: Varianten])

    JS[JS berechnet Kreuzprodukt\naller Achswert-Kombinationen\n─────────────────\nGruppenachse: Sub-Achsen UNION\nnicht Kreuzprodukt\nBereits bestehende Kombis: grau]

    SELECT[User wählt Kombinationen\nund setzt Artikelnummern + Namen]

    POST[POST → varkombi_erstellen.php\nkombis[] mit key, artikelnummer, name\nhat_eigenen_lagerstand]

    VATER[ArtikelService::findById\nVater komplett laden\nDB: artikel + artikel_typen + preise]

    LOOP[["Für jede gewählte Kombination:"]]

    INS_KIND["INSERT artikel\nDB: artikel\n─────────────────\nVon Vater geerbt:\nhersteller_id · steuerklasse_id · artikeltyp_id\nbeschreibung(en) · meta_titel · meta_description\neinheit_id · inhalt · gewicht · maße\nherkunftsland · taric_code\ngrundpreis · charge_pflicht · ueberverkauf_erlaubt\n─────────────────\nKind-spezifisch:\nartikelnummer · name · vaterartikel_id\nurl_slug = NULL (eigener Slug später)"]

    INS_KOMBI["INSERT varianten_kombination_werte\nDB: varianten_kombination_werte\nkombination_id = KindId\nje Achswert eine Zeile"]

    COPY[ArtikelService\n::kopiereVaterRelationenZuKindern]

    CPY_KAT[copyKategorien\nDB: artikel_kategorien\nINSERT SELECT vom Vater]
    CPY_MERK[copyMerkmale\nDB: artikel_merkmale\nINSERT SELECT vom Vater]
    CPY_LIEF[copyLieferanten\nDB: artikel_lieferanten\nINSERT SELECT vom Vater]
    CPY_PREIS[copyPreise\nDB: artikel_preise\nINSERT SELECT alle KG vom Vater]

    LOG[Logger::log\naktion: varkombi.erstellen\nDB: aktivitaeten]

    END([🟢 Redirect → detail.php?tab=varianten])

    START --> JS --> SELECT --> POST --> VATER --> LOOP
    LOOP --> INS_KIND --> INS_KOMBI --> LOOP
    LOOP -->|Alle Kombis erstellt| COPY
    COPY --> CPY_KAT --> CPY_MERK --> CPY_LIEF --> CPY_PREIS --> LOG --> END
```

### Was Kinder vom Vater erben (beim Erstellen)

| Bereich | Felder |
|---------|--------|
| Stamm | `hersteller_id`, `steuerklasse_id`, `artikeltyp_id`, `einheit_id` |
| Beschreibungen | `kurzbeschreibung`, `beschreibung`, `technische_details`, `beschreibung_intern` |
| SEO | `meta_titel`, `meta_description` (`url_slug` = NULL) |
| Logistik | `inhalt_menge`, `inhalt_einheit`, `gewicht_artikel`, `gewicht_versand`, `laenge`, `breite`, `hoehe` |
| Zoll | `herkunftsland`, `taric_code` |
| Grundpreis | `grundpreis_bezugsmenge`, `grundpreis_anzeigen` |
| Verhalten | `charge_pflicht`, `ueberverkauf_erlaubt`, `ist_auslaufartikel` |
| Relationen | Kategorien, Merkmale, Lieferanten, Preise (alle KG) |

### Was Kinder NICHT erben

| Feld | Grund |
|------|-------|
| `artikelnummer` | Kind hat eigene Nummer |
| `name` | Kind hat eigenen Variantennamen |
| `url_slug` | Würde Shop-Duplikat-URLs erzeugen |
| `aktiv` | Startet immer mit `aktiv = 1` |
| `zustand` | Startet mit `neu` |

### Betroffene DB-Tabellen (Stufe 2)

| Tabelle | Operation | Menge |
|---------|-----------|-------|
| `artikel` | INSERT | 1 je Kombination |
| `varianten_kombination_werte` | INSERT | 1 je Achswert je Kombination |
| `artikel_kategorien` | INSERT SELECT | Vater-Kategorien × Kind-Anzahl |
| `artikel_merkmale` | INSERT SELECT | Vater-Merkmale × Kind-Anzahl |
| `artikel_lieferanten` | INSERT SELECT | Vater-Lieferanten × Kind-Anzahl |
| `artikel_preise` | INSERT SELECT | Vater-Preiszeilen × Kind-Anzahl |
| `aktivitaeten` | INSERT | 1 (Logger) |
