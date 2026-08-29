---
name: project-gutscheine
description: "Gutschein-Modul Design KOMPLETT (2026-08-29): ERP als Single Source of Truth, on+offline, WooCommerce-Sync als Slave, Design+Von/An/Grußtext via Shop-Gutschein-Artikel + Dokumente-System, Versand deckt auch Versandkosten. Planung fertig, Bau noch nicht gestartet."
metadata:
  node_type: memory
  type: project
  originSessionId: 40a29a40-0c3b-483d-82e6-51045de0676d
  modified: 2026-08-29T13:30:56.866Z
---

## ✅ Baustufe 1 GEBAUT 2026-08-29: Backend + ERP-UI komplett, getestet

Direkt im Anschluss an die Planungsrunde umgesetzt (Jacky: "dann kannst du mit dem bauen anfangen"). Kompletter Kern-Durchstich funktioniert end-to-end, alle Backend-Pfade mit echten Funktionstests verifiziert (nicht nur `php -l`):

**Migrationen 173+174:** `gutschein_vorlagen`/`gutscheine`/`gutschein_transaktionen` + FK auf das seit Migration 060 vorsorglich existierende `auftraege.gutschein_id` + neue `kassen_bons.gutschein_id`-FK (alte `gutschein_code`-Freitextspalte bleibt für historische Bons) + `artikel.ist_gutschein`-Flag + `system_einstellungen` (`gutschein_gueltigkeit_tage`=3650, `gutschein_mindestbetrag_shop`=10.00) + `gutscheine.kanal_line_item_id` (Idempotenz-Tracking für den Shop-Kauf-Pfad).

**`GutscheinRepository`/`GutscheinService`** (`src/modules/gutscheine/`): `erstelleGutschein()`, `einloesen()` (inkl. automatischer Neu-Code-Erzeugung bei Teileinlösung + Betrag-Cap auf Restguthaben + Ablauf-/Storno-/Vollständig-eingelöst-Prüfung), `spiegleZuWooCommerce()`, `versende()` (PDF+Mail), `generiereEindeutigenCode()` (Format `MEA-XXXX-XXXX-XXXX`, Alphabet ohne 0/O/1/I/L). **Echter Funktionstest bestanden:** 100€ erstellt → 30€ teileingelöst (via simuliertem `coupon_lines`-Aufruf) → korrekt neuer 70€-Code erzeugt, Transaktionen sauber, unbekannter Coupon-Code korrekt ignoriert.

**WooCommerceClient** um `erstelleCoupon()`/`aktualisiereCoupon()`/`sucheCouponNachCode()` erweitert (fixed_cart, usage_limit=1 fix -- siehe Race-Condition-Begründung im Code-Kommentar).

**`ShopBestellungSyncService`** (beide Richtungen, per Reflection-Test verifiziert):
- `verarbeiteGutscheinEinloesungen()`: liest `order.coupon_lines`, matched gegen bekannte Gutschein-Codes, bucht via `einloesen()`. Läuft NUR beim Erstimport (Idempotenz, coupon_lines ändert sich nach Bestellabschluss nicht mehr).
- `verarbeiteGutscheinKauf()`: erkennt `artikel.ist_gutschein=1`-Line-Items, erst wenn `zahlungsstatus='bezahlt'` (läuft bei JEDEM Poll, idempotent über `kanal_line_item_id`). Liest Personalisierung aus `_mealana_gutschein`-Line-Item-Meta (JSON, analog `_mealana_konfig` beim Konfigurator -- **Checkout-Snippet dafür noch nicht gebaut**, Feld-Konvention aber im Code dokumentiert). Menge>1 erzeugt mehrere Einzelcodes. Sofortversand außer bei gewähltem `zustellung_am`.

**Dokumente-System:** `gutschein/standard.html.twig` (Karten-Design mit Hintergrundbild-Variable, Logo, Betrag/Code/Gültig-bis-Box, Empfänger+Grußtext) + `DokumentService::erstelleGutscheinPdf()`. **Per echtem PDF-Test bestätigt** (Screenshot-Qualität geprüft) -- funktioniert auch OHNE Design-Hintergrundbild (Jacky muss die 8 JTL-Vorlagenbilder noch hochladen, `gutschein_vorlagen` ist leer).

