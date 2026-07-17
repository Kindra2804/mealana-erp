---
name: project-auftragsmodul
description: "Auftragsmodul Design: Status-Workflow, DB-Tabellen, Nummerierung, Templates, WooCommerce-Sync"
metadata: 
  node_type: memory
  type: project
  originSessionId: 9a44da56-fbce-4da5-b4f6-17b472024d63
---

## 🔴 BUG behoben (2026-07-09): Nummernkreis ohne Selbstheilung

`AuftragRepository::insert()` + `insertRechnung()` machten für die Nummernvergabe direkt ein `UPDATE dokument_nummern SET letzt_nr = letzt_nr + 1 WHERE typ=... AND jahr=...`, ohne vorher zu prüfen ob die Zeile fürs aktuelle Jahr existiert. Fehlte sie (Jahreswechsel ohne manuellen Eintrag, oder frische Installation), matchte das UPDATE 0 Zeilen — kein Fehler, einfach still — und jeder Auftrag/jede Rechnung bekam für immer `A-JJJJ-00000` / `R-JJJJ-00000`. `DokumentRepository` (Gutschrift, Lieferschein, Pickliste...) hatte für genau diesen Fall schon eine Selbstheilung (`INSERT IGNORE` falls Zeile fehlt) — nur die zwei wichtigsten Nummernkreise hatten sie nicht. Jetzt beide auf dasselbe Muster umgestellt (`INSERT IGNORE INTO dokument_nummern (typ, praefix, jahr, letzt_nr) VALUES (...)` direkt vor dem UPDATE).
**Why:** Kein Cronjob legt zum Jahreswechsel neue `dokument_nummern`-Zeilen an — hätte spätestens am 01.01.2027 auch die Live-Umgebung getroffen, nicht nur Neuinstallationen.
**How to apply:** Falls weitere `dokument_nummern`-Konsumenten dazukommen (z.B. neue Belegtypen), immer das Selbstheilungs-Muster verwenden, nie nur UPDATE+SELECT ohne vorherigen INSERT IGNORE.

## ✅ UX: Positionen-Eingabe für Telefonbestellungen beschleunigt (2026-07-10)

Jacky bemängelte `auftraege/neu.php`: pro Artikel musste man nach Menge-Eingabe zurück zum "+ Position"-Button oben scrollen — bei Telefonbestellungen (Hauptanwendungsfall für viele Positionen hintereinander) unangenehm. Referenzwunsch war ein "Achsen-ähnliches" automatisches Nachrücken neuer Zeilen.

**Fix in `js/auftraege_neu.js`:** `artikelWaehlen()` legt jetzt automatisch eine neue leere Zeile an, sobald die gerade befüllte Zeile die letzte war (still, ohne Fokus zu stehlen), und fokussiert stattdessen das Menge-Feld der gerade gewählten Zeile. Neuer Enter-Handler auf dem Menge-Feld (`springeZurNaechstenZeile()`) springt danach in die (bereits vorbereitete) nächste Zeile — funktioniert für beide Artikel-Auswahlwege (Typeahead-Dropdown UND Artikel-Browser-Modal, da beide über denselben `artikelWaehlen()`-Knotenpunkt laufen). Nebeneffekt: `e.preventDefault()` auf Enter im Menge-Feld verhindert jetzt auch ein versehentliches Auslösen des Formular-Submits.

**Warum keine Erweiterung auf Preis/Rabatt-Enter:** Jackys Kernbedarf ist der Artikel→Menge→nächster-Artikel-Loop (Preis/Steuer kommen automatisch vom Artikel) — bewusst nicht weiter ausgebaut ohne konkreten Bedarf, siehe [[feedback_scope_ohne_bedarf]].

**Nicht live im Browser getestet** (kein Browser-Tool verfügbar) — Code manuell durchgetraced (Fokus-Reihenfolge, Zeilen-Erkennung via `lastElementChild`), JS-Datei per curl auf Syntax-Unversehrtheit geprüft. Nächster Praxistest sollte den Ablauf einmal bestätigen.

