# MeaLana ERP — Modul-Übersicht & Abhängigkeiten

> **Zielgruppe:** Entwickler + Fehlersuche nach Monaten  
> **Zweck:** Orientierungskarte — welches Modul hängt woran?

---

## Modul-Karte

```mermaid
graph TD
    ARTIKEL["📦 Artikel-Modul\nartikel · artikel_preise\nartikel_kategorien · artikel_bilder\nartikel_merkmale · artikel_codes"]
    PREIS["💶 Preise & Aktionen\nartikel_preise · artikel_staffelpreise\naktionen · aktionen_artikel_preise\nkundengruppen"]
    LAGER["🏭 Lager\nlagerbestand · lager_bewegungen\nlager · reservierungen"]
    AUFTRAG["📋 Auftragsmodul\nauftraege · auftrag_positionen\nauftrag_statuslog · auftrag_dokumente\nrechnungen · mahnungen"]
    PACKPLATZ["📦 Packplatz\npicklisten · pickliste_auftraege\nEasyPakExporter\nversand_tracking"]
    KUNDEN["👤 Kundendatenbank\nkunden · kunden_adressen\naes_256_gcm Verschlüsselung"]
    DOKUMENTE["📄 Dokumente\nDokumentService · Twig + Dompdf\nauftrag_dokumente · rechnungen"]
    MAIL["📧 Mail / Mahnwesen\nMailer · PHPMailer\nmahnungen · Cronjob"]
    EINSTELLUNGEN["⚙ Einstellungen\nsystem_einstellungen\nshops · kanäle"]
    PARTNER["🤝 Partner-Modul\npartner · partner_belege\nmietfaecher · spenden_log"]
    BESTELLUNG["🛒 Bestellmodul (Einkauf)\nbestellungen · bestellung_positionen\nbestellung_eingaenge"]
    KASSE["🖥 Kasse (TODO)\nBONs · RKSV / BFR"]
    WOO["🌐 WooCommerce-Sync\nshops · artikel_bilder_shops\nkanal_auftrag_id"]

    ARTIKEL -->|"Artikel-Daten"| PREIS
    ARTIKEL -->|"Stammdaten"| LAGER
    ARTIKEL -->|"Bezeichnung/EAN\neingefroren"| AUFTRAG
    PREIS -->|"Effektivpreis\nbei Auftragsanlage"| AUFTRAG
    LAGER -->|"Abgang bei Verkauf\nRückbuchung bei Storno"| AUFTRAG
    LAGER -->|"Bestand für Picklisten"| PACKPLATZ
    AUFTRAG -->|"Versand abschließen"| PACKPLATZ
    AUFTRAG -->|"Rechnung / GS"| DOKUMENTE
    AUFTRAG -->|"Mahnungen / Storno"| MAIL
    KUNDEN -->|"Snapshot einfrieren"| AUFTRAG
    EINSTELLUNGEN -->|"SMTP · Firma · PLC-Ordner"| MAIL
    EINSTELLUNGEN -->|"Firma-Daten"| DOKUMENTE
    AUFTRAG -->|"Bestands-Check\nbei WC-Eingang"| WOO
    ARTIKEL -->|"Artikel sync"| WOO
    PACKPLATZ -->|"Versandmail"| MAIL
    PARTNER -->|"Konsignation\nlager.typ='extern_haendler'"| LAGER
    BESTELLUNG -->|"Wareneingang"| LAGER
    KASSE -->|"Direktverkauf"| AUFTRAG
    KASSE -->|"RKSV Signatur"| DOKUMENTE
```

---

## Fertige Module (Stand 2026-06-25)

