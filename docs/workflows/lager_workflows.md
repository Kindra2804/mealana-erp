# Lager-Modul: Workflows

> **Zielgruppe:** Entwickler + Fehlersuche nach Monaten  
> **Zweck:** Wie wird Lagerbestand berechnet? Wo passiert was bei Zu- und Abgängen?

---

## Datenmodell (zuerst lesen!)

```
lagerbestand (pro Artikel × Lager × Charge)
    ↑ wird IMMER über LagerService aktualisiert
    ↑ Quelle der Wahrheit: lagerbestand.bestand
    ↑ Audit-Trail: lager_bewegungen (nie löschen!)

reservierungen
    ↑ "weich" reservierte Mengen für offene Aufträge
    ↑ verfügbar = lagerbestand.bestand − SUM(reservierungen.menge WHERE status='offen')
```

**Schlüsseltabellen:**

| Tabelle | Inhalt |
|---------|--------|
| `lagerbestand` | Aktueller Bestand (artikel_id × lager_id × charge) |
| `lager_bewegungen` | Jede Bewegung mit Menge, Typ, Benutzer, EK-Preis |
| `lager` | Lager-Definitionen (Standard, Messe, extern_haendler …) |
| `reservierungen` | Weiche Reservierungen für offene Aufträge |
| `artikel` | Hat kein eigenes lagerbestand-Feld — alles über lagerbestand-Tabelle |

**Lager-IDs (Konfiguration):**
- ID 1 = Standardlager (immer normal)
- ID 2 = Lager Messe (umschaltbar normal ↔ Messe)
- typ = 'extern_haendler' = Konsignationslager bei Partnern

---

## 1. Wareneingang (freier Zugang)

**Seiten:** `lager/wareneingang.php` → `lager/wareneingang_speichern.php`  
**Service:** `LagerService::wareneingangBuchen()`

```mermaid
flowchart TD
    START(["User: Wareneingang\nArtikel scannen / suchen"])
    FORM["Formular:\nArtikel · Menge · EK-Preis\nLager · Charge (opt.)\nLieferschein-Nr"]
    POST["POST → wareneingang_speichern.php"]
    CHK_ART{"Artikel vorhanden\nund aktiv?"}
    ERR["🔴 Fehler: Artikel nicht gefunden"]
    INS_BEW["INSERT lager_bewegungen\ntyp = 'wareneingang'\nmenge POSITIV\nek_preis · lager_id · charge\nDB: lager_bewegungen"]
    CHK_LB{"lagerbestand-Zeile\nexistiert?"}
    UPD_LB["UPDATE lagerbestand\nbestand = bestand + menge\nDB: lagerbestand"]
    INS_LB["INSERT lagerbestand\nbestand = menge\nDB: lagerbestand"]
    CHK_AUSL{"Artikel war\nAuslaufartikel\nund bestand war 0?"}
    REAKTIV["UPDATE artikel\nist_auslaufartikel = 0\nAuto-Reaktivierung!\nDB: artikel"]
    LOG["Logger::log · lager.wareneingang\nDB: aktivitaeten"]
    END(["🟢 Redirect · Banner: Wareneingang gebucht"])

    START --> FORM --> POST --> CHK_ART
    CHK_ART -->|Nein| ERR
    CHK_ART -->|Ja| INS_BEW --> CHK_LB
    CHK_LB -->|Ja| UPD_LB --> CHK_AUSL
    CHK_LB -->|Nein| INS_LB --> CHK_AUSL
    CHK_AUSL -->|Ja| REAKTIV --> LOG
    CHK_AUSL -->|Nein| LOG --> END
    LOG --> END
```

---

## 2. Lagerabgang (Verkauf / Auftrag)

**Service:** `LagerService::lagerabgangBuchen()`  
Wird aufgerufen beim: Zahlungseingang (Vorkasse), Kassenabschluss (Bar), Auftragsanlage (Rechnung)

```mermaid
flowchart TD
    TRIGGER(["Trigger:\nZahlungseingang / Kassenabschluss\n/ Auftragsanlage"])
    CHK_BESTAND{"bestand ≥ menge\n(oder Überverkauf erlaubt?)"}
    WARN["⚠ Lagerbestand negativ\n(wenn ueberverkauf_erlaubt = 1)\nBanner-Warnung, kein Abbruch"]
    ERR["🔴 Abbruch wenn\nueberverkauf_erlaubt = 0"]
    INS_BEW["INSERT lager_bewegungen\ntyp = 'verkauf'\nmenge NEGATIV\nauftrag_id als referenz\nDB: lager_bewegungen"]
    UPD_LB["UPDATE lagerbestand\nbestand = bestand − menge\nDB: lagerbestand"]
    DEL_RESERV["DELETE reservierungen\nWHERE referenz_id = auftrag_id\nDB: reservierungen"]
    END(["Lagerabgang gebucht"])

    TRIGGER --> CHK_BESTAND
    CHK_BESTAND -->|"< 0 + kein Überverkauf"| ERR
    CHK_BESTAND -->|"< 0 + Überverkauf OK"| WARN --> INS_BEW
    CHK_BESTAND -->|OK| INS_BEW
    INS_BEW --> UPD_LB --> DEL_RESERV --> END
```