**Nachtrag selber Tag:** Enter im Artikel-Suchfeld selbst löste (Formular hat einen Submit-Button) bisher ein versehentliches Absenden des ganzen Auftrags aus — v.a. bei bekannter Artikelnummer oder nur noch einem Listentreffer, aus Gewohnheit Enter gedrückt. Fix: `keydown`-Handler auf dem Bezeichnungsfeld verhindert Submit immer; bei genau einem Treffer oder exakter Artikelnummer/EAN-Übereinstimmung wird automatisch übernommen (Ergebnisliste wird dafür am Dropdown-Element gemerkt, `drop._ergebnisse`), sonst passiert nichts. Kompletter Loop jetzt rein per Tastatur: Artikelnummer → Enter → Menge → Enter → nächster Artikel. Von Jacky bestätigt ("sehr gut").

## ✅ Manueller Storno → Kundenmail (2026-07-09)

`AuftragService::stornieren()` verschickt jetzt automatisch eine Mail an den Kunden (neues Template `templates/mails/auftrag_storniert.html.twig`, neutraler Ton + optionaler Grund — nicht das Mahnwesen-Template, das ist spezifisch für automatische 30-Tage-Stornos). Nutzt den `kunden_snapshot` direkt vom Auftrag (keine Entschlüsselung nötig). Stornierungsgrund kommt aus dem bestehenden `prompt()` in `storniereAuftrag()` (auftraege_detail.js), keine UI-Änderung nötig. Mailversand scheitert nur geloggt, blockiert die Stornierung selbst nicht.
**E2E getestet (2026-07-09):** Echter Testauftrag angelegt, `stornieren()` ausgelöst, Mail ging korrekt an die konfigurierte Testadresse (`mail_test_adresse` = office@indra-design.at, dev hat `mail_aktiv=0`) — von Jacky bestätigt ("Passt"). Testauftrag danach wieder gelöscht.

## Auftragsbearbeitung ✅ FERTIG (2026-06-24)

- `public/auftraege/bearbeiten.php` — Formular (wie neu.php, DB-vorbelegt, Kunde readonly, Sperr-Check)
- `public/auftraege/aktualisieren.php` — POST-Handler
- `AuftragService::bearbeiten()` — Update Header + Delete/Insert Positionen + logStatus mit Brutto-Diff
- `AuftragRepository::updateHeader()` — Whitelist-Pattern, nur editierbare Felder
- `AuftragRepository::deletePositionen()` — DELETE FROM auftrag_positionen WHERE auftrag_id
- `auftraege_neu.js` — `positionHinzufuegen()` erweitert für `window.POSITIONEN` (DB-Vorladen)
- **Sperr-Check**: versendet/abgeschlossen/storniert → nicht mehr editierbar
- **Kein Lager-Adjustment** bei Bearbeitung — Lager wird erst beim Packplatz/Versand angepasst
- **"Reserviert" im Artikel-Bestand** = dynamisch aus offenen auftrag_positionen berechnet (kein extra Buchungsvorgang)
- **WC-Sync bei Auftrag-Änderung**: geplant für WC-Modul (kanal_auftrag_id ist vorhanden)
- **Lieferadresse**: in neu.php + bearbeiten.php eingebaut (lieferadresse_snapshot); Rechnungsadresse in bearbeiten.php readonly (eingefroren)
- **Preisanzeige**: system_einstellungen 'preisanzeige_auftrag' steuert Brutto/Netto in Formularen + JS-Konvertierung vor Submit
- **Liste**: Zahlungsart-Spalte ergänzt (chip-Klassen inkl. sc-aktion/sc-fehlbest/sc-ohnekat für mehr Farbauswahl)

## Grundentscheidungen

- **Zahlungsstatus + Lieferstatus getrennt** (wie JTL) — unabhängig voneinander änderbar
- **Keine Kanal-Prefixes** in Nummern — Kanal-Chip + Filterbox in der Liste reichen
- **Auftragsnummer**: A-2026-00001 (pro Jahr neu, lückenlos)
- **Rechnungsnummer**: R-2026-00001 (eigene Sequenz, getrennt von Auftrag, AT UStG §11)
- **Template-System**: Twig + Dompdf — Weitergabefähigkeit an andere Betriebe