| Modul | Seiten | Status |
|-------|--------|--------|
| **Artikel** | liste / detail / neu / bearbeiten / kopieren | ✅ Fertig |
| **Artikel-Bilder** | detail Tab Bilder, GD-Resize | ✅ Fertig |
| **Artikel-Merkmale** | merkmale/ (2-Ebenen, Single/Multi) | ✅ Fertig |
| **Varianten** | Achsen / Werte / VarKombi-Generator | ✅ Fertig |
| **Kategorien** | Baum / Drag-Drop / Aktionskategorien | ✅ Fertig |
| **Preise** | KG-Preise / Staffel / UVP / SALE-Override | ✅ Fertig |
| **Aktionen** | Aktionszeiträume / Artikel-Preise / Cronjob | ✅ Fertig |
| **Lager** | Wareneingang / Übersicht / Bewegungen | ✅ Fertig |
| **Auftragsmodul** | Liste / Detail / Neu / Bearbeiten / Statuslog | ✅ Fertig |
| **Mahnwesen** | Cronjob (14d Erinnerung / 30d Vorkasse Storno) | ✅ Fertig |
| **Packplatz Warenausgang** | Scan / EasyPak / Versandmail | ✅ Fertig |
| **Einstellungen** | Firma / Kanäle / SMTP / System | ✅ Fertig |
| **Kundendatenbank** | Liste / Detail / AES-256 | ✅ Fertig |
| **Partner-Modul** | Mietfächer / Kommission / Spenden | ✅ Fertig |
| **Bestellmodul (Einkauf)** | Bestellungen / Positionen / Eingang | ✅ Fertig |

## Offene Module (Reihenfolge laut Plan)

| Modul | Priorität | Abhängigkeiten |
|-------|-----------|----------------|
| Picklisten-Manager (Babsi) | 🔴 Nächste Phase | Lagerstand ist/reserviert/verfügbar |
| Packplatz: Intern / Retoure | 🔴 Nächste Phase | Packplatz-Warenausgang fertig |
| Kasse / POS | 🟡 Nach Packplatz | RKSV / BFR-API (Referenz: reference_bfr_api.md) |
| WooCommerce-Sync | 🟡 Parallel | artikel_bilder_shops, shops-Tabelle |
| Druck-Listen | 🟢 Mit Druck-Modul | EAN/Bilder-Qualitätslisten |
| Statistik / Dashboard | 🟢 Nach Verkauf | Auftragsmodul + Lager |

---

## Schlüssel-Services

| Klasse | Datei | Zuständigkeit |
|--------|-------|---------------|
| `Database` | `src/core/Database.php` | PDO-Singleton |
| `Logger` | `src/core/Logger.php` | Aktivitäten-Log |
| `Mailer` | `src/core/Mailer.php` | PHPMailer-Wrapper, mail_aktiv-Flag |
| `EasyPakExporter` | `src/core/EasyPakExporter.php` | EasyPak-XML für PLC |
| `ArtikelService` | `src/services/ArtikelService.php` | CRUD + Propagierung |
| `VariantenService` | `src/services/VariantenService.php` | Achsen + VarKombi |
| `LagerService` | `src/services/LagerService.php` | Alle Lagerbewegungen |
| `PreisService` | `src/services/PreisService.php` | Effektivpreis-Berechnung |
| `DokumentService` | `src/services/DokumentService.php` | Twig + Dompdf (TODO vollständig) |

---

## Kritische Abhängigkeiten — Debugging-Checkliste

```
Bestand stimmt nicht?
  → lagerbestand.bestand vs SUM(lager_bewegungen.menge) vergleichen
  → reservierungen WHERE status='offen' addieren

Preis falsch?
  → PreisService::getEffektiverPreis Prioritätskette:
    SALE-Override > Aktion > KG-Preis > Standard-Preis
  → aktionen: gueltig_ab/bis prüfen, gestartet=1?

Auftrag hat falschen Status?
  → auftrag_statuslog: Verlauf ansehen
  → mahnungen: War Cronjob-Storno?

Dokument fehlt?
  → auftrag_dokumente WHERE auftrag_id = X
  → Datei auf Disk: storage/dokumente/{auftrag_id}/

Mail kam nicht?
  → system_einstellungen: mail_aktiv = '1'?
  → aktivitaeten: Eintrag vorhanden aber kein Versand?
  → SMTP-Einstellungen: Einstellungen → Mail/SMTP → Test-Mail senden
```
