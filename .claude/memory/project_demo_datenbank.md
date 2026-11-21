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

**Offen (nächste Schritte):**
2. Artikel: Kategorien, Hersteller, Lieferanten, Garne mit Farb-Varianten + Chargen, Stoffe als Meterware, Kurzwaren, erzeugte Produktbilder (GD), Preise, EAN, Bestand, Kanal-Zuordnung. Kunden (verschlüsselt über Encryption).
3. Bewegungen 12 Monate (Saisonkurve): Aufträge aller Kanäle, Rechnungen, Zahlungen, Kassenbons + Z-Bons, Mahnungen, Retouren/Widerrufe, Lieferantenbestellungen/Wareneingang, Gutscheine, Newsletter, Partner/Händler.
4. Nächtlicher Reset + stündlich „neue Shop-Bestellung“; Webspace (PHP 8 + MySQL + Cron) klären.

**Teaser-Webseite** (danach): statisches HTML/CSS auf Indranet, Vorbild bonitnexus.at; Kopf NAHTLOS + „in Entwicklung, im Echtbetrieb“, Für wen, Modul-Kacheln, Screenshots aus der Demo (Playwright), vorläufige Preise (Grundmodul 790 €, [[project_lizenzierung_preise]]), Kontakt.
