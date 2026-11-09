---
name: project-datentransfer
description: "Datentransfer ERP → ERP (scripts/datentransfer.php, src/modules/datentransfer/) — gebaut 2026-11-09 für den Indra-Umzug Dev → Live, wiederverwendbar für weitere Shops und Kunden"
metadata:
  node_type: memory
  type: project
  originSessionId: 75b00eaf-ba08-4cd0-87b9-ad6e86a4b5cf
  modified: 2026-11-09T19:44:39.750Z
---

Gebaut 2026-11-09 (Claude, Jacky wollte es fertig statt Trainer-Modus; Erklärung im Nachgang), Commit 769af5e.

- CLI `erp/scripts/datentransfer.php export|import`, Klassen `DatentransferExport`/`DatentransferImport`. Paket = .tar (PharData, keine zip-Erweiterung in XAMPP) mit `daten.json` + `dateien/`.
- Teile: kategorien (ganzer Baum / `--kategorie-baum=`), hersteller, artikel (`--shop=`, `--artikel-kategorie=`, `--alle-artikel`), slider, menue. Kein Bestand, keine Woo-IDs.
- Zuordnung über natürliche Schlüssel: Steuerklasse Satz+Land, Artikeltyp Code, Einheit Kürzel, Artikelgruppe Konto, Versandklasse Code, Kundengruppe Typ+Name, Lieferant Name, Shop Slug→Kürzel→Name, Kategorie Name+Eltern, Hersteller Name, Achse Code/Name, Artikel Artikelnummer (vorhanden = unverändert). Eine Transaktion, `--probelauf`, Wiederholung idempotent. Übernimmt leere Kanal-Einstellungen (Wurzelkategorie, Logo), Menü-Orte, Slider-Intervall.
- Test gegen frische 1.0-DB: 185 Indra-Artikel identisch (Fingerabdruck über Preise/Bilder/Codes/Kategorien/Achsen/Werte/Bedingungen/Kombis), Konfigurator-Preismatrix identisch, 481 Dateien.
- Funde: Dev hat Kategorie „Sparpakete“ doppelt auf oberster Ebene (156, 386) → im Ziel zusammengeführt. Artikelgruppe 4040 „Sonstiges Zubehör“ fehlt in der Baseline → wird angelegt. Ausgeblendete Kategorien anderer Kanäle (MeaLana/BIO/SOW) erst nach Anlegen dieser Kanäle per erneutem Import.
- Live-Reihenfolge: Kanal indra-design (Slug `indra-design`, IND) anlegen → Import → Woo-Shop muss vorher leer sein (Produkte, Kategorien, Attribute), sonst SKU-Konflikte. Siehe [[project-1-0-check]].
- Ideen später: Oberfläche im ERP (··· → Datentransfer), Modus „aktualisieren“ für vorhandene Artikel.