**Mail:** `templates/mails/gutschein_versand.html.twig` (unterscheidet "X hat dir geschenkt" vs. Direktversand an Käufer).

**Cron:** `cron/gutschein_versand.php` (nur für geplante `zustellung_am`-Zustellungen -- Soforversand läuft direkt am Entstehungspunkt, nie über den Cron).

**ERP-Oberfläche** (`public/gutscheine/`): `liste.php` (Filter Status/Suche), `neu.php` (Formular inkl. Design-Auswahl/Versandart-Toggle/JS), `speichern.php`, `detail.php` (Transaktions-Historie, PDF-Download, manuelles Einlösen-Formular für Telefon-/Laden-Bestellungen, "Jetzt versenden"-Button), `einloesen.php`, `versenden.php`, `pdf_download.php`. In `shell_top.php` als eigenes Modul unter "Verkauf" eingehängt. **Kein Browser-Test** (kein ERP-Login in dieser Session) -- nur `php -l` + 302-Redirect-Check (kein Fatal Error) verifiziert.

**Bewusst NICHT auf Zugriffsregeln.php eingetragen** -- fehlender Eintrag = nur Login-Pflicht (dokumentiertes, sicheres Fallback-Verhalten dieser Datei). Granulare Berechtigungen (`gutscheine.anzeigen` etc.) erst nachziehen, wenn Jacky die Rollen-Zuordnung entschieden hat -- ein blind eingetragener, nirgends gewährter Berechtigungs-String hätte sonst RISIKO eines Lockouts (auch für Admin).

**`legeReservierungenAn()`** (AuftragRepository) erweitert: `ist_gutschein=1`-Artikel werden wie `keine_lagerbestandsfuehrung=1` von der Lagerreservierung ausgenommen.

### 🔲 Noch offen (klar benannt, nicht vergessen)

