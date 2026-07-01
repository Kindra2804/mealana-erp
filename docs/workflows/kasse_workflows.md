# Kasse (POS): Workflows

> **Zielgruppe:** Entwickler + Fehlersuche nach Monaten  
> **Zweck:** Bon-Erstellung, Zahlwege, Abholbereit-Flow, Kassensturz — was passiert wo?

---

## Systemübersicht

```
Kasse (kasse/index.php)
─────────────────────────────────────────────────────────────
EAN-Scan / Namenssuche → Artikel in Warenkorb
Zahlart: Bar | Karte extern | Gutschein
Chargen-Dialog bei charge_pflicht=1
Vater→Variante-Auswahl bei ist_vater=1
                         ↓
              bon_speichern.php
                         ↓
kassen_bons + kassen_bon_positionen  ←→  lager_bewegungen
                         ↓
              bon_druck.php (80mm Browser-Druck)
                         ↓
              [GEPLANT] RKSV / BFR-BONit Signatur
```

**Schlüsseltabellen:**

| Tabelle | Inhalt |
|---------|--------|
| `kassen` | Kassen-Instanzen (Name, Lager, RKSV-ID) |
| `kassen_bons` | Bon-Kopfdaten (Gesamtbetrag, Zahlart, Typ: verkauf/retour/storno) |
| `kassen_bon_positionen` | Positionen pro Bon (Artikel, Menge, Preis, Charge) |
| `kassenbuch` | Kassenstand-Einträge (Einlage, Entnahme, Z-Bon) |
| `offene_auswahl` | Abholbereit+bezahlt Aufträge (Link ERP-Auftrag ↔ Kasse) |
| `lager_bewegungen` | Lagerabgänge (negativ) bei jedem Bon |

---

## 1. Bon erstellen — Normalverkauf

**Seiten:** `kasse/index.php` → `kasse/bon_speichern.php` → `kasse/bon_druck.php`  
**JS:** Inline-Script in index.php (ausgelagert: kasse_bon.js)

```mermaid
flowchart TD
    START(["User öffnet kasse/index.php"])
    SCAN["EAN scannen\noder Namenssuche-Modal öffnen"]

    CHK_ART{"Artikel gefunden?"}
    NOT_FOUND["🔴 Fehlermeldung:\n'Artikel nicht gefunden'"]

    CHK_VATER{"ist_vater = 1?"}
    VAR_MODAL["Varianten-Modal öffnen\nKind-Artikel wählen"]

    CHK_CHARGE{"charge_pflicht = 1?"}
    CHARGE_MODAL["Chargen-Dialog (ov-charge)\nCharge wählen / nachzutragen / ohne"]

    WARENKORB["Artikel zum Warenkorb hinzufügen\nMenge · Preis · Rabatt %"]
    REPEAT{"Weitere Artikel?"}

    ZAHLUNG["Zahlart wählen:\nBar | Karte extern | Gutschein"]
    CHK_BAR{"Bar?"}
    RUECKGELD["Gegeben-Betrag eingeben\nRückgeld = Gegeben − Gesamt"]

    POST["POST → bon_speichern.php\n(kasse_id, positionen[], zahlart, betrag)"]

    LAGER["INSERT lager_bewegungen\n(menge negativ, je Position)\nUPDATE lagerbestand\nDB: lager_bewegungen · lagerbestand"]

    BON_NR["Bon-Nummer generieren\nDB: dokument_nummern (typ='bon')"]
    INS_BON["INSERT kassen_bons\n(typ='verkauf', zahlart, bruttobetrag)\nDB: kassen_bons"]
    INS_POS["INSERT kassen_bon_positionen\n(artikel_id, bezeichnung, charge)\nDB: kassen_bon_positionen"]
    LOG["INSERT aktivitaeten\naktion='kasse.bon_erstellt'\nDB: aktivitaeten"]

    DRUCK["Redirect → bon_druck.php?id=X\n80mm Browser-Druck\n@page { size: 80mm auto }"]
    END(["🟢 Bon fertig — weiter zum nächsten"])

    START --> SCAN --> CHK_ART
    CHK_ART -->|"Nicht gefunden"| NOT_FOUND --> SCAN
    CHK_ART -->|"Gefunden"| CHK_VATER
    CHK_VATER -->|"Ja"| VAR_MODAL --> CHK_CHARGE
    CHK_VATER -->|"Nein"| CHK_CHARGE
    CHK_CHARGE -->|"Ja"| CHARGE_MODAL --> WARENKORB
    CHK_CHARGE -->|"Nein"| WARENKORB
    WARENKORB --> REPEAT
    REPEAT -->|"Ja"| SCAN
    REPEAT -->|"Nein"| ZAHLUNG
    ZAHLUNG --> CHK_BAR
    CHK_BAR -->|"Ja"| RUECKGELD --> POST
    CHK_BAR -->|"Karte/Gutschein"| POST
    POST --> LAGER --> BON_NR --> INS_BON --> INS_POS --> LOG --> DRUCK --> END
```