## Status-Workflow

```
ZAHLUNGSSTATUS: ausstehend → bezahlt | erstattet | storniert
                (teilbezahlt nur für Deposits/Strickaufträge später)

LIEFERSTATUS:
neu → in_bearbeitung → versandbereit → versendet → abgeschlossen
                     → teilgeliefert → abgeschlossen
                     → zurueckgestellt → in_bearbeitung (nach WE)
    → storniert (jederzeit)
```

## Mahnwesen

**Vorkasse** (Zahlung kommt nicht):
- 14 Tage ohne Zahlungseingang → automatische Erinnerungsmail
- 30 Tage → erscheint in Dashboard-Liste "Zum Stornieren" → manueller Klick → Stornomail + Lagerbestand freigeben

**Rechnungszahler** (wenige Stammkunden):
- Mahnstufen 1 + 2 — wird gebaut wenn erste Rechnungszahler im System

## DB-Tabellen (Migration 060-063)

```sql
auftraege (
  id, auftrag_nr VARCHAR(20) UNIQUE,    -- A-2026-00001
  kunden_id INT FK NULL,                -- NULL = Laufkunde
  kunden_snapshot JSON,                 -- Adresse einfrieren!
  lieferadresse_snapshot JSON,
  rechnungsadresse_snapshot JSON,
  kanal ENUM(woocommerce, manuell, kasse),
  kanal_auftrag_id INT NULL,            -- WC Order-ID
  zahlungsstatus ENUM(ausstehend, bezahlt, teilbezahlt, erstattet, storniert),
  lieferstatus ENUM(neu, in_bearbeitung, versandbereit, teilgeliefert,
                    zurueckgestellt, versendet, abgeschlossen, storniert),
  zahlungsart ENUM(vorkasse, paypal, rechnung, bar, gutschein, gemischt),
  zahlungsbedingung_id INT FK NULL,
  gutschein_id INT FK NULL,
  gutschein_betrag DECIMAL(10,2) DEFAULT 0,
  versandkosten DECIMAL(10,2) DEFAULT 0,
  rabatt_gesamt DECIMAL(10,2) DEFAULT 0,
  nettobetrag DECIMAL(10,2),
  steuerbetrag DECIMAL(10,2),
  bruttobetrag DECIMAL(10,2),
  bezahlt_am DATETIME NULL,
  mahnung_stufe TINYINT DEFAULT 0,      -- 0/1/2
  mahnung_gesendet_am DATETIME NULL,
  tracking_nr VARCHAR(100) NULL,
  versanddienstleister VARCHAR(50) NULL,
  notiz_intern TEXT,
  notiz_versand TEXT,                   -- aufs Packerl
  erstellt_am, aktualisiert_am, erstellt_von INT FK benutzer
)

auftrag_positionen (
  id, auftrag_id INT FK, artikel_id INT FK,
  chargen_id INT FK NULL,               -- Farbkonsistenz!
  bezeichnung VARCHAR(255),             -- eingefroren
  ean VARCHAR(20),                      -- eingefroren
  menge INT, menge_geliefert INT DEFAULT 0,
  einzelpreis_netto DECIMAL(10,2),
  steuer_prozent DECIMAL(5,2),
  rabatt_prozent DECIMAL(5,2) DEFAULT 0,
  gesamtpreis_netto DECIMAL(10,2),
  sort_order INT
)

rechnungen (
  id, rechnung_nr VARCHAR(20) UNIQUE,   -- R-2026-00001
  auftrag_id INT FK,
  nettobetrag DECIMAL(10,2),
  steuerbetrag DECIMAL(10,2),
  bruttobetrag DECIMAL(10,2),
  faellig_am DATE NULL,
  storniert BOOL DEFAULT 0,
  storno_von INT FK NULL,               -- bei Gutschrift: welche Rechnung
  erstellt_am, erstellt_von INT FK benutzer
)

auftrag_dokumente (
  id, auftrag_id INT FK,
  typ ENUM(auftragsbestaetigung, lieferschein, rechnung, gutschrift, mahnung),
  dateiname VARCHAR(255),
  erstellt_am, erstellt_von INT FK benutzer
)

auftrag_statuslog (
  id, auftrag_id INT FK,
  felder_geaendert JSON,
  notiz TEXT,
  erstellt_am, erstellt_von INT FK benutzer
)
```

