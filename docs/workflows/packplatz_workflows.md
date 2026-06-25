# Packplatz-Modul: Workflows

> **Zielgruppe:** Entwickler + Fehlersuche nach Monaten  
> **Zweck:** Scan-Flow, EasyPak, Picklisten — was passiert wo?

---

## Systemübersicht

```
Babsi (Büro-PC)          Packplatz-PC (Tablet/Touchscreen)    PLC (Österr. Post)
─────────────────        ────────────────────────────────     ─────────────────
Picklisten-Manager  →    packplatz/warenausgang/              EasyPak XML lesen
(Aufträge auswählen,     scan.php (EAN-Scan, Grün/Rot)        → Zebra-Druck
 PDF drucken)            abschliessen.php (Status, Mail)      → Label mit Tracking
                         ↓                                    ← Tracking-Nr am Label
                         Tracking-Nr vom Label abscannen
```

**Schlüsseltabellen:**

| Tabelle | Inhalt |
|---------|--------|
| `auftraege` | lieferstatus, versand_tracking, versand_datum |
| `auftrag_positionen` | Positionen inkl. EAN (eingefroren) |
| `picklisten` | Picklisten-Kopfdaten (offen / gedruckt / abgeschlossen) |
| `pickliste_auftraege` | Zuordnung Pickliste ↔ Aufträge (n:m) |
| `auftrag_statuslog` | Statuslog-Einträge (aktion = "Versendet — Tracking: XXX") |

---

## 1. Warenausgang — Übersichtsseite

**Seite:** `packplatz/warenausgang/index.php`

```mermaid
flowchart TD
    START(["User öffnet Packplatz\n→ Warenausgang"])
    LINKS["Links: offene Picklisten\nSELECT FROM picklisten WHERE status='offen'\nDB: picklisten"]
    DIREKT["Rechts:\nAuftrags-Nummer direkt eintippen/scannen\n+ Liste offener Aufträge\n(lieferstatus IN ('neu','in_bearbeitung'), max 10)\nDB: auftraege"]

    CHK{"Was wurde gewählt?"}
    PL["Pickliste ausgewählt\n→ scan.php?modus=pickliste&pickliste_id=X"]
    AUF["Auftrag direkt\n→ scan.php?modus=auftrag&auftrag_id=X"]

    START --> LINKS & DIREKT --> CHK
    CHK -->|Pickliste| PL
    CHK -->|Auftrag| AUF
```

---

## 2. Scan-Interface

**Seite:** `packplatz/warenausgang/scan.php`  
**JS:** `public/js/packplatz_scan.js`

```mermaid
flowchart TD
    START(["scan.php lädt"])
    LOAD["Auftrag(e) aus DB laden\nJOIN auftrag_positionen\nJOIN artikel (ean_gtin13, gewicht_versand)\nJOIN artikel_bilder (Hauptbild)\nDB: auftraege · auftrag_positionen · artikel · artikel_bilder"]
    GEWICHT["Gewicht vorberechnen\n= SUM(gewicht_versand × menge)\nDB: artikel.gewicht_versand"]
    RENDER["Tabelle: Artikel | Menge | Gescannt\nGrau = offen · Grün = fertig · Rot = zu viel\nRechts: Auftrags-Info-Box"]
    SCAN["EAN-Scan oder ArtNr in Scan-Feld\nJS: verarbeiteEan(ean)\n→ bucheMenge(idx, menge)"]
    MATCH{"EAN / ArtNr\nmatcht Position?"}
    UNK["Scan-Feld rot blinken\n'Unbekannt' im Bildfeld"]
    UPDATE["Zeile aktualisieren:\ngescannt++\nFarbe: pp-aktiv / pp-ok / pp-zuviel"]
    BILD["Artikelbild rechts anzeigen"]
    FERTIG{"Alle Positionen\nexakt gescannt?"}
    BTN["'Verpacken'-Button aktiv"]

    START --> LOAD --> GEWICHT --> RENDER
    RENDER --> SCAN --> MATCH
    MATCH -->|Nein| UNK --> SCAN
    MATCH -->|Ja| UPDATE --> BILD --> FERTIG
    FERTIG -->|Noch nicht| SCAN
    FERTIG -->|Ja| BTN
```

### Overlay-Flow nach "Verpacken"

```mermaid
flowchart TD
    BTN(["User: Verpacken"])
    OV["Overlay öffnet:\nGewicht (vorausgefüllt, editierbar)\nTracking-Feld (leer, wartet auf Scan)"]
    SCAN_TR["Barcode-Scanner liest Tracking\nvom aufgedruckten Label"]
    SUBMIT["JS: verpackenAbschliessen(false)\n→ hidden Form → POST abschliessen.php\nFelder: auftrag_id · pickliste_id · tracking\ngewicht · teillieferung='0' · positionen_json"]

    TL_BTN(["User: Teillieferung"])
    TL_OV["Gleiches Overlay mit Teillieferung-Flag"]
    TL_SUBMIT["→ POST mit teillieferung='1'"]

    BTN --> OV --> SCAN_TR --> SUBMIT
    TL_BTN --> TL_OV --> SCAN_TR --> TL_SUBMIT
```

---

## 3. Abschluss-Handler

**Seite:** `packplatz/warenausgang/abschliessen.php`