### Debugging: Bon nicht erstellt
| Symptom | Wo suchen |
|---------|-----------|
| Artikel nicht gefunden | `artikel_codes` WHERE code = EAN · aktiv=1? |
| Charge fehlt in Bon | `kassen_bon_positionen.charge` NULL → Chargen-Dialog übersprungen? |
| Lagerbestand nicht abgebucht | `lager_bewegungen` WHERE referenz_tabelle='kassen_bons' AND referenz_id=X |
| Bon-Nummer doppelt | `dokument_nummern` WHERE typ='bon' — letzt_nr stuck? |

---

## 2. Abholbereit+bezahlt — 4 Fälle

**Seiten:** `kasse/offene_auswahl.php` → `kasse/offene_auswahl_speichern.php` → `kasse/offene_auswahl_verarbeiten.php`

Wenn ein ERP-Auftrag auf "abholbereit" gesetzt wurde UND bezahlt ist, erscheint er in der Kasse.  
Die Kasse vergleicht die Auftrags-Positionen mit dem was der Kunde tatsächlich mitnimmt.

```mermaid
flowchart TD
    START(["Kunde kommt abholen\nKasse: Offene Auswahl"])
    LOAD["Auftrag laden\nDB: auftraege · auftrag_positionen"]
    COMPARE["Kassierer gibt tatsächliche Mengen ein\n(kann abweichen von Auftragsmenge)"]

    CHK_TYP{"Vergleich\nAuftrag vs. Kasse"}

    EXAKT["EXAKT:\nKasse = Auftrag\n→ kein Bon nötig"]
    EXAKT_UPD["UPDATE auftraege\nlieferstatus = 'abgeschlossen'\nDB: auftraege"]
    EXAKT_LOG["INSERT auftrag_statuslog\nDB: auftrag_statuslog"]

    RETOUR["RETOUR:\nKasse < Auftrag\n→ Retour-Bon für Differenz"]
    RETOUR_BON["INSERT kassen_bons\ntyp='retour'\nbetrag = Differenz (negativ)\nDB: kassen_bons"]
    RETOUR_AUSZ["Barauszahlung an Kunden\n(Rückgeld-Dialog)"]
    RETOUR_RUECK["INSERT lager_bewegungen\n(Differenz-Menge positiv = Rückbuchung)\nDB: lager_bewegungen"]

    EXTRA["EXTRA:\nKasse > Auftrag\n→ Extra-Bon nur für Extras"]
    EXTRA_BON["INSERT kassen_bons\ntyp='verkauf'\nnur Extra-Positionen\nDB: kassen_bons"]
    EXTRA_LAGER["INSERT lager_bewegungen\n(Extras negativ ausbuchen)\nDB: lager_bewegungen"]
    EXTRA_ZAHLUNG["Zusatzbetrag: Bar / Karte"]

    MIX["MIX:\nTeile retour + Extra"]
    MIX_NOTE["Kombination aus\nRetour-Bon + Extra-Bon\n(beide werden erstellt)"]

    FINISH["auftrag_positionen anpassen\nauf tatsächliche Mengen\nlieferstatus = 'abgeschlossen'"]
    END(["🟢 Fertig\noptional: Bon drucken"])

    START --> LOAD --> COMPARE --> CHK_TYP
    CHK_TYP -->|"Gleich"| EXAKT --> EXAKT_UPD --> EXAKT_LOG --> END
    CHK_TYP -->|"Weniger"| RETOUR --> RETOUR_BON --> RETOUR_AUSZ --> RETOUR_RUECK --> FINISH --> END
    CHK_TYP -->|"Mehr"| EXTRA --> EXTRA_BON --> EXTRA_LAGER --> EXTRA_ZAHLUNG --> FINISH --> END
    CHK_TYP -->|"Mix"| MIX --> MIX_NOTE --> FINISH --> END
```

