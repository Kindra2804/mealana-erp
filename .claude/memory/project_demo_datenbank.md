---
name: project-demo-datenbank
description: "Demo-Installation „Garnstube Muster GmbH“ (Wolle+Stoffe) für Präsentationen; Schritt 1 Demo-Schalter + lokale Installation ✅ 2026-11-21, Generator-Daten folgen; danach Teaser-Webseite"
metadata:
  node_type: memory
  type: project
  originSessionId: 65ed2551-8447-4149-9e7e-963e5751c7e0
  modified: 2026-11-21T17:14:47.816Z
---

Reihenfolge (Jacky ok): [[project-zahlungseingaenge]] ✅ → Demo-DB (läuft) → Teaser-Webseite. Regel: [[feedback-demo-nachziehen]].

**Entscheidungen 2026-11-21:** erfundene Firma **Garnstube Muster GmbH** (Musterplatz 1, 4020 Linz, ATU12345678), Wolle + Stoffe (Meterware); **fester Demo-Login demo/demo** (vorausgefüllt, Rolle superadmin); Kanäle Garnstube Online (S1, Hauptshop), Stoffzimmer Online (STZ), Sockenliebe (SOL); Lager Ladengeschäft/Zentrallager/Messestand; Kasse K1 ohne BFR.

**Schritt 1 ✅ (gebaut + getestet 2026-11-21):**
- `src/core/Demo.php`: Schalter = Datei `config/demo.php` ('aktiv', 'login', 'passwort'; gitignored). Gesperrte Seiten (Update, Einrichtung, Lizenz einspielen, Benutzer speichern, Rollen, Einstellungen speichern, Importe; Profil nur POST) über `Auth::pruefeSeite`. Stumm: `Mailer::sende` (tut so, als wäre raus), BFR httpGet/Post → null, Paperless + Newsletter-Portal → Fehler, WooCommerceClient alle 4 curl-Stellen → Exception.
- bootstrap.php: Demo → `session_name('NAHTLOSDEMO')` (sonst teilen localhost/mealana und /nahtlos-demo die Sitzung!). Banner in shell_top, Login-Hinweis + Vorbelegung, kein Assistent nach Login.
- Generator `scripts/demo/demo_erzeugen.php` (+ DemoBasis, DemoStammdaten): bricht ohne Demo ab, löscht alle Tabellen, Baseline + Migrationen, leert Upload-/Belegordner, fester mt_srand, Datum relativ zu heute. Demo-Lizenz `scripts/demo/demo.lic` (L-DEMO-0001, alle Module, Wartung 2035).
- Lokal: `D:\ERP\nahtlos-demo\erp`, DB `nahtlos_demo`, Junction `htdocs\nahtlos-demo`, Abgleich `tools/demo/lokal_aktualisieren.ps1` (robocopy /MIR; /XF nur mit vollem Pfad — „demo.php“ traf sonst src/core/Demo.php). Anleitung `tools/demo/README.md`.
- Klicktest `C:\Users\indy1\.claude-browser-tools\demo_schalter_test.js`.

**Schritt 2 ✅ (2026-11-21):** `DemoKatalog` (Sortiment, erfundene Hersteller „… Muster/Beispiel“), `DemoArtikel` (Warengruppen 4010–4040, Kategorien + kategorie_shops, Hersteller=Lieferanten, Achsen Farbe/Stärke, Väter+Kinder direkt per SQL — NICHT über VariantenService, der loggt mit Session; Preise, EAN-13 Präfix 200, Merkmale, artikel_shops synced, Startbestand Zentrallager 75 % / Laden 25 % mit Charge, gemerkt in `$b->ids['verkauf']`), `DemoBilder` (GD, nur Formen; KEIN imageantialias, sonst ignoriert GD die Strichstärke), `DemoKunden` (653 über KundenRepository verschlüsselt, @example.com, 3 Händler). Meterware: 1 Stk = 1 cm, VK je m in vollen Euro (Preis je cm hat nur 2 Nachkommastellen). Lauf ~33 s. Ansicht-Skript `.claude-browser-tools/demo_ansicht.js` (in Git Bash mit MSYS_NO_PATHCONV=1 aufrufen!).

**Schritt 3 ✅ (2026-11-21):** `DemoVerkauf`: 365 Tage bis heute, Saison (Wolle Winter, Baumwolle Sommer, Stoff Frühjahr) × Wachstum; ~2.000 Online-Bestellungen (3 Shops, PayPal/Vorkasse/Nachnahme, alle Zwischenstände, unbezahlte Vorkasse → Erinnerung 14 T/Storno 30 T, Widerrufe abgewickelt + 2 beantragt), ~100 Telefon-/Händleraufträge auf Rechnung (6 % zahlen nie → Mahnvorschlag), ~3.800 Kassenbons Mo–Sa mit Spiegel-Auftrag wie KassenService + Z-Bons über echten `KassenService::erstelleZBon` (Zeit danach auf 18:35 gesetzt), montags Nachbestellung, Wareneingang 4–9 T mit neuer Charge, Lieferantenrechnung bezahlt nach 8–28 T. Nummern über `DokumentRepository::naechsteNummer($typ, $jahr, $kuerzel)`; Rechnungen OHNE PDF-Datei (Download rendert immer aus der DB). Logbuch für letzte 3 Tage befüllt. Lauf ~57 s. Umsatz ~25–38 k€/Monat.

**ERP-Fund (nicht geändert, Jacky gemeldet):** Statistik „Topseller nach Menge“ zählt Meterware in cm → Stoffe stehen mit 2000+ ganz oben (betrifft auch MeaLana).

**Offen (nächste Schritte):**
3b. evtl. noch Gutscheine, Newsletter, Partner/Mietfächer, Händler-Konsignation, Inventur, Messe in die Demo.
4. Nächtlicher Reset + stündlich „neue Shop-Bestellung“; Webspace (PHP 8 + MySQL + Cron) klären.

**Teaser-Webseite** (danach): statisches HTML/CSS auf Indranet, Vorbild bonitnexus.at; Kopf NAHTLOS + „in Entwicklung, im Echtbetrieb“, Für wen, Modul-Kacheln, Screenshots aus der Demo (Playwright), vorläufige Preise (Grundmodul 790 €, [[project_lizenzierung_preise]]), Kontakt.