```mermaid
flowchart TD
    POST(["POST von scan.php\nauftrag_id · pickliste_id\ntracking · gewicht\nteillieferung · positionen_json"])
    VALID{"auftrag_id\nund tracking\nvorhanden?"}
    ERR["🔴 Redirect → index.php\nmit Fehler-Session"]

    TXN["BEGIN TRANSACTION"]
    UPD_AUF["UPDATE auftraege\nversand_tracking = tracking\nversand_datum = NOW()\nlieferstatus = 'versendet' | 'teilgeliefert'\nDB: auftraege"]
    LOG_STATUS["INSERT auftrag_statuslog\naktion = 'Versendet — Tracking: ...'\nDB: auftrag_statuslog"]
    CHK_PL{"pickliste_id\nvorhanden?"}
    PL_CHECK["Prüfen: offene Aufträge dieser Pickliste\nSELECT COUNT(*) ... NOT IN versendet/storniert\nDB: pickliste_auftraege · auftraege"]
    PL_DONE["UPDATE picklisten\nstatus = 'abgeschlossen'\nDB: picklisten"]
    COMMIT["COMMIT"]

    EasyPak{"lieferart = 'versand'\nund plc_polling_ordner\nkonfiguriert?"}
    XML["EasyPakExporter::exportiere()\nXML in PLC-Ordner schreiben\nPLC liest → Zebra-Druck"]

    MAIL{"E-Mail-Adresse\nvorhanden und\nkeine Teillieferung?"}
    VERSANDMAIL["Mailer::sendeTemplate\nversandbestaetigung.html.twig\nmit Tracking-Link Post.at\nDB: aktivitaeten (log)"]

    LOGGER["Logger::log · packplatz.versendet\nDB: aktivitaeten"]

    CHK_NEXT{"Pickliste hat\nnoch offene Aufträge?"}
    NEXT_SCAN(["→ scan.php nächster Auftrag"])
    END(["→ warenausgang/index.php"])

    POST --> VALID
    VALID -->|Nein| ERR
    VALID -->|Ja| TXN --> UPD_AUF --> LOG_STATUS --> CHK_PL
    CHK_PL -->|Ja| PL_CHECK --> PL_DONE --> COMMIT
    CHK_PL -->|Nein| COMMIT
    COMMIT --> EasyPak
    EasyPak -->|Ja| XML --> MAIL
    EasyPak -->|Nein / Abholung| MAIL
    MAIL -->|Ja| VERSANDMAIL --> LOGGER
    MAIL -->|Nein| LOGGER
    LOGGER --> CHK_NEXT
    CHK_NEXT -->|Ja| NEXT_SCAN
    CHK_NEXT -->|Nein| END
```

---

## 4. EasyPak-Exporter

**Klasse:** `src/core/EasyPakExporter.php`  
**Dateiformat:** ISO-8859-1 XML im PLC-Polling-Ordner

```mermaid
flowchart TD
    IN(["exportiere(auftrag_id, gewicht_kg, ziel_ordner)"])
    LOAD["Auftrag + Snapshots laden\nFirma-Einstellungen laden\nDB: auftraege · system_einstellungen"]
    LAND["lieferadresse_snapshot.land\n→ ISO-Code (AT/DE/CH …)"]
    ROUTE{"Lieferland?"}
    AT["Item-ID: 430101\nContract: Paket Österreich"]
    EU["Item-ID: 430106\nContract: Paket Premium International"]
    INTL["Item-ID: 430104\nContract: Paket International"]
    NN{"zahlungsart\n= 'nachnahme'?"}
    COD["Item-ID 430124 (COD)\nmit Betrag + IBAN\nDB: system_einstellungen (iban, bic)"]
    XML["XML bauen (als UTF-8 String)"]
    ENC["mb_convert_encoding → ISO-8859-1\n(Post-Anforderung)"]
    WRITE["file_put_contents\neasypak_{auftragsnr}_{timestamp}.xml\nin plc_polling_ordner"]
    OUT(["Dateiname zurückgeben"])

    IN --> LOAD --> LAND --> ROUTE
    ROUTE -->|AT| AT --> NN
    ROUTE -->|EU| EU --> NN
    ROUTE -->|Rest| INTL --> NN
    NN -->|Ja| COD --> XML
    NN -->|Nein| XML
    XML --> ENC --> WRITE --> OUT
```

---

## 5. Debugging-Hinweise

| Problem | Wo suchen |
|---------|-----------|
| Tracking fehlt in auftraege | `abschliessen.php` wurde nicht erreicht (JS-Fehler? POST leer?) |
| EasyPak XML nicht erstellt | `plc_polling_ordner` in system_einstellungen leer/falscher Pfad? `is_dir()` prüfen |
| Versandmail nicht angekommen | `aktivitaeten`-Log: mail_aktiv='1'? email in kunden_snapshot? |
| Pickliste bleibt "offen" | Prüfen: alle pickliste_auftraege.auftrag_id → lieferstatus IN (versendet/storniert)? |
| Falsche Gewichtsangabe | artikel.gewicht_versand für alle Positionen prüfen (nicht gewicht_artikel!) |
| Scan erkennt EAN nicht | `auftrag_positionen.ean` leer — EAN wurde beim Anlegen nicht eingefroren |
