# Auftragsmodul: Workflows

> **Zielgruppe:** Entwickler + Fehlersuche nach Monaten  
> **Zweck:** Welche Tabellen, Services und Pfade sind bei welchem Auftragsproblem beteiligt?  
> **Handbuch:** siehe `../handbuch/auftraege_handbuch.md`

---

## Legende

| Symbol | Bedeutung |
|--------|-----------|
| Abgerundete Box | Start / Ende / Seite |
| Rechteck | Verarbeitungsschritt |
| Raute | Entscheidung |
| `DB:` | Betroffene DB-Tabelle(n) |
| 🔴 | Fehler-/Abbruchpfad |
| 🟢 | Erfolgspfad |

---

## Statusmodell (zuerst lesen!)

```
zahlungsstatus:  ausstehend → bezahlt | teilbezahlt | erstattet | storniert
lieferstatus:    neu → in_bearbeitung → versandbereit → versendet | teilgeliefert → abgeschlossen
                                                     ↘ storniert (jederzeit)
                                                     ↘ zurueckgestellt
```

**Schlüsseltabellen:**

| Tabelle | Inhalt |
|---------|--------|
| `auftraege` | Kopfdaten, Status-ENUMs, Snapshot-JSONs, Beträge |
| `auftrag_positionen` | Positionen (Artikel, Menge, Preise eingefroren) |
| `auftrag_statuslog` | Jede Statusänderung mit Diff-JSON |
| `auftrag_dokumente` | Pfade zu generierten PDFs |
| `rechnungen` | Rechnungsnummern (R-2026-xxxxx) |
| `mahnungen` | Erinnerungen/Stornierungen durch Cronjob |
| `lager_bewegungen` | Lagerabgänge (negativ) beim Verbuchen, Rückbuchung bei Storno |
| `reservierungen` | Reservierte Lagermengen für offene Aufträge |
| `picklisten` / `pickliste_auftraege` | Kommissionierung |

---

## 1. Auftrag manuell anlegen

**Seiten:** `auftraege/neu.php` → `auftraege/speichern.php` → `auftraege/detail.php`

```mermaid
flowchart TD
    START(["User öffnet neu.php"])
    KUNDE["Kunden suchen / Laufkunde wählen\nkunden_id oder 'laufkunde'"]
    FORM["Formular:\nZahlungsart · Lieferart · Versandklasse\nNotiz intern · Notiz Versand"]
    POS["Positionen hinzufügen:\nArtikel via EAN / Artikelnummer suchen\nMenge · Preis · Rabatt"]
    CALC["Summen berechnen:\nnettobetrag · steuerbetrag · bruttobetrag\n+ Versandkosten − Gutschein"]
    POST["POST → speichern.php"]
    VALNR["Auftragsnummer generieren\nAuftragService::naechsteNummer()\nDB: dokument_nummern (typ=auftrag)\nFormat: A-2026-00001"]
    SNAPSHOT["kunden_snapshot einfrieren\n(JSON: name, strasse, email …)\nlieferadresse_snapshot\nrechnungsadresse_snapshot"]
    INS_AUF["INSERT auftraege\nDB: auftraege"]
    INS_POS["INSERT auftrag_positionen\n(bezeichnung + ean eingefroren)\nDB: auftrag_positionen"]
    LAGER{"Sofort ausbuchen?"}
    RESERV["INSERT reservierungen\nDB: reservierungen\nstatus='offen'"]
    LOG["INSERT auftrag_statuslog\nDB: auftrag_statuslog"]
    END(["🟢 Redirect → detail.php?id=X"])

    START --> KUNDE --> FORM --> POS --> CALC --> POST
    POST --> VALNR --> SNAPSHOT --> INS_AUF --> INS_POS
    INS_POS --> LAGER
    LAGER -->|"Vorkasse: noch nicht"| RESERV --> LOG
    LAGER -->|"Rechnung / Kasse: sofort"| LOG
    LOG --> END
```

### Debugging: Auftrag nicht angelegt
| Symptom | Wo suchen |
|---------|-----------|
| Doppelte Auftragsnummer | `dokument_nummern` — letzt_nr stuck? |
| Snapshot leer | `kunden_snapshot` NULL → kunden_id fehlte |
| Positionen fehlen | `auftrag_positionen` mit auftrag_id prüfen |

---

## 2. Auftrag bearbeiten (bis "in_bearbeitung")

**Seiten:** `auftraege/bearbeiten.php` → `auftraege/aktualisieren.php`

