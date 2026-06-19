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

## Inhaltsverzeichnis

1. [Artikel anlegen](#1-artikel-anlegen-standard)
2. [Artikel bearbeiten](#2-artikel-bearbeiten)
3. [Artikel kopieren](#3-artikel-kopieren)
4. [Artikel löschen und reaktivieren](#4-artikel-löschen-und-reaktivieren)
5. [Varianten erstellen — Stufe 1: Achsen zuweisen](#5-varianten-erstellen--stufe-1-achsen--werte-zuweisen)
6. [Varianten erstellen — Stufe 2: VarKombi-Generator](#6-varianten-erstellen--stufe-2-varkombi-generator)
7. [Kind-Artikel bearbeiten](#7-kind-artikel-bearbeiten)
8. [Kategorien zuweisen (Modal)](#8-kategorien-zuweisen-modal)
9. [Kategorie-Baum verwalten](#9-kategorie-baum-verwalten)
10. [Preis-Workflows](#10-preis-workflows)
11. [Status-Workflows (Auslauf / Aktiv)](#11-status-workflows)
12. [SEO-Daten speichern](#12-seo-daten-speichern)

---

## 1. Artikel anlegen (Standard)

**Seiten:** `artikel/neu.php` → `artikel/speichern.php` → `artikel/detail.php`  
**Service:** `ArtikelService::save()`

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
    INS_EAN["INSERT artikel_codes<br/>DB: artikel_codes · typ = GTIN13"]
    CHK_KAT{"Kategorien<br/>gewählt?"}
    INS_KAT["INSERT artikel_kategorien<br/>DB: artikel_kategorien"]
    LOG["Logger::log · artikel.anlegen<br/>DB: aktivitaeten"]
    END(["🟢 Redirect → detail.php?id=X"])

    START --> FORM --> POST --> VALID
    VALID -->|"Artikelnummer leer/vergeben<br/>oder Name leer"| ERR --> REPOP --> FORM
    VALID -->|OK| INS_ART --> CHK_PREIS
    CHK_PREIS -->|Ja| INS_PREIS --> CHK_EAN
    CHK_PREIS -->|Nein| CHK_EAN
    CHK_EAN -->|Ja| INS_EAN --> CHK_KAT
    CHK_EAN -->|Nein| CHK_KAT
    CHK_KAT -->|Ja| INS_KAT --> LOG
    CHK_KAT -->|Nein| LOG --> END
```

### Validierungsregeln

| Feld | Regel |
|------|-------|
| `artikelnummer` | Pflichtfeld + UNIQUE (DB-Check) |
| `name` | Pflichtfeld |
| `steuerklasse_id` | Pflichtfeld |
| `artikeltyp` | Pflichtfeld (aus `artikel_typen`, kein ENUM) |
| `einheit_id` | Pflichtfeld |

---

## 2. Artikel bearbeiten

**Seiten:** `artikel/bearbeiten.php` → `artikel/aktualisieren.php` → `artikel/detail.php`  
**Service:** `ArtikelService::update()` + `saveKategorien()`

```mermaid
flowchart TD
    START(["User öffnet bearbeiten.php?id=X"])
    LOAD["Artikel laden via findById<br/>Formdata aus Session ODER DB<br/>Kategorien vorbelegen via getKategorienFuerArtikel"]
    FORM["Formular bearbeiten<br/>Alle Stammdaten-Felder<br/>Kategorien via Modal änderbar"]
    POST["POST → aktualisieren.php<br/>array_intersect_key für erlaubte Felder<br/>Leere Strings → NULL"]
    VALID{"ArtikelService<br/>::validiere()"}
    ERR["🔴 Session: fehler + formdata<br/>Redirect → detail.php"]
    UPDATE["UPDATE artikel<br/>DB: artikel<br/>alle Stammdaten-Felder"]
    PROP["propagiereZuKindern()<br/>UPDATE alle Kinder mit 22 gemeinsamen Feldern<br/>DB: artikel WHERE vaterartikel_id = X"]
    AUSLAUF["propagateAuslaufZuKindern()<br/>ist_auslaufartikel an Kinder<br/>DB: artikel WHERE vaterartikel_id = X"]
    UPD_EAN["DELETE alte GTIN13<br/>INSERT neue falls angegeben<br/>DB: artikel_codes"]
    UPD_PREIS["updatePreis()<br/>UPSERT Standard-KG Preis<br/>DB: artikel_preise"]
    KATS["saveKategorien()<br/>DELETE + INSERT Vater-Kategorien<br/>DB: artikel_kategorien"]
    SYNC_KAT["syncKategorienZuKindern()<br/>DELETE + INSERT Kinder-Kategorien<br/>DB: artikel_kategorien"]
    LOG["Logger::log · artikel.bearbeiten<br/>DB: aktivitaeten"]
    END(["🟢 Redirect → detail.php?id=X"])

    START --> LOAD --> FORM --> POST --> VALID
    VALID -->|Fehler| ERR
    VALID -->|OK| UPDATE --> PROP --> AUSLAUF --> UPD_EAN --> UPD_PREIS --> KATS --> SYNC_KAT --> LOG --> END
```

### Was bei Bearbeiten NICHT propagiert wird

| Feld | Grund |
|------|-------|
| `artikelnummer` | Kind hat eigene Nummer |
| `name` | Kind hat eigenen Variantennamen |
| `url_slug` | Kind hat eigenen Shop-Slug |
| `aktiv` | Eigene Logik via deactivate/reactivateKinder |
| `ist_auslaufartikel` | Eigene Logik via propagateAuslaufZuKindern |

---

## 3. Artikel kopieren

**Seiten:** `artikel/kopieren.php` → `artikel/kopieren_speichern.php` → `artikel/detail.php`  
**Service:** `ArtikelService::kopiere()`

```mermaid
flowchart TD
    START(["User: detail.php<br/>Button: Artikel kopieren"])
    FORM["kopieren.php<br/>Neue Artikelnummer + Name eingeben<br/>Checkboxen: Preise · Kategorien<br/>Merkmale · Lieferanten · Überverkauf"]
    POST["POST → kopieren_speichern.php"]
    CHK_NR{"Artikelnummer<br/>bereits vergeben?"}
    ERR["🔴 Session: fehler<br/>Redirect → kopieren.php"]
    INS["INSERT artikel<br/>DB: artikel<br/>alle Felder vom Original<br/>aktiv = 0 · url_slug = NULL<br/>neue Artikelnummer + Name"]
    CHK_P{"Preise<br/>kopieren?"}
    CPY_P["copyPreise()<br/>INSERT SELECT alle KG<br/>DB: artikel_preise"]
    CHK_K{"Kategorien<br/>kopieren?"}
    CPY_K["copyKategorien()<br/>INSERT SELECT<br/>DB: artikel_kategorien"]
    CHK_M{"Merkmale<br/>kopieren?"}
    CPY_M["copyMerkmale()<br/>INSERT SELECT<br/>DB: artikel_merkmale"]
    CHK_L{"Lieferanten<br/>kopieren?"}
    CPY_L["copyLieferanten()<br/>INSERT SELECT<br/>DB: artikel_lieferanten"]
    END(["🟢 Redirect → detail.php?id=NEU<br/>Artikel inaktiv — muss aktiviert werden"])

    START --> FORM --> POST --> CHK_NR
    CHK_NR -->|Ja| ERR
    CHK_NR -->|Nein| INS --> CHK_P
    CHK_P -->|Ja| CPY_P --> CHK_K
    CHK_P -->|Nein| CHK_K
    CHK_K -->|Ja| CPY_K --> CHK_M
    CHK_K -->|Nein| CHK_M
    CHK_M -->|Ja| CPY_M --> CHK_L
    CHK_M -->|Nein| CHK_L
    CHK_L -->|Ja| CPY_L --> END
    CHK_L -->|Nein| END
```

> **Hinweis:** Kopierter Artikel startet immer mit `aktiv = 0`. Varianten/Kinder werden nicht mitkopiert.

---

## 4. Artikel löschen und reaktivieren

**Seiten:** `artikel/delete.php` (GET) · Button in detail.php  
**Service:** `ArtikelService::delete()` / `aktivieren()`

```mermaid
flowchart TD
    SDEL(["User: Liste oder Detail<br/>Button: Deaktivieren"])
    CHK_EX{"Artikel vorhanden?"}
    ERR_DEL["🔴 Fehler: nicht gefunden"]
    DEACT["UPDATE aktiv = 0<br/>DB: artikel WHERE id = X"]
    DEACT_K["deactivateKinder()<br/>UPDATE aktiv = 0<br/>deaktiviert_mit_vater = 1<br/>DB: artikel WHERE vaterartikel_id = X<br/>nur bisher aktive Kinder"]
    LOG_DEL["Logger::log · artikel.loeschen<br/>DB: aktivitaeten"]
    END_DEL(["🟢 Redirect → liste.php"])

    SACT(["User: Detail<br/>Button: Reaktivieren"])
    CHK_EX2{"Artikel vorhanden?"}
    ERR_ACT["🔴 Fehler: nicht gefunden"]
    ACT["UPDATE aktiv = 1<br/>DB: artikel WHERE id = X"]
    REACT_K["reactivateKinder()<br/>UPDATE aktiv = 1<br/>deaktiviert_mit_vater = 0<br/>DB: artikel WHERE vaterartikel_id = X<br/>nur mit deaktiviert_mit_vater = 1"]
    LOG_ACT["Logger::log · artikel.aktivieren<br/>DB: aktivitaeten"]
    END_ACT(["🟢 Redirect → detail.php"])

    SDEL --> CHK_EX
    CHK_EX -->|Nein| ERR_DEL
    CHK_EX -->|Ja| DEACT --> DEACT_K --> LOG_DEL --> END_DEL

    SACT --> CHK_EX2
    CHK_EX2 -->|Nein| ERR_ACT
    CHK_EX2 -->|Ja| ACT --> REACT_K --> LOG_ACT --> END_ACT
```

> **Wichtig:** `deactivateKinder()` setzt `deaktiviert_mit_vater = 1` — so weiß `reactivateKinder()` welche Kinder wieder aktiviert werden dürfen (nur die, die durch den Vater deaktiviert wurden, nicht manuell inaktive).

---

## 5. Varianten erstellen — Stufe 1: Achsen + Werte zuweisen

**Seiten:** `artikel/achsen_zuweisen.php` → `artikel/achsen_speichern.php`  
**Service:** `VariantenService::speichereAchsenUndWerte()`

```mermaid
flowchart TD
    START(["User: detail.php<br/>Button: Achsen zuweisen"])
    PAGE["achsen_zuweisen.php<br/>Achsen aus globaler Liste wählen<br/>Werte als Chips eingeben<br/>Sortierung und Verschieben möglich"]
    POST["POST → achsen_speichern.php<br/>achsen_ids[] · werte[] je Achse"]
    INUSE{"Werte bereits in<br/>Kombinationen verwendet?<br/>DB: varianten_kombination_werte"}
    LOCK["In-use Werte bleiben erhalten<br/>Anzeige als gesperrter Chip"]
    DEL_WERTE["DELETE varianten_achse_werte<br/>nur nicht-in-use<br/>DB: varianten_achse_werte"]
    CHK_ACHSE{"Achse entfernt<br/>UND nicht geschützt?"}
    DEL_ACHSE["DELETE artikel_achsen<br/>DB: artikel_achsen"]
    INS_ACHSE["INSERT artikel_achsen<br/>nur fehlende Achsen<br/>DB: artikel_achsen"]
    INS_WERTE["INSERT varianten_achse_werte<br/>wert · aufpreis · sortierung<br/>DB: varianten_achse_werte"]
    LOG["Logger::log · achsenUndWerte.speichern<br/>DB: aktivitaeten"]
    END(["🟢 Redirect → achsen_zuweisen.php"])

    START --> PAGE --> POST --> INUSE
    INUSE -->|In-use vorhanden| LOCK --> DEL_WERTE
    INUSE -->|Keine in-use| DEL_WERTE
    DEL_WERTE --> CHK_ACHSE
    CHK_ACHSE -->|Ja| DEL_ACHSE --> INS_ACHSE
    CHK_ACHSE -->|Nein| INS_ACHSE
    INS_ACHSE --> INS_WERTE --> LOG --> END
```

---

## 6. Varianten erstellen — Stufe 2: VarKombi-Generator

**Seiten:** `artikel/detail.php` Tab Varianten → `artikel/varkombi_erstellen.php`  
**Service:** `VariantenService::erstelleKombinationen()` + `ArtikelService::kopiereVaterRelationenZuKindern()`

```mermaid
flowchart TD
    START(["User: detail.php · Tab Varianten"])
    JS["JS berechnet Kreuzprodukt aller Achswerte<br/>Gruppenachse: Sub-Achsen als UNION<br/>Bestehende Kombis werden grau markiert"]
    SELECT["User wählt Kombinationen<br/>vergibt Artikelnummern + Namen"]
    POST["POST → varkombi_erstellen.php<br/>kombis[] · hat_eigenen_lagerstand"]
    VATER["ArtikelService::findById<br/>Vater komplett laden"]
    LOOP[["Für jede gewählte Kombination:"]]
    INS_KIND["INSERT artikel<br/>DB: artikel<br/>vaterartikel_id = VaterId<br/>alle Stammdaten vom Vater geerbt<br/>url_slug = NULL"]
    INS_KOMBI["INSERT varianten_kombination_werte<br/>DB: varianten_kombination_werte<br/>je Achswert eine Zeile"]
    COPY["kopiereVaterRelationenZuKindern()"]
    CPY_KAT["copyKategorien · DB: artikel_kategorien"]
    CPY_MERK["copyMerkmale · DB: artikel_merkmale"]
    CPY_LIEF["copyLieferanten · DB: artikel_lieferanten"]
    CPY_PREIS["copyPreise alle KG · DB: artikel_preise"]
    LOG["Logger::log · varkombi.erstellen<br/>DB: aktivitaeten"]
    END(["🟢 Redirect → detail.php?tab=varianten"])

    START --> JS --> SELECT --> POST --> VATER --> LOOP
    LOOP --> INS_KIND --> INS_KOMBI --> LOOP
    LOOP -->|Alle Kombis erstellt| COPY
    COPY --> CPY_KAT --> CPY_MERK --> CPY_LIEF --> CPY_PREIS --> LOG --> END
```

### Vererbung beim Erstellen

| Bereich | Felder |
|---------|--------|
| Stamm | `hersteller_id` · `steuerklasse_id` · `artikeltyp_id` · `einheit_id` |
| Beschreibungen | `kurzbeschreibung` · `beschreibung` · `technische_details` · `beschreibung_intern` |
| SEO | `meta_titel` · `meta_description` — `url_slug` = NULL |
| Logistik | `inhalt_menge` · `inhalt_einheit` · `gewicht_artikel` · `gewicht_versand` · `laenge` · `breite` · `hoehe` |
| Zoll | `herkunftsland` · `taric_code` |
| Grundpreis | `grundpreis_bezugsmenge` · `grundpreis_anzeigen` |
| Verhalten | `charge_pflicht` · `ueberverkauf_erlaubt` · `ist_auslaufartikel` |
| Relationen | Kategorien · Merkmale · Lieferanten · Preise alle KG |

---

## 7. Kind-Artikel bearbeiten

**Seiten:** `artikel/variante_bearbeiten.php` → `artikel/variante_aktualisieren.php`  
**Service:** `ArtikelService::kindUpdate()`

```mermaid
flowchart TD
    START(["User: detail.php · Tab Varianten<br/>Button: Kind bearbeiten"])
    FORM["variante_bearbeiten.php<br/>Artikelnummer · Name<br/>Auslaufartikel-Checkbox<br/>Preis (eigener VK möglich)"]
    POST["POST → variante_aktualisieren.php<br/>Leere Strings → NULL"]
    UPDATE["kindUpdate()<br/>UPDATE artikel<br/>DB: artikel<br/>artikelnummer · aktiv<br/>ueberverkauf_erlaubt · ist_auslaufartikel"]
    LOG["Logger · artikel.kind_bearbeiten<br/>DB: aktivitaeten"]
    END(["🟢 Redirect → detail.php?id=VaterId"])

    START --> FORM --> POST --> UPDATE --> LOG --> END
```

> **Hinweis:** Kind-Artikel können nur wenige Felder selbst ändern. Stammdaten (Beschreibungen, Gewicht, Kategorien usw.) kommen immer vom Vater und werden beim Vater-Update automatisch propagiert.

---

## 8. Kategorien zuweisen (Modal)

Die Kategorie-Zuweisung ist **Teil des Bearbeiten-Workflows** (siehe Workflow 2). Das Modal selbst läuft rein im Browser — kein separater Request bis "Übernehmen" geklickt wird.

```mermaid
flowchart TD
    BTN(["User klickt: Kategorien bearbeiten"])
    OPEN["JS: katModalOeffnen()<br/>Liest bestehende hidden inputs name=kategorien[]<br/>Setzt Checkboxen entsprechend"]
    CHECK["User wählt/entfernt Kategorien<br/>Optional: Neue Kategorie anlegen"]
    NEU{"Neue Kategorie<br/>anlegen?"}
    FETCH["AJAX POST → kategorie_neu.php<br/>INSERT kategorien<br/>DB: kategorien<br/>Checkbox dynamisch hinzugefügt"]
    UEBERN["JS: katUebernehmen()<br/>Entfernt alle hidden inputs name=kategorien[]<br/>Erstellt neue hidden inputs für jede Checkbox"]
    SAVE["Wird mit Formular-Submit gespeichert<br/>→ Workflow 2: Artikel bearbeiten<br/>saveKategorien() + syncKategorienZuKindern()"]
    END(["Kategorien im Formular aktualisiert"])

    BTN --> OPEN --> CHECK --> NEU
    NEU -->|Ja| FETCH --> CHECK
    NEU -->|Nein| UEBERN --> END
    END -.->|"Beim Speichern des Artikels"| SAVE
```

---

## 9. Kategorie-Baum verwalten

**Seite:** `artikel/kategorien_verwalten.php` (eigenständige Verwaltungsseite)  
**AJAX-Endpoints:** `kategorie_bearbeiten_ajax.php` · `kategorie_loeschen_ajax.php` · `kategorie_sort_ajax.php`

```mermaid
flowchart TD
    START(["User öffnet kategorien_verwalten.php"])
    TREE["Baumansicht aller Kategorien<br/>Drag-Drop Sortierung<br/>ist_aktions_kategorie Checkbox"]

    subgraph EDIT ["Kategorie bearbeiten"]
        E1["Klick auf Kategorie-Name<br/>Inline-Edit Modal öffnet"]
        E2["AJAX POST → kategorie_bearbeiten_ajax.php<br/>name · parent_id · ist_aktions_kategorie"]
        E3["ArtikelService::updateKategorie()<br/>UPDATE kategorien<br/>DB: kategorien"]
    end

    subgraph SORT ["Reihenfolge ändern"]
        S1["Drag-Drop im Baum"]
        S2["AJAX POST → kategorie_sort_ajax.php"]
        S3["UPDATE kategorien SET sortierung<br/>DB: kategorien"]
    end

    subgraph DEL ["Kategorie löschen"]
        D1["Klick: Löschen"]
        D2["Modal: Artikel verschieben zu?<br/>Optional: Eltern-Kategorie auswählen"]
        D3["AJAX POST → kategorie_loeschen_ajax.php<br/>id · verschiebe_zu_parent_id"]
        D4["deleteKategorie()<br/>INSERT IGNORE artikel_kategorien für neue KatId<br/>DELETE artikel_kategorien für alte KatId<br/>DELETE kategorien rekursiv inkl. Kinder<br/>DB: artikel_kategorien · kategorien"]
    end

    START --> TREE
    TREE --> E1 --> E2 --> E3
    TREE --> S1 --> S2 --> S3
    TREE --> D1 --> D2 --> D3 --> D4
```

> **Wichtig bei Löschen:** Alle Kinder-Kategorien im Baum werden mitgelöscht. Artikel können optional zu einer anderen Kategorie verschoben werden — sonst werden ihre Kategorie-Zuweisungen gelöscht.

---

## 10. Preis-Workflows

### 10a. Standard-Kundengruppen-Preis setzen

**Endpoint:** `artikel/preis_speichern.php` (AJAX JSON)  
**Service:** `PreisService::speichereKundengruppenPreis()`

```mermaid
flowchart TD
    START(["User: detail.php · Tab Preise<br/>Preis für Kundengruppe eingeben"])
    POST["AJAX POST → preis_speichern.php<br/>artikel_id · kundengruppen_id<br/>brutto_vk · netto_vk · gueltig_ab · gueltig_bis"]
    UPSERT["UPSERT artikel_preise<br/>INSERT ... ON DUPLICATE KEY UPDATE<br/>DB: artikel_preise"]
    END(["🟢 JSON: erfolg"])

    START --> POST --> UPSERT --> END
```

### 10b. Staffelpreis setzen

**Endpoint:** `artikel/staffelpreis_speichern.php` (AJAX JSON)  
**Service:** `PreisService::speichereStaffelpreis()`

```mermaid
flowchart TD
    START(["User: detail.php · Tab Preise<br/>Staffelpreis-Zeile hinzufügen"])
    POST["AJAX POST → staffelpreis_speichern.php<br/>id (0=neu) · artikel_id · kundengruppen_id<br/>menge_ab · brutto_vk · netto_vk"]
    CHK_ID{"id vorhanden?"}
    UPD["UPDATE artikel_staffelpreise<br/>DB: artikel_staffelpreise"]
    INS["INSERT artikel_staffelpreise<br/>DB: artikel_staffelpreise"]
    END(["🟢 JSON: erfolg + id"])

    START --> POST --> CHK_ID
    CHK_ID -->|Ja| UPD --> END
    CHK_ID -->|Nein| INS --> END
```

### 10c. UVP setzen

**Endpoint:** `artikel/uvp_speichern.php` (AJAX JSON) — direkt DB, kein Service

```mermaid
flowchart TD
    START(["User: detail.php · Tab Preise<br/>UVP Feld"])
    POST["AJAX POST → uvp_speichern.php<br/>artikel_id · uvp"]
    UPD["UPDATE artikel SET uvp<br/>DB: artikel"]
    END(["🟢 JSON: erfolg"])

    START --> POST --> UPD --> END
```

### 10d. SALE-Override (manueller Aktionspreis)

**Endpoint:** `artikel/sale_override_speichern.php` (AJAX JSON)  
**Service:** `PreisService::speichereSaleOverride()`  
**Priorität:** Höchste — überschreibt Aktionspreise und KG-Preise

```mermaid
flowchart TD
    START(["User: detail.php · Tab Preise<br/>SALE-Override Modal öffnen"])
    FORM["Modal: brutto_vk · netto_vk<br/>gueltig_ab · gueltig_bis<br/>preis_vorher_brutto (Streichpreis)<br/>bis_lagerstand_null Checkbox"]
    POST["AJAX POST → sale_override_speichern.php<br/>kundengruppen_id optional (null = alle KG)"]
    UPSERT["PreisService::speichereSaleOverride()<br/>UPSERT artikel_preise mit sale_flag<br/>DB: artikel_preise"]
    BANNER["detail.php zeigt<br/>Streichpreis + SALE-Banner<br/>liste.php zeigt roten SALE-Chip"]
    END(["🟢 JSON: erfolg"])

    START --> FORM --> POST --> UPSERT --> BANNER --> END
```

### Preis-Prioritätskette (PreisService::getEffektiverPreis)

```
1. SALE-Override (höchste Priorität, überschreibt alles)
2. Aktion (aktionen_artikel_preise — wenn laufende Aktion für Kategorie)
3. Kundengruppen-Preis (artikel_preise für spezifische KG)
4. Standard-Preis (artikel_preise für Standard-KG)
```

---

## 11. Status-Workflows

### 11a. Auslaufartikel setzen / entfernen

**Service:** `ArtikelService::auslaufSetzen()` / `auslaufEntfernen()`

```mermaid
flowchart TD
    SSET(["User: detail.php<br/>Auslaufartikel aktivieren"])
    SET["setAuslauf()<br/>UPDATE ist_auslaufartikel = 1<br/>DB: artikel WHERE id = X"]
    SET_K["setAuslaufKinder()<br/>UPDATE ist_auslaufartikel = 1<br/>auslauf_mit_vater = 1<br/>DB: artikel WHERE vaterartikel_id = X<br/>nur bisher NICHT auslauf"]
    LOG_S["Logger · artikel.auslauf.setzen<br/>DB: aktivitaeten"]
    END_S(["🟢 Artikel + Kinder als Auslauf markiert"])

    SREM(["User: detail.php<br/>Auslaufartikel deaktivieren"])
    REM["removeAuslauf()<br/>UPDATE ist_auslaufartikel = 0<br/>DB: artikel WHERE id = X"]
    REM_K["removeAuslaufKinder()<br/>UPDATE ist_auslaufartikel = 0<br/>auslauf_mit_vater = 0<br/>DB: artikel WHERE vaterartikel_id = X<br/>nur mit auslauf_mit_vater = 1"]
    LOG_R["Logger · artikel.auslauf.entfernen<br/>DB: aktivitaeten"]
    END_R(["🟢 Auslauf-Flag entfernt"])

    SSET --> SET --> SET_K --> LOG_S --> END_S
    SREM --> REM --> REM_K --> LOG_R --> END_R
```

> **Wareneingang-Sonderfall:** Bei Wareneingang auf einen Auslaufartikel mit Bestand = 0 wird `ist_auslaufartikel` automatisch auf 0 gesetzt (Auto-Reaktivierung via `LagerService`).

### 11b. Aktiv / Inaktiv schalten

Siehe [Workflow 4: Artikel löschen und reaktivieren](#4-artikel-löschen-und-reaktivieren) — das ist dieselbe Funktion. "Löschen" ist hier ein Soft-Delete (aktiv = 0).

---

## 12. SEO-Daten speichern

**Endpoint:** `artikel/seo_speichern.php` (POST, kein Service — direkter DB-Zugriff)  
**Seite:** `artikel/detail.php` Tab SEO

```mermaid
flowchart TD
    START(["User: detail.php · Tab SEO<br/>Felder ausfüllen"])
    POST["POST → seo_speichern.php<br/>id · meta_titel · meta_description · url_slug"]
    CHK{"artikel_id > 0?"}
    ERR["🔴 Fehler: Artikel fehlt"]
    UPD["UPDATE artikel SET<br/>meta_titel · meta_description · url_slug<br/>DB: artikel WHERE id = X"]
    LOG["Logger::log · artikel.seo_aktualisiert<br/>DB: aktivitaeten"]
    END(["🟢 Redirect → detail.php · Session: erfolg"])

    START --> POST --> CHK
    CHK -->|Nein| ERR
    CHK -->|Ja| UPD --> LOG --> END
```

> **Hinweis:** `url_slug` muss systemweit eindeutig sein — wird für Shop-URLs verwendet. Kinder-Artikel haben eigene Slugs (oder NULL wenn noch nicht gesetzt).

## 13. Bilder-Workflow

**Dateien:** `bild_upload.php`, `bild_ajax.php`, `bild_loeschen.php`, `bilder.js`, `BilderRepository.php`  
**Seite:** `artikel/detail.php` Tab Bilder  
**Speicherort:** `public/uploads/artikel/{artikel_id}/` (Filesystem, PHP GD Resize)  
**DB:** `artikel_bilder` + `artikel_bilder_shops`

```mermaid
flowchart TD
    User(["👤 User\ndetail.php · Tab Bilder"])

    subgraph FE ["Frontend"]
        DZ["Drop-Zone\nDrag & Drop / Klick"]
        Grid["#bild-grid\n.bild-karte × n"]
    end

    subgraph JS ["bilder.js"]
        Delegation["Event Delegation\nauf #bild-grid"]
        Upload["ladeHoch()\nfetch POST"]
        UI["aktualisiereAlleKarten()\nOverlay + Steuer neu rendern"]
    end

    subgraph Handler ["PHP Handler"]
        BU["bild_upload.php\nGD Resize → JPEG 85%\nmax 1920px · MIME-Check"]
        BA["bild_ajax.php\naktion: hauptbild | position | alt_text"]
        BL["bild_loeschen.php\nunlink + DELETE"]
    end

    subgraph Repo ["BilderRepository.php"]
        R1["insert / delete\nupdateAltText"]
        R2["setzeHauptbild\n→ Position 0"]
        R3["verschiebePosition\n↑ nur pos > 1"]
    end

    AB[("artikel_bilder\nid · artikel_id · dateiname\nalt_text · position")]
    ABS[("artikel_bilder_shops\nbild_id + shop_id\nexternal_id · sync_status")]
    FS[("Filesystem\nuploads/artikel/{id}/")]

    User -->|"Drag & Drop"| DZ --> Upload -->|POST| BU
    BU -->|GD resize| FS
    BU --> R1 --> AB
    BU -->|JSON ok| UI

    User -->|"☆ Hauptbild / ↑↓ / Alt-Text / ✕"| Delegation
    Delegation -->|hauptbild · position · alt_text| BA
    Delegation -->|löschen| BL
    BA --> R2 & R3 --> AB
    BL --> R1
    BL -->|unlink| FS
    BA & BL -->|JSON ok| UI
    UI --> Grid

    AB -.->|"Shop-Sync\n(noch offen)"| ABS

    style AB fill:#dbeafe
    style ABS fill:#dbeafe,stroke-dasharray:5 5
    style FS fill:#fef9c3
```

**Hauptbild-Logik:**
- Position 0 = Hauptbild — nur `setzeHauptbild()` / ☆-Button darf das ändern
- `verschiebePosition()`: ↑ erlaubt nur wenn `$pos > 1` (schützt Position 0)
- Im JS: nach jeder Aktion baut `aktualisiereAlleKarten()` alle Karten komplett neu → kein DOM-Stapeln

**Wasserzeichen (geplant):**
- ERP speichert immer das saubere Original
- Wasserzeichen wird beim Shop-Sync per GD on-the-fly aufgedrückt
- Konfigurierbar pro Shop im Admin-Menü (Bild + Position)
