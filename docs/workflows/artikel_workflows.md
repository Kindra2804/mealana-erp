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
    START(["User öffnet neu.php"])
    FORM["Formular ausfüllen<br/>Artikelnummer · Name<br/>Artikeltyp · Hersteller<br/>Steuerklasse · Einheit<br/>Brutto-VK · EAN<br/>Kategorien via Modal"]
    POST["POST → speichern.php<br/>artikelData = array_intersect_key POST<br/>Leere Strings → NULL"]
    VALID{"ArtikelService<br/>::validiere()"}
    ERR["🔴 Session: fehler + formdata<br/>Redirect → neu.php"]
    REPOP["Formular neu befüllen<br/>via old() und selected()"]
    INS_ART["INSERT artikel<br/>DB: artikel"]
    CHK_PREIS{"Brutto-VK<br/>angegeben?"}
    INS_PREIS["INSERT artikel_preise<br/>DB: artikel_preise<br/>kundengruppen_id = Standard-KG"]
    CHK_EAN{"EAN<br/>angegeben?"}
    INS_EAN["INSERT artikel_codes<br/>DB: artikel_codes<br/>typ = GTIN13"]
    CHK_KAT{"Kategorien<br/>gewählt?"}
    INS_KAT["INSERT artikel_kategorien<br/>DB: artikel_kategorien<br/>je Kategorie eine Zeile"]
    LOG["Logger::log<br/>aktion: artikel.anlegen<br/>DB: aktivitaeten"]
    END(["🟢 Redirect → detail.php?id=X<br/>Session: erfolg"])

    START --> FORM --> POST --> VALID
    VALID -->|"Fehler: Artikelnummer leer<br/>oder vergeben · Name leer"| ERR
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

- Vater-Artikel existiert
- Mindestens eine globale Achse in `varianten_achsen` vorhanden

---

### Stufe 1: Achsen + Werte zuweisen

**Seiten:** `artikel/achsen_zuweisen.php` → `artikel/achsen_speichern.php`  
**Service:** `VariantenService::speichereAchsenUndWerte()`

```mermaid
flowchart TD
    START(["User: detail.php<br/>Button: Achsen zuweisen"])
    PAGE["achsen_zuweisen.php<br/>Achsen aus globaler Liste wählen<br/>Werte als Chips eingeben<br/>Sortierung und Verschieben möglich"]
    POST["POST → achsen_speichern.php<br/>achsen_ids[] · werte[] je Achse"]
    INUSE{"Werte bereits in<br/>Kombinationen verwendet?<br/>DB: varianten_kombination_werte"}
    LOCK["In-use Werte bleiben erhalten<br/>Anzeige als gesperrter Chip"]
    FREE["Nicht-in-use Werte:<br/>löschen + neu schreiben"]
    DEL_WERTE["DELETE varianten_achse_werte<br/>nur nicht-in-use<br/>DB: varianten_achse_werte"]
    CHK_ACHSE{"Achse entfernt UND<br/>nicht geschützt?"}
    DEL_ACHSE["DELETE artikel_achsen<br/>DB: artikel_achsen"]
    INS_ACHSE["INSERT artikel_achsen<br/>nur fehlende Achsen<br/>DB: artikel_achsen"]
    INS_WERTE["INSERT varianten_achse_werte<br/>wert · aufpreis · sortierung<br/>DB: varianten_achse_werte"]
    LOG["Logger::log<br/>aktion: achsenUndWerte.speichern<br/>DB: aktivitaeten"]
    END(["🟢 Redirect → achsen_zuweisen.php<br/>Session: erfolg"])

    START --> PAGE --> POST --> INUSE
    INUSE -->|"In-use vorhanden"| LOCK --> DEL_WERTE
    INUSE -->|"Keine in-use"| FREE --> DEL_WERTE
    DEL_WERTE --> CHK_ACHSE
    CHK_ACHSE -->|Ja| DEL_ACHSE --> INS_ACHSE
    CHK_ACHSE -->|Nein| INS_ACHSE
    INS_ACHSE --> INS_WERTE --> LOG --> END
```

---

### Stufe 2: VarKombi-Generator (Kind-Artikel erstellen)

**Seiten:** `artikel/detail.php` Tab Varianten → `artikel/varkombi_erstellen.php`  
**Service:** `VariantenService::erstelleKombinationen()` + `ArtikelService::kopiereVaterRelationenZuKindern()`

```mermaid
flowchart TD
    START(["User: detail.php<br/>Tab: Varianten"])
    JS["JS berechnet Kreuzprodukt aller Achswerte<br/>Gruppenachse: Sub-Achsen als UNION<br/>Bestehende Kombis werden grau markiert"]
    SELECT["User wählt Kombinationen<br/>und vergibt Artikelnummern + Namen"]
    POST["POST → varkombi_erstellen.php<br/>kombis[] mit key · artikelnummer · name<br/>hat_eigenen_lagerstand"]
    VATER["ArtikelService::findById<br/>Vater komplett laden<br/>DB: artikel + preise"]
    LOOP[["Für jede gewählte Kombination:"]]
    INS_KIND["INSERT artikel<br/>DB: artikel<br/>vaterartikel_id = VaterId<br/>Alle Stammdaten vom Vater geerbt<br/>artikelnummer + name = Kind-spezifisch<br/>url_slug = NULL"]
    INS_KOMBI["INSERT varianten_kombination_werte<br/>DB: varianten_kombination_werte<br/>kombination_id = KindId<br/>je Achswert eine Zeile"]
    COPY["ArtikelService<br/>::kopiereVaterRelationenZuKindern()"]
    CPY_KAT["copyKategorien<br/>DB: artikel_kategorien"]
    CPY_MERK["copyMerkmale<br/>DB: artikel_merkmale"]
    CPY_LIEF["copyLieferanten<br/>DB: artikel_lieferanten"]
    CPY_PREIS["copyPreise<br/>DB: artikel_preise<br/>alle Kundengruppen"]
    LOG["Logger::log<br/>aktion: varkombi.erstellen<br/>DB: aktivitaeten"]
    END(["🟢 Redirect → detail.php?tab=varianten"])

    START --> JS --> SELECT --> POST --> VATER --> LOOP
    LOOP --> INS_KIND --> INS_KOMBI --> LOOP
    LOOP -->|"Alle Kombis erstellt"| COPY
    COPY --> CPY_KAT --> CPY_MERK --> CPY_LIEF --> CPY_PREIS --> LOG --> END
```

### Was Kinder vom Vater erben (beim Erstellen)

| Bereich | Felder |
|---------|--------|
| Stamm | `hersteller_id`, `steuerklasse_id`, `artikeltyp_id`, `einheit_id` |
| Beschreibungen | `kurzbeschreibung`, `beschreibung`, `technische_details`, `beschreibung_intern` |
| SEO | `meta_titel`, `meta_description` — `url_slug` = NULL |
| Logistik | `inhalt_menge`, `inhalt_einheit`, `gewicht_artikel`, `gewicht_versand`, `laenge`, `breite`, `hoehe` |
| Zoll | `herkunftsland`, `taric_code` |
| Grundpreis | `grundpreis_bezugsmenge`, `grundpreis_anzeigen` |
| Verhalten | `charge_pflicht`, `ueberverkauf_erlaubt`, `ist_auslaufartikel` |
| Relationen | Kategorien · Merkmale · Lieferanten · Preise alle KG |

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