Positionen können geändert werden solange `lieferstatus IN ('neu','in_bearbeitung','versandbereit')`.

```mermaid
flowchart TD
    START(["detail.php → Bearbeiten"])
    CHK_STATUS{"lieferstatus\nin_bearbeitung\noder früher?"}
    LOCKED["🔴 Bearbeitung gesperrt\n(versendet/abgeschlossen)"]
    FORM["Positionen / Kopfdaten ändern"]
    POST["POST → aktualisieren.php"]
    UPD_AUF["UPDATE auftraege\nDB: auftraege"]
    DEL_POS["DELETE auftrag_positionen\nWHERE auftrag_id = X"]
    INS_POS["INSERT neue Positionen\nDB: auftrag_positionen"]
    RECALC["Summen neu berechnen\n→ UPDATE auftraege"]
    LOG["INSERT auftrag_statuslog\nDB: auftrag_statuslog"]
    END(["🟢 Redirect → detail.php"])

    START --> CHK_STATUS
    CHK_STATUS -->|Nein| LOCKED
    CHK_STATUS -->|Ja| FORM --> POST --> UPD_AUF --> DEL_POS --> INS_POS --> RECALC --> LOG --> END
```

---

## 3. Zahlungseingang buchen

**Seite:** `auftraege/zahlung_buchen.php` (AJAX oder POST)

```mermaid
flowchart TD
    START(["User: detail.php\nButton: Zahlung buchen"])
    POST["POST: auftrag_id · betrag · zahlungsart"]
    CHK_BETRAG{"Betrag = bruttobetrag?"}
    VOLL["UPDATE auftraege\nzahlungsstatus = 'bezahlt'\nbezahlt_am = NOW()\nDB: auftraege"]
    TEIL["UPDATE auftraege\nzahlungsstatus = 'teilbezahlt'\nDB: auftraege"]
    RESERV_AUF["reservierungen → status='aufgeloest'\noder Lagerabgang direkt\nDB: reservierungen · lager_bewegungen"]
    LOG["INSERT auftrag_statuslog\nDB: auftrag_statuslog"]
    MAIL{"Auftragsbestätigung\nsenden?"}
    MAIL_SEND["Mailer::sendeTemplate\nauftragsbestaetigung.html.twig\nDB: aktivitaeten (log)"]
    END(["🟢 Banner: Zahlung gebucht"])

    START --> POST --> CHK_BETRAG
    CHK_BETRAG -->|"="|  VOLL --> RESERV_AUF --> LOG --> MAIL
    CHK_BETRAG -->|"<"| TEIL --> RESERV_AUF --> LOG --> MAIL
    MAIL -->|"Vorkasse + E-Mail"| MAIL_SEND --> END
    MAIL -->|"Sonstiges"| END
```

---

## 4. Stornierung (manuell)

**Seite:** `auftraege/stornieren.php`

```mermaid
flowchart TD
    START(["User: detail.php\nButton: Stornieren"])
    CHK{"lieferstatus\nnicht versendet?"}
    WARN["⚠ Warnung: Ware evtl. bereits unterwegs\n(nur Hinweis, kein Block)"]
    UPDATE["UPDATE auftraege\nzahlungsstatus = 'storniert'\nlieferstatus = 'storniert'\nDB: auftraege"]
    CHK_LAGER{"Lagerabgang\nbereits gebucht?"}
    RUECK["INSERT lager_bewegungen\nmenge POSITIV (Rückbuchung)\nDB: lager_bewegungen"]
    UPD_LB["UPDATE lagerbestand\nlagerbestand + rückgabe\nDB: lagerbestand"]
    RESERV_DEL["DELETE reservierungen\nWHERE referenz_id = auftrag_id\nDB: reservierungen"]
    LOG["INSERT auftrag_statuslog\naktion='storniert'\nDB: auftrag_statuslog"]
    END(["🟢 Auftrag storniert"])

    START --> CHK
    CHK -->|"versendet"| WARN --> UPDATE
    CHK -->|"früher"| UPDATE
    UPDATE --> CHK_LAGER
    CHK_LAGER -->|Ja| RUECK --> UPD_LB --> RESERV_DEL --> LOG
    CHK_LAGER -->|Nein| RESERV_DEL --> LOG --> END
    LOG --> END
```

---

## 5. Mahnwesen-Cronjob

**Datei:** `erp/cron/mahnwesen.php`  
**Zeitplan:** täglich, z.B. 08:00 Uhr  
**Ziel:** `zahlungsart IN ('vorkasse','rechnung') AND zahlungsstatus IN ('offen','ausstehend')`