### Wichtiger Hinweis: kunden_snapshot
```
Bei K1 (Kasse 1) Bons wird kunden_snapshot IMMER vom Original-Auftrag kopiert.
Nicht neu aus kunden-Tabelle laden — Daten könnten inzwischen geändert sein.
→ kassen_bons.kunden_snapshot = auftraege.kunden_snapshot (eingefroren)
```

---

## 3. Bon stornieren

**Seiten:** `kasse/bon_journal.php` → `kasse/bon_stornieren.php`

```mermaid
flowchart TD
    START(["User öffnet Bon-Journal\nkasse/bon_journal.php"])
    FIND["Bon suchen (Bon-Nr., Datum)\nDB: kassen_bons"]
    OPEN["Bon-Detail öffnen"]

    CHK_STORNO{"Bon bereits\nstorniert?"}
    BLOCKED["🔴 'Bon bereits storniert'\n(kein doppelter Storno)"]

    POST["POST → bon_stornieren.php\n(kassen_bon_id)"]

    RUECK["INSERT lager_bewegungen\n(Menge positiv — Rückbuchung)\nfür jede Position\nDB: lager_bewegungen · lagerbestand"]

    NEW_BON["INSERT kassen_bons\ntyp='storno'\nreferenz_bon_id = original_bon_id\nbetrag negativ\nDB: kassen_bons"]
    INS_POS["INSERT kassen_bon_positionen\n(Kopie der Original-Positionen, negativ)\nDB: kassen_bon_positionen"]

    UPD_ORIG["UPDATE kassen_bons\nstatus='storniert'\nDB: kassen_bons"]

    DRUCK["Redirect → bon_druck.php?id=neuer_storno_bon\n80mm Storno-Bon drucken"]
    END(["🟢 Bon storniert\nLager rückgebucht"])

    START --> FIND --> OPEN --> CHK_STORNO
    CHK_STORNO -->|"Ja"| BLOCKED
    CHK_STORNO -->|"Nein"| POST --> RUECK --> NEW_BON --> INS_POS --> UPD_ORIG --> DRUCK --> END
```

---

## 4. Kassensturz / Z-Bon (Tagesabschluss)

**Seiten:** `kasse/kassensturz.php` → `kasse/kassensturz_speichern.php`

