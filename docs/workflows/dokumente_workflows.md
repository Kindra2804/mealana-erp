# Dokumente-System: Workflows

> **Zielgruppe:** Entwickler + Fehlersuche nach Monaten  
> **Zweck:** Welches Dokument wird wann erzeugt, welche Tabellen sind betroffen?

---

## Dokumententypen-Übersicht

| Typ | Nummer | Wann erzeugt | Tabelle |
|-----|--------|-------------|---------|
| Auftragsbestätigung (AB) | — (keine eigene Nr.) | Nach Auftragsanlage | `auftrag_dokumente` |
| Lieferschein | LS-2026-xxxxx | Versandbereit | `auftrag_dokumente` |
| Rechnung | R-2026-xxxxx | Zahlungseingang (Rechnung) / manuell | `rechnungen` + `auftrag_dokumente` |
| Gutschrift | G-2026-xxxxx | Storno einer Rechnung | `rechnungen` (storno_von) |
| Mahnung | — | Cronjob / manuell | `auftrag_dokumente` |
| Abholzettel | — | Auf Wunsch | `auftrag_dokumente` |
| Bon (Kasse) | B-2026-xxxxx | Kassenabschluss | Kassen-Modul (TODO) |
| Pickliste | PL-2026-xxxxx | Babsi erstellt | `picklisten` |

**Nummernkreise** alle in `dokument_nummern` (typ, praefix, jahr, letzt_nr).

---

## 1. Dokument erzeugen — allgemeiner Flow

**Service:** `DokumentService` (geplant) / `TwigRenderer` + Dompdf

```mermaid
flowchart TD
    TRIGGER(["Trigger:\nButton 'Drucken' / Statuswechsel\n/ Cronjob"])
    TYP{"Dokumententyp?"}

    AB["Auftragsbestätigung\nkein eigener Nummernkreis\nDateiname: AB_{auftragsnr}.pdf"]
    LS["Lieferschein\nSELECT + INCREMENT dokument_nummern\ntyp='lieferschein'\nDateiname: LS-2026-xxxxx.pdf"]
    RE["Rechnung\nSELECT + INCREMENT dokument_nummern\ntyp='rechnung'\nINSERT rechnungen\nDateiname: R-2026-xxxxx.pdf"]
    GS["Gutschrift\nSELECT + INCREMENT\ntyp='gutschrift'\nINSERT rechnungen\nstorno_von = rechnungen.id\nDateiname: G-2026-xxxxx.pdf"]

    TWIG["Twig::render(template, variablen)\nDaten aus auftraege + auftrag_positionen\nDB: auftraege · auftrag_positionen · kunden · artikel"]
    DOMPDF["Dompdf::render(html) → PDF-Bytes"]
    SAVE["file_put_contents\nstorage/dokumente/{auftrag_id}/dateiname.pdf"]
    INS_DOK["INSERT auftrag_dokumente\nDB: auftrag_dokumente"]
    LOG["Logger::log · dokument.erstellt\nDB: aktivitaeten"]
    END(["PDF gespeichert + optional Download"])

    TRIGGER --> TYP
    TYP -->|AB| AB --> TWIG
    TYP -->|Lieferschein| LS --> TWIG
    TYP -->|Rechnung| RE --> TWIG
    TYP -->|Gutschrift| GS --> TWIG
    TWIG --> DOMPDF --> SAVE --> INS_DOK --> LOG --> END
```

---

## 2. Rechnung — Detailflow