```mermaid
flowchart TD
    CRON(["🕗 Cronjob startet\ncron/mahnwesen.php"])
    QUERY["SELECT alle offenen Aufträge\nZA = vorkasse ODER rechnung\nZS = offen ODER ausstehend\nDB: auftraege"]
    LOOP[["Für jeden Auftrag:"]]

    CHK14{"erstellt_am\n≥ 14 Tage alt?"}
    CHK_ERR{"mahnungen.typ\n= 'erinnerung'\nbereits vorhanden?"}
    SEND14["Mailer: erinnerung.html.twig\nan Kunden-E-Mail"]
    INS14["INSERT mahnungen\ntyp = 'erinnerung'\nDB: mahnungen"]

    CHK30{"erstellt_am\n≥ 30 Tage alt?"}
    CHK_VK{"zahlungsart\n= 'vorkasse'?"}

    CHK_STRN{"mahnungen.typ\n= 'stornierung'\nbereits vorhanden?"}
    STORNO["UPDATE auftraege\nlieferstatus = 'storniert'\nzahlungsstatus = 'storniert'\nDB: auftraege"]
    STORNO_LAGER["INSERT lager_bewegungen\n(Rückbuchung, Menge positiv)\nDB: lager_bewegungen · lagerbestand"]
    STORNO_MAIL["Mailer: stornierung.html.twig"]
    INS_STRN["INSERT mahnungen\ntyp = 'stornierung'\nDB: mahnungen"]

    CHK_HIN{"mahnungen.typ\n= 'hinweis'\nbereits vorhanden?"}
    HINWEIS["INSERT mahnungen\ntyp = 'hinweis'\n→ NUR Log, kein Auto-Storno!\nDB: mahnungen"]
    HINWEIS_WHY>"⚠ Rechnung 30d: Ware ggf. bereits\nausgeliefert → manuell prüfen!"]

    NEXT["Nächster Auftrag"]
    DONE(["Cronjob fertig"])

    CRON --> QUERY --> LOOP
    LOOP --> CHK14
    CHK14 -->|Nein| NEXT
    CHK14 -->|Ja| CHK_ERR
    CHK_ERR -->|Bereits gesendet| CHK30
    CHK_ERR -->|Noch nicht| SEND14 --> INS14 --> CHK30

    CHK30 -->|Nein| NEXT
    CHK30 -->|Ja| CHK_VK
    CHK_VK -->|Vorkasse| CHK_STRN
    CHK_STRN -->|Bereits gesendet| NEXT
    CHK_STRN -->|Noch nicht| STORNO --> STORNO_LAGER --> STORNO_MAIL --> INS_STRN --> NEXT

    CHK_VK -->|Rechnung| CHK_HIN
    CHK_HIN -->|Bereits eingetragen| NEXT
    CHK_HIN -->|Noch nicht| HINWEIS --> HINWEIS_WHY --> NEXT

    NEXT -->|Weitere Aufträge| LOOP
    NEXT -->|Keine weiteren| DONE
```

### Debugging: Mahnwesen-Probleme
| Symptom | Wo suchen |
|---------|-----------|
| Erinnerung kam nicht | `mahnungen` WHERE auftrag_id = X · `aktivitaeten` Log · mail_aktiv='1'? |
| Falscher Storno | `mahnungen.typ` = 'stornierung' vorhanden? — evtl. doppelter Cronjob-Lauf |
| Rechnung fälschlich storniert | `zahlungsart` in auftraege prüfen — sollte 'rechnung' sein → nur 'hinweis' |

---

## 6. Statusübergänge — Übersicht

```mermaid
stateDiagram-v2
    [*] --> neu : Auftrag angelegt
    neu --> in_bearbeitung : Manuelle Freigabe
    neu --> storniert : Storno
    in_bearbeitung --> versandbereit : Pickliste erstellt
    in_bearbeitung --> storniert : Storno
    versandbereit --> versendet : Packplatz abgeschlossen
    versandbereit --> teilgeliefert : Teillieferung
    versandbereit --> storniert : Storno
    teilgeliefert --> versendet : Rest versendet
    teilgeliefert --> abgeschlossen : Manuell
    versendet --> abgeschlossen : Manuell / WC-Sync
    zurueckgestellt --> in_bearbeitung : Freigabe
    in_bearbeitung --> zurueckgestellt : Rückstellen
```