---

## 3. Lagerrückbuchung (Storno / Retoure)

**Service:** `LagerService::rueckbuchungBuchen()`

```mermaid
flowchart TD
    TRIGGER(["Trigger:\nAuftrag storniert (Cronjob oder manuell)\noder Retoure am Packplatz"])
    INS_BEW["INSERT lager_bewegungen\ntyp = 'storno' | 'retoure'\nmenge POSITIV (Zugang)\nDB: lager_bewegungen"]
    UPD_LB["UPDATE lagerbestand\nbestand = bestand + menge\nDB: lagerbestand"]
    CHK_ZUSTAND{"Zustand = einwandfrei?"}
    NORMAL["Normaler Lagerbestand\n→ fertig"]
    DEFEKT["Artikel in Zustand 'beschädigt'\nggf. eigener Lagerplatz\n→ TODO: Zustandsverwaltung Packplatz"]
    LOG["Logger::log · lager.rueckbuchung\nDB: aktivitaeten"]
    END(["Rückbuchung abgeschlossen"])

    TRIGGER --> INS_BEW --> UPD_LB --> CHK_ZUSTAND
    CHK_ZUSTAND -->|OK| NORMAL --> LOG
    CHK_ZUSTAND -->|Defekt| DEFEKT --> LOG
    LOG --> END
```

---

## 4. Umlagerung (Standardlager ↔ Messe)

**Seite:** `lager/umlagerung.php` (geplant)  
**Service:** `LagerService::umlagerungBuchen()`

```mermaid
flowchart TD
    START(["User: Umlagerung\nArtikel + Menge + Von/Nach-Lager"])
    POST["POST → umlagerung_speichern.php"]
    INS_AB["INSERT lager_bewegungen\ntyp = 'umlagerung_ab'\nmenge NEGATIV\nlager_id = Quelle\nDB: lager_bewegungen"]
    INS_ZU["INSERT lager_bewegungen\ntyp = 'umlagerung_zu'\nmenge POSITIV\nlager_id = Ziel\nDB: lager_bewegungen"]
    UPD_AB["UPDATE lagerbestand\nbestand − menge (Quelle)\nDB: lagerbestand"]
    UPD_ZU["UPDATE lagerbestand\nbestand + menge (Ziel)\nDB: lagerbestand"]
    LOG["Logger::log · lager.umlagerung"]
    END(["🟢 Umlagerung gebucht"])

    START --> POST --> INS_AB --> INS_ZU --> UPD_AB --> UPD_ZU --> LOG --> END
```

---

## 5. Reservierungen (Verfügbarkeitsberechnung)

```mermaid
flowchart TD
    BERECH(["Verfügbarkeit berechnen"])
    LB["SELECT bestand FROM lagerbestand\nWHERE artikel_id = X AND lager_id = Y\nDB: lagerbestand"]
    RES["SELECT SUM(menge) FROM reservierungen\nWHERE artikel_id = X AND status = 'offen'\nDB: reservierungen"]
    VERF["verfügbar = bestand − reserviert\n(kann negativ sein bei Überverkauf)"]
    END(["Anzeige: ist / reserviert / verfügbar"])

    BERECH --> LB --> RES --> VERF --> END
```

**Reservierung-Lifecycle:**

| Ereignis | Aktion |
|---------|--------|
| Auftrag angelegt (Vorkasse) | INSERT reservierungen status='offen' |
| Zahlung eingegangen | DELETE reservierungen → Lagerabgang buchen |
| Auftrag storniert | DELETE reservierungen |
| Lagerabgang ohne Reservierung | Direkt buchen (Kasse, Sofort-Zahlung) |

---

## 6. Bestandsermittlung — Debugging-Kurzformel

```sql
-- Bestand eines Artikels im Standardlager (lager_id=1)
SELECT bestand FROM lagerbestand WHERE artikel_id = ? AND lager_id = 1;

-- Alle Bewegungen eines Artikels (chronologisch)
SELECT typ, menge, erstellt_am, benutzer_id
FROM lager_bewegungen
WHERE artikel_id = ?
ORDER BY erstellt_am;

-- Offene Reservierungen
SELECT SUM(menge) FROM reservierungen
WHERE artikel_id = ? AND status = 'offen';

-- Erwarteter Bestand (aus Bewegungen neu berechnen = Kontrolle)
SELECT SUM(menge) FROM lager_bewegungen WHERE artikel_id = ? AND lager_id = 1;
```

> **Wenn `lagerbestand.bestand` ≠ `SUM(lager_bewegungen.menge)` → Datenfehler!**  
> Ursache: direkte DB-Updates außerhalb LagerService, oder abgebrochene Transaktionen.