```mermaid
flowchart TD
    START(["Trigger:\nZahlungsart='rechnung' + Versand abgeschlossen\nODER manuell Button 'Rechnung erstellen'"])
    CHK_RRE{"Rechnung bereits\nin rechnungen vorhanden?"}
    EXIST["🔴 Abbruch\n(Doppel-Rechnung verhindern!)"]
    NR["SELECT + INCREMENT\ndokument_nummern WHERE typ='rechnung'\nDB: dokument_nummern\nFormat: R-2026-00001"]
    INS_RE["INSERT rechnungen\nrechnung_nr · auftrag_id\nnetto/steuer/brutto · faellig_am\nDB: rechnungen"]
    CHK_B2B{"Auftrag B2B?"}
    NETTO["Template: rechnung_b2b.html.twig\n(Netto-Darstellung, USt. separat)"]
    BRUTTO["Template: rechnung_b2c.html.twig\n(Brutto-Darstellung)"]
    PDF["Twig → Dompdf → PDF"]
    INS_DOK["INSERT auftrag_dokumente\ntyp='rechnung'\nDB: auftrag_dokumente"]
    MAIL{"Per Mail senden?"}
    MAILSEND["Mailer::sendeTemplate\nrechnung_mail.html.twig\nAnhang: PDF\nDB: aktivitaeten"]
    END(["🟢 Rechnung erstellt"])

    START --> CHK_RRE
    CHK_RRE -->|Ja| EXIST
    CHK_RRE -->|Nein| NR --> INS_RE --> CHK_B2B
    CHK_B2B -->|Ja| NETTO --> PDF
    CHK_B2B -->|Nein| BRUTTO --> PDF
    PDF --> INS_DOK --> MAIL
    MAIL -->|Ja| MAILSEND --> END
    MAIL -->|Nein| END
```

---

## 3. Gutschrift (Rechnungsstorno)

```mermaid
flowchart TD
    START(["User: Rechnung stornieren\nButton in detail.php")
    CHK_RE{"Rechnung vorhanden\nund nicht bereits storniert?"}
    ERR["🔴 Fehler"]
    NR["SELECT + INCREMENT\ndokument_nummern WHERE typ='gutschrift'\nDB: dokument_nummern\nFormat: G-2026-00001"]
    INS_GS["INSERT rechnungen\nstorno_von = rechnung.id\nstorniert = 1 auf Original\nDB: rechnungen (2 Zeilen)"]
    TWIG["Template: gutschrift.html.twig\n(Negativbeträge)"]
    PDF["Dompdf → PDF"]
    INS_DOK["INSERT auftrag_dokumente\ntyp='gutschrift'\nDB: auftrag_dokumente"]
    LAGER{"Ware zurückgekommen?"}
    RUECK["LagerService::rueckbuchungBuchen()\nDB: lager_bewegungen · lagerbestand"]
    MAIL["Mailer: gutschrift_mail.html.twig\nmit Anhang"]
    END(["🟢 Gutschrift erstellt"])

    START --> CHK_RE
    CHK_RE -->|Nein| ERR
    CHK_RE -->|Ja| NR --> INS_GS --> TWIG --> PDF --> INS_DOK --> LAGER
    LAGER -->|Ja (Retoure)| RUECK --> MAIL
    LAGER -->|Nein| MAIL --> END
    RUECK --> END
```

---

## 4. Pickliste erzeugen (Babsi-Seite, TODO)

```mermaid
flowchart TD
    START(["Babsi: Picklisten-Manager\nAufträge auswählen"])
    CHK_LAGER["Lagerbestand prüfen:\nverfügbar = bestand − reserviert\nDB: lagerbestand · reservierungen"]
    SELECT["Aufträge auswählen\n(auto: komplett auslieferbar zuerst)"]
    NR["SELECT + INCREMENT\ndokument_nummern WHERE typ='pickliste'\nDB: dokument_nummern\nFormat: PL-2026-00001"]
    INS_PL["INSERT picklisten\nINSERT pickliste_auftraege\nDB: picklisten · pickliste_auftraege"]
    PDF["Twig → PDF mit Barcode\nder Picklisten-Nummer"]
    STATUS["UPDATE picklisten.status = 'gedruckt'\nDB: picklisten"]
    END(["🟢 PDF drucken → zum Packplatz"])

    START --> CHK_LAGER --> SELECT --> NR --> INS_PL --> PDF --> STATUS --> END
```

---

## 5. Debugging — Dokumente

| Problem | Wo suchen |
|---------|-----------|
| PDF nicht gefunden | `auftrag_dokumente.dateiname` → Pfad auf Disk vorhanden? |
| Doppelte Rechnung | `rechnungen WHERE auftrag_id = X` — `CHK_RRE` hat nicht gegriffen |
| Falsche Rechnungsnummer | `dokument_nummern WHERE typ='rechnung'` — letzt_nr prüfen |
| Gutschrift referenziert falsche Rechnung | `rechnungen.storno_von` prüfen |
| Pickliste bleibt "offen" | Status in `picklisten` — alle zugehörigen `auftraege.lieferstatus` prüfen |