```mermaid
flowchart TD
    START(["Kassierer öffnet Kassensturz\nkasse/kassensturz.php"])

    ZAEHLEN["Zählhilfe:\nScheine + Münzen einzeln eingeben\n→ Summe wird berechnet"]

    CHK_BON{"X-Bon oder Z-Bon?"}

    XBON["X-Bon (Zwischenbericht):\nnur anzeigen, kein Abschluss\nKassenstand bleibt offen"]
    XBON_DRUCK["80mm Druck\nX-Bon (kein Tagesabschluss)"]

    ZBON["Z-Bon (Tagesabschluss):\nechte Abschlussbuchung"]
    ZBON_POST["POST → kassensturz_speichern.php"]
    ZBON_DB["INSERT kassenbuch\ntyp='z_bon'\nbetrag = gezählter Bestand\nDB: kassenbuch"]
    ZBON_RESET["Laufende Bon-Nummern für\nnächsten Tag vorbereitet\n(dokument_nummern bleibt laufend)"]
    ZBON_LOG["INSERT aktivitaeten\naktion='kasse.kassensturz'\nDB: aktivitaeten"]
    ZBON_DRUCK["80mm Druck\nZ-Bon mit Zusammenfassung"]

    END(["🟢 Tagesabschluss fertig"])

    START --> ZAEHLEN --> CHK_BON
    CHK_BON -->|"X-Bon"| XBON --> XBON_DRUCK
    CHK_BON -->|"Z-Bon"| ZBON --> ZBON_POST --> ZBON_DB --> ZBON_RESET --> ZBON_LOG --> ZBON_DRUCK --> END
```

---

## 5. Chargen-Dialog

Bei Artikeln mit `charge_pflicht=1` erscheint beim Scan ein Overlay.

```mermaid
flowchart TD
    SCAN(["EAN gescannt\ncharge_pflicht = 1"])
    MODAL["Overlay ov-charge öffnet\nFIFO: älteste Charge zuerst vorschlagen\n(via findArtikelByCode in KassenService)"]

    CHK_WAHL{"Kassierer wählt:"}
    WAHL_OK["Charge auswählen\n(aus Dropdown)"]
    WAHL_NEU["Neue Charge eintragen\n(Freitext)"]
    WAHL_OHNE["Ohne Charge weiter\n(charge = NULL)"]

    BON_POS["kassen_bon_positionen.charge\n= gewählte / eingetragene Charge"]
    LAGER_ABGANG["lager_bewegungen.charge\n= gleiche Charge\n(Rückbuchung bei Storno liest Charge\n aus kassen_bon_positionen)"]

    END(["Artikel im Warenkorb\nmit Charge-Zuordnung"])

    SCAN --> MODAL --> CHK_WAHL
    CHK_WAHL --> WAHL_OK & WAHL_NEU & WAHL_OHNE
    WAHL_OK & WAHL_NEU & WAHL_OHNE --> BON_POS --> LAGER_ABGANG --> END
```

---

## 6. Divers-Artikel (freier Preis)

Für Artikel ohne Stammdatensatz (z.B. Sonderpositionen, Spenden):

```mermaid
flowchart TD
    START(["Kassierer klickt 'Divers'"])
    FORM["Freitext-Name + Betrag eingeben\nSteuerklasse wählen"]
    BON["Wird als Position ohne artikel_id\nin Warenkorb eingefügt\nartikel_id = NULL, bezeichnung = Freitext"]
    END(["Bon weiter normal"])
    START --> FORM --> BON --> END
```

---

## Debugging-Checkliste Kasse

```
Bon nicht gedruckt?
  → bon_druck.php?id=X direkt aufrufen
  → 80mm Thermodrucker als Windows-Standarddrucker gesetzt?
  → @page { size: 80mm auto } im Druck-CSS vorhanden?

Lagerbestand nach Bon falsch?
  → lager_bewegungen WHERE referenz_tabelle='kassen_bons' AND referenz_id=X
  → kassen_bon_positionen: Menge stimmt?

Abholbereit-Auftrag erscheint nicht in Kasse?
  → auftraege: lieferstatus='abholbereit'? zahlungsstatus='bezahlt'?
  → offene_auswahl WHERE auftrag_id=X vorhanden?

Chargen-Problem?
  → kassen_bon_positionen.charge prüfen
  → lager_bewegungen.charge muss mit bon_position.charge übereinstimmen
  → Storno-Rückbuchung liest Charge aus original bon_positionen
```