## WooCommerce-Import

Beide Modi geplant, pro Kanal konfigurierbar:
- **Manuell**: Button "Jetzt importieren" + Timestamp letzter Import
- **Automatisch**: Cronjob alle X Minuten (Standard: 10 Min.)
- Cronjob macht beides: Aufträge holen (Pull) + Lagerbestand pushen (Push)
- Bestand-Push ist kritisch für Kassen-Warnung "Artikel online gekauft"

## Packplatz

Eigene Seite `public/packplatz/` (analog Wareneingang als eigenes Modul).
Vollbild, Tablet/Touch-freundlich, Charge-Auswahl, Abschluss → "versandbereit".

## Template-System (Dokumente)

```
erp/templates/dokumente/
├── rechnung/standard.html.twig
├── lieferschein/standard.html.twig
├── auftragsbestaetigung/standard.html.twig
└── mahnung/standard.html.twig
```
Variablen: {firma}, {auftrag}, {positionen}, {kunde}, {summen}
Engine: Twig + Dompdf (reines PHP, kein externes Binary)

## Fehlbestand-Flow

Auftrag eingeht → Bestand < Menge → lieferstatus='zurueckgestellt'
→ Einkauf sieht Fehlbestand → Bestellung beim Lieferanten
→ Wareneingang → ERP löst zurueckgestellte Aufträge auf (FIFO nach erstellt_am)

## Auftragsliste-Features

- Kanal-Chip (woocommerce / manuell / kasse) sichtbar in der Liste
- Filterbox: Kanal, Zahlungsstatus, Lieferstatus, Datum, Kunde
- Spalten-Picker (wie Artikelliste)

## ✅ Rabatt-Design FERTIG (2026-07-10) — anderer Ansatz als ursprünglich notiert

Ursprünglich geplant war eine DB-Erweiterung (`rabatt_typ` ENUM + `rabatt_betrag`-Spalte). Beim Umsetzen stattdessen das schon bewährte Muster der Kasse übernommen (dort löst der globale Bon-Rabatt-Dialog "€ Neuer Gesamtpreis" dasselbe Problem): **kein neues DB-Feld** — `auftrag_positionen.rabatt_prozent` bleibt die einzige Quelle.

**`js/auftraege_neu.js`:** neuer "€"-Button neben dem %-Rabatt-Feld pro Position → `rabattEurEingeben(idx)` fragt (per `prompt()`, bewusst schlank statt eigenes Modal für dieses selten genutzte Feature) den gewünschten €-Rabattbetrag ab, rechnet ihn sofort in den äquivalenten %-Satz um und trägt ihn ins bestehende %-Feld ein — alles Nachgelagerte (Speichern, PDF-Vorlagen, `aktualisiereZeile()`) unverändert, da am Ende weiterhin nur `rabatt_prozent` ankommt. Gilt automatisch auch in `bearbeiten.php` (teilt sich dieselbe JS-Datei).

**Why (Ansatzwechsel):** Deutlich weniger Aufwand (keine Migration, keine Anpassung an allen Lesestellen), und die Kasse hat exakt dasselbe Problem schon einmal gelöst — Konsistenz zum bestehenden Muster war Jacky wichtiger als eine explizit gespeicherte "war ein €-Rabatt"-Information.
**How to apply:** Falls später doch die explizite €-Betrag-Speicherung gebraucht wird (z.B. für Reporting "wie viel € Rabatt insgesamt gewährt"), lässt sich das nachrüsten — aktuell nicht gebaut, da kein konkreter Bedarf dafür genannt wurde.

## Versandklassen-Features (offen)

- **Teillieferung als Versandoption**: eigene Versandklasse mit Aufpreis (z.B. „Versand + Teillieferung AT"), wählbar im Shop/Auftrag
- **Versandkostenfrei ab X**: in system_einstellungen, pro Shop aktivier-/deaktivierbar + Betrag einstellbar