1. **Kasse-UI-Anbindung** -- der bestehende "Gutschein"-Zahlungsart-Knopf in der Kasse prüft aktuell nur `code.length>=3`, ist noch NICHT an `GutscheinService::einloesen()` angebunden. Braucht Live-Test in der Kasse.
2. **Shop-seitiges WPCode-Snippet** -- Checkout-Felder am Gutschein-Artikel (Betrag/Empfänger/Zustellung/Grußtext → `_mealana_gutschein`-Meta), Versand-inklusive Rabattlogik (eigene Verrechnung Warenwert+Versand statt WC's binärem "Kostenloser Versand"-Haken), Teileinlösung-Checkout-Hinweis. Braucht zuerst einen echten "Shop-Gutschein"-Artikel in der ERP-DB (SKU "GUTSCHEIN", `ist_gutschein=1`) -- noch nicht angelegt.
3. **Kasse-Erstattung via Gutschein** (bei abholbereit+bezahlt, Kunde nimmt weniger) -- weiterhin nur geplant, siehe unten.
4. **Design-Vorlagen** -- `gutschein_vorlagen` ist leer, Jacky muss die 8 JTL-Bildvorlagen (oder neue) hochladen.
5. **Buchhaltungs-Konto** -- Gutschein-Anzahlungskonto-Nummer kommt noch von Babsi (siehe [[project_buchhaltung]]).
6. **Zugriffsregeln** -- granulare Berechtigungen nachziehen sobald Rollen-Zuordnung klar ist.
7. **Browser-Test** der neuen ERP-Seiten steht aus.

**How to apply beim Wiedereinstieg:** Backend ist fertig und verifiziert -- NICHT nochmal neu bauen. Bei Punkt 1 oder 2 weitermachen, je nachdem was Jacky zuerst braucht (Kasse-Verkauf vs. Online-Verkauf). Diese Liste als Fortschritts-Checkliste nehmen.

## Status 2026-08-29 (Vormittag der Planung): Planung KOMPLETT abgeschlossen

Vollständige Planungsrunde mit Jacky (Referenz-Check JTL-Shop-Screenshots + WAWI-Vergleich + Wireframe), löst die alte 2026-07-10-Zurückstellung ein ("in der Nähe der Online-Shop-Anbindung bauen" — die ist jetzt weit fortgeschritten). Alte Annahme "kein Design/Von-An im WC möglich, nackter Code reicht" ist **überholt** — der Shop-Gutschein-Artikel-Ansatz (siehe unten) löst das sauber.

## Konzept: ERP = Single Source of Truth, WooCommerce nur Slave (Code-Spiegel)

WooCommerce hat kein natives Wertgutschein-System (nur Rabatt-Coupons, kein Restguthaben-Tracking, kein Design/Von-An-Feld). ERP verwaltet alle Gutscheine zentral (Betrag, Restguthaben, Status, Design, Personalisierung) — zu WooCommerce geht nur der nackte, technische Code als `fixed_cart`-Coupon mit `usage_limit=1`.

**WAWI-Referenzvergleich (2026-08-29):**
| System | Ansatz | Erkenntnis für uns |
|---|---|---|
| JTL-Shop (Screenshots von Jacky) | Frei wählbarer Betrag ab Mindestwert, 8 Design-Vorlagen zur Auswahl, geplantes Zustelldatum, Empfänger-Feld getrennt vom Käufer, "versenden" vs. "selbst ausdrucken" | 1:1-Vorbild für den Checkout-Teil, siehe Feldliste unten |
| Odoo (Sales/eCommerce) | Feste ODER freie Beträge, Restguthaben-Tracking bei Teileinlösung | Bestätigt unser Restguthaben-Modell als Branchenstandard |
| Shopware 6 | Gutscheine nativ schwach, meist Zusatz-Plugins nötig | Bestätigt: WooCommerce als reiner Slave ist richtig, nicht auf Bordmittel verlassen |
| LS Central/POS (MeaLana-Benchmark) | Omnichannel-Guthaben, eine Quelle der Wahrheit über alle Kanäle | Genau unser Kernprinzip, keine Lücke |

**MeaLana-Extras gegenüber allen vier Referenzsystemen:** geplantes Zustelldatum (nur JTL hat das auch) UND die direkte Kasse-Erstattungs-Anbindung (Restbetrag bei Retoure automatisch als Gutschein statt bar) — bei keinem Referenzsystem so eng verzahnt, weil bei uns Kasse+Shop+ERP eine einzige Plattform sind.

## DB-Tabellen (Stand 2026-08-29, ersetzt die alte Skizze vom 07-10)

```sql
-- Reine DESIGN-Vorlagen, KEIN Betrag mehr (Betrag ist frei wählbar, siehe unten)
gutschein_vorlagen (
  id, name, hintergrundbild_pfad, aktiv, sort_order
)

gutscheine (
  id, code UNIQUE,
  vorlage_id FK NULL,           -- gewähltes Design
  betrag, restguthaben,          -- IMMER frei/dezimal, auch für Kasse-Überzahlungs-Erstattungen
  gueltig_bis DATE NULL,         -- Default: Kaufdatum + konfigurierbare Gültigkeitsdauer (Praxis-Standard 10 Jahre,
                                  -- siehe Rechtsfrage unten -- als System-Einstellung, NICHT hartcodiert)
  status ENUM(aktiv, teilweise, eingeloest, abgelaufen, storniert),
  kunden_id FK NULL,              -- Käufer, wenn bekannt
  empfaenger_name, empfaenger_email,   -- NEU: Personalisierung
  zustellung_am DATE NULL,        -- NEU: geplantes Versanddatum, NULL = sofort
  versandart ENUM(versenden, selbst_ausdrucken),  -- NEU: steuert WER die Mail bekommt
  grusstext TEXT NULL,            -- NEU: Freitext fürs PDF
  woo_coupon_id INT NULL,         -- gespiegelte WC-Coupon-ID
  kanal_erstellt ENUM(kasse, erp, woocommerce, manuell),
  ausgestellt_von FK benutzer,
  erstellt_am, aktualisiert_am
)

gutschein_transaktionen (
  id, gutschein_id FK,
  auftrag_id FK NULL,
  betrag,          -- negativ = Einlösung, positiv = Erstattung
  kanal ENUM(kasse, erp, woocommerce),
  notiz,
  benutzer_id FK, erstellt_am
)
```

**Bei "selbst_ausdrucken":** `empfaenger_name`/`empfaenger_email` bleiben trotzdem gespeichert (fürs Design/Grußtext relevant), es geht nur KEINE zweite Mail an den Empfänger raus — nur der Käufer bekommt PDF+Mail.

## Checkout-Formular am Shop-Gutschein-Artikel (1:1 nach JTL-Vorbild, Jacky-Screenshot 2026-08-29)

```
┌─────────────────────────────────────┐
│  [Vorschaubild]      Shop-Gutschein  │
│  [Gutschein auswählen] [Vorschau]    │  ← 1 von N Design-Vorlagen (gutschein_vorlagen)
│  Gewünschter Betrag                  │
│  [ 10________________ ] €  (min 10€) │  ← frei, Mindestwert 10€ (Shop-UI-Constraint,
│                                       │     DB/ERP-Feld selbst bleibt IMMER frei)
│  Gutschein versenden                 │
│  [ Versenden ▾ ]                     │
│    ├─ Versenden        (Mail an Empf.)│
│    └─ Selbst ausdrucken (Mail an mich)│
│  ── nur wenn "Versenden" gewählt ──  │
│  Name des Empfängers   [____________]│
│  E-Mail des Empfängers [____________]│
│  Zustellung am         [____________]│
│  Ihr Grußtext                        │
│  [                                 ] │
└─────────────────────────────────────┘
```

## Datenfluss (alle drei Entstehungswege münden im selben Mechanismus)

```
Kasse ─┐
       │  betrag, kunde?, design?
ERP ───┼──► gutscheine (code, restguthaben, status, kanal_erstellt)
       │         │
Shop ──┘         ├──► Dokumente-System (Twig+Dompdf, wiederverwendet -- KEIN neuer Code-Pfad)
  (Gutschein-        EIN generisches Template `gutschein/standard.html.twig`,
   Artikel-           Hintergrundbild aus gutschein_vorlagen als Variable
   Bestellung,        (nicht 8 einzelne Templates -- neues Design = nur Bild hochladen)
   via bestehendem    → PDF mit Code/Betrag/Gültig-bis/Grußtext als Overlay
   Bestellungs-Sync        │
   importiert)             ├─ "Versenden"         → Mail an Empfänger
                           └─ "Selbst ausdrucken"  → Mail/PDF an Käufer
                         │
                         └──► WooCommerce-Coupon-Spiegel (NUR Code, kein Design,
                                fixed_cart, usage_limit=1)
                                    │
                              Einlösung im Shop-Checkout
                                    │
                         Bestellungs-Sync erkennt Coupon in coupon_lines
                                    │
                         gutschein_transaktionen (Abbuchung um tatsächlich
                         abgezogenen Betrag)
```

## Teileinlösung: neuer Code ist technisch ZWINGEND, nicht nur schöner

`usage_limit=1` ist bewusst so gewählt: der Bestellungs-Sync **pollt** (kein Webhook, ERP hat keinen öffentlichen Endpunkt, siehe [[project_shop_sync]]). Würde derselbe Code mit reduziertem Restwert weiterleben, gäbe es ein Zeitfenster zwischen Einlösung und nächstem Sync-Lauf, in dem der Code nochmal mit dem ALTEN (noch vollen) Betrag verwendet werden könnte -- Doppel-Einlösung/Race-Condition. `usage_limit=1` macht den Code sofort tot (WooCommerce-nativ, unabhängig von unserer Sync-Timing), der neue Code für den Rest entsteht erst NACHDEM wir den tatsächlichen Restbetrag berechnet haben (aus `coupon_lines[].discount` der synchten Bestellung, verglichen mit `gutscheine.restguthaben`).

**Checkout-Hinweis bei Teileinlösung (Jackys Wunsch, erspart Support-Anrufe):** Läuft OHNE Wartezeit auf den ERP-Sync -- der gespiegelte WC-Coupon trägt bereits den aktuellen Restbetrag als Wert. Direkt beim Anwenden des Codes im Checkout (noch vor Bestellabschluss) wird Coupon-Wert vs. Bestellsumme (Ware+Versand) verglichen; ist der Coupon höher, Hinweis: *"Dieser Gutschein deckt mehr als diese Bestellung -- der Restbetrag von X € wird dir in Kürze per E-Mail als neuer Code zugeschickt."* Reine Shop-seitige Logik (WPCode-Snippet), kein ERP-Roundtrip nötig für die Anzeige selbst.

## Gutschein deckt auch Versandkosten (Jackys Anforderung 2026-08-29)

**Nicht** über WooCommerce's eingebauten "Kostenloser Versand"-Coupon-Haken lösen -- der macht Versand binär komplett gratis sobald IRGENDEIN gültiger Gutschein-Code angewendet ist, unabhängig vom Restbetrag (bei nur noch 1€ Restguthaben wäre das zu großzügig). Stattdessen eigene Rabattlogik (Snippet, gleiches Muster wie die Konfigurator-Preisberechnung): Gutschein-Betrag wird gegen Warenwert **plus** Versandkosten gemeinsam verrechnet, bis er aufgebraucht ist -- genau wie jeder andere Postenwert. Hintergrund: viele Kunden verschenken 100€-Gutscheine, die dann über mehrere kleine Bestellungen aufgebraucht werden -- diese sollen bei ausreichendem Restguthaben wirklich 0€ kosten (inkl. Versand), nicht nur der Warenwert.

## Rechtsfrage Gültigkeitsdauer (geklärt 2026-08-29, kein hartes Gesetz)

Jacky fragte ob "10 Jahre" (JTL-Vorbild) EU-Recht ist -- **nein, nicht exakt so**. Es gibt kein Gesetz das genau 10 Jahre vorschreibt. Hintergrund: allgemeine zivilrechtliche Verjährung in AT ist 30 Jahre (ABGB §1478), für viele unternehmerische Ansprüche 3 Jahre (§1486); Gerichte haben aber wiederholt KURZE Gutschein-Gültigkeiten (z.B. 1 Jahr) als sittenwidrig/unfair nach dem KSchG gekippt. Viele Händler setzen deshalb vorsorglich lange Fristen -- 10 Jahre ist ein verbreiteter sicherer Praxiswert, kein gesetzliches Minimum. **Als konfigurierbare System-Einstellung bauen** (Default 10 Jahre/3650 Tage), nicht hartcodiert. Bei Bedarf mit Steuerberater/Anwalt absegnen lassen, analog zu den offenen Buchhaltungskonten-Fragen.

## Steuerliche Behandlung (bereits korrekt verstanden, kein blinder Fleck)

Babsi bucht Gutscheine schon jetzt bewusst auf ein **"3er-Konto für Anzahlung ohne Steuer"** (siehe [[project_buchhaltung]]) -- das ist exakt die korrekte EU-Behandlung für "Mehrzweck-Gutscheine" (Steuer erst bei tatsächlicher Einlösung fällig, nicht beim Verkauf, weil zum Verkaufszeitpunkt die spätere Steuersatz-Zuordnung noch nicht feststeht). Genaue Kontonummer steht laut Buchhaltungs-Notiz noch aus (offene Detailfrage an Babsi) -- das Gutschein-Modul muss beim Bauen sauber auf dieses Konto einzahlen, sobald die Nummer da ist.

## Kasse-Erstattung via Gutschein (weiterhin geplant, unverändert)

Wenn Auftrag `abholbereit + bezahlt` und Kunde nimmt **weniger** als bestellt:
- Derzeit: Barauszahlung (Differenz bar zurück)
- Sobald Gutschein-Modul fertig: Kasse bietet automatisch Wahl an ("Bar zurückgeben" ODER "Gutschein ausstellen"), bei Gutschein-Wahl automatisch neuen Gutschein über Differenzbetrag, verknüpft mit Erstattungs-Bon (`kassen_bon_id`), personalisiert auf `kunden_id` wenn bekannt.
→ Muss mit der Abholbereit+bezahlt-Implementierung koordiniert werden.

**How to apply beim Wiedereinstieg:** Planung ist vollständig, alle offenen Fragen sind geklärt (Betragsmodell, Empfänger-Feld, Versand-Deckung, Rechtsfrage, Steuerfrage). Nächster Schritt: Migrationen ausformulieren + Wireframe in echtes HTML/Twig überführen (3-Stufen-Workflow, siehe [[feedback_design_workflow]]). Nicht nochmal von vorne planen.
