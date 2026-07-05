---
name: project-verkauf-workflows
description: "Verkauf-Geschäftsregeln: Zahlungsarten, Mahnprozess, Fehlbestand-Konzept"
metadata: 
  node_type: memory
  type: project
  originSessionId: c55c1aca-b514-4e20-98fa-732e6e1149b3
---

## Zahlungsarten

Hauptsächlich **Vorkasse / PayPal** — fast alle Kunden zahlen vor Versand.
Nur wenige **Stammkunden** zahlen auf Rechnung (Rechnungszahler).

**How to apply:** Aging-Logik und Mahnwesen betrifft primär die kleinen Anzahl Rechnungszahler.

## Offene Bestellungen — Mahnprozess (Rechnungszahler)

- **14 Tage+** ohne Zahlung → automatisches Erinnerungsmail an Kunden
- **30 Tage+** ohne Zahlung → Auftrag stornieren, Artikel wieder freigeben (Lagerbestand zurückbuchen)

**Status-Flags im UI:**
- Unter 14 Tage: kein Flag
- 14+ Tage, Mail noch nicht gesendet: "→ Erinnerung senden"
- 14+ Tage, Mail bereits gesendet: "✓ Mail gesendet"
- 30+ Tage: "⚠ Stornieren?" mit Option Auftrag stornieren + Artikel freigeben

**How to apply:** Diese Logik muss in den Auftrag-Workflow eingebaut werden. Dashboard-Widget zeigt Zusammenfassung mit Links. Stornierung + Artikelfreigabe müssen als Aktion im System verfügbar sein.

## 🔴 BUG (BEHOBEN 2026-07-05): cron/mahnwesen.php lief seit jeher NIE erfolgreich durch

Jacky bemerkte am Dashboard: "offene Kundenrechnungen fangen jeden Tag neu zu zählen an", eine mit Betrag 0 obwohl "offen", andere 5+ Tage alt trotzdem `<1` angezeigt — Verdacht: Aging/Mahnwesen kaputt. Nachschau ergab: **noch schlimmer als vermutet, der komplette Cronjob ist seit Erstellung nie einmal fehlerfrei durchgelaufen.**

**Drei fatale Schema-Drift-Bugs in `cron/mahnwesen.php` gefunden (PDO wirft bei jedem Query-Fehler eine Exception, das Skript stirbt beim allerersten Query ohne jede Aktion):**
1. `a.auftragsnummer` referenziert — die Spalte heißt `auftrag_nr`. Erster Query der Datei, damit ist das Skript nie über Zeile 1 der eigentlichen Logik hinausgekommen.
2. `LEFT JOIN kunden k ... k.email AS kunden_email` — `kunden.email` existiert nicht mehr im Klartext (AES-verschlüsselt seit dem Kundendatenbank-Umbau, echte Spalte ist `email_enc`). Fix: JOIN komplett entfernt, `kunden_snapshot` (JSON, bereits auf `auftraege` vorhanden) ist wie überall sonst im Code (`dashboard.php`, `dokumente/index.php`) die richtige Klartext-Quelle.
3. `INSERT INTO auftrag_status_log (..., aktion, ...)` — Tabelle heißt `auftrag_statuslog` (kein Unterstrich vor "log") mit komplett anderen Spalten (`felder_geaendert` JSON statt `aktion` Text). Fix: nutzt jetzt `AuftragRepository::logStatus()` statt eigenem SQL — eine Quelle der Wahrheit statt Doppelpflege.
4. Nebenbei: `mahnungen.typ` ENUM hatte `'hinweis'` (für den "Rechnung 30+ Tage, nur manuell prüfen"-Zweig) nie enthalten → auch dieser INSERT wäre fehlgeschlagen. Migration 111 ergänzt.
5. Nebenbei: `WHERE zahlungsstatus IN ('offen','ausstehend')` — `'offen'` ist kein gültiger ENUM-Wert (wirkungslos) und `'teilbezahlt'` fehlte komplett in der Liste → teilbezahlte überfällige Aufträge wurden nie geprüft.

**Getestet (isolierter Test-Auftrag `_TEST_MAHNWESEN`, künstlich auf 15 dann 31 Tage zurückdatiert, danach komplett wieder gelöscht inkl. mahnungen/auftrag_statuslog/aktivitaeten-Zeilen):** Erinnerungsmail-Zweig UND Auto-Stornierung-Zweig beide jetzt erfolgreich durchlaufen, korrekte DB-Endzustände (zahlungsstatus/lieferstatus='storniert', beide mahnungen-Zeilen, korrekter auftrag_statuslog-Eintrag).

**Separater, echter Datenbug gefunden+behoben:** `kasse/bon_speichern.php` verglich beim "nur Zahlung"/"Abholbereit"-Pfad `$auftragAnteil` (nur DIESE Kassen-Transaktion) statt der **kumulierten Summe aller `auftrag_zahlungen`** gegen `bruttobetrag`, um zwischen 'bezahlt'/'teilbezahlt' zu entscheiden — bei Rundungsdifferenzen zwischen Positions- und Kopfbetrag (oder mehreren Teilzahlungen) blieb der Auftrag für immer fälschlich auf 'teilbezahlt' stehen, obwohl vollständig bezahlt. Fix: kumulierte Summe (`SELECT SUM(betrag) FROM auftrag_zahlungen WHERE auftrag_id=?`) mit 1-Cent-Toleranz, exakt wie in `AuftragService::bucheZahlung()` bereits korrekt gemacht. Ein konkret betroffener Dev-Auftrag (A-2026-00016, 5€ komplett bezahlt aber 'teilbezahlt') wurde händisch auf 'bezahlt' korrigiert, DB-weiter Scan ergab keine weiteren betroffenen Aufträge.

**Dashboard-Widget zusätzlich präzisiert:** "Tage"-Spalte zeigte bisher Tage-nach-Fälligkeit (relativ zu `rechnungen.faellig_am` oder ersatzweise Bestelldatum+14 Tage) — bei jungen Vorkasse-Aufträgen ohne Rechnung war das bis zu 14 Tage lang negativ und wurde als `<1` angezeigt, obwohl der Auftrag schon 5+ Tage alt war (Ursache von Jackys "zählt jeden Tag neu"-Eindruck). Zeigt jetzt das echte Bestellalter — dieselbe Kennzahl, die `cron/mahnwesen.php` tatsächlich für die 14-/30-Tage-Schwellen verwendet (der Cron nutzt ohnehin nie `faellig_am`, nur das Bestelldatum). Zusätzlich: Widget + Zähler-Kacheln blenden jetzt Aufträge mit bereits ausgeglichenem Saldo (bezahlt ≥ bruttobetrag − 1 Cent) konsequent aus, als Verteidigungslinie falls doch nochmal ein Status-Rechenfehler auftritt.

**Why:** Der Cron war offenbar seit dem Kundendatenbank-Verschlüsselungs-Umbau (2026-06-19) nie wieder gegen echte überfällige Testdaten gelaufen — der Logger-Fix aus project_rksv_bfr (`$jarvisId` explizit übergeben) wurde vermutlich per Code-Review ergänzt, ohne das Skript je End-to-End auszuführen, sonst wäre der viel frühere `auftragsnummer`-Crash sofort aufgefallen.
**How to apply:** Bei jeder künftigen Schema-Änderung an `auftraege`/`kunden`/`auftrag_statuslog` aktiv prüfen, ob `cron/mahnwesen.php` (läuft unsichtbar im Hintergrund, kein Browser-Feedback) mitgezogen werden muss — idealerweise mit einem echten Testlauf, nicht nur Code-Lesen.

## Fehlbestand (Überverkauf)

**Definition:** Artikel mit "Überverkauf aktiviert" die von Kunden bestellt wurden, aber aktuell nicht am Lager sind — sie befinden sich auf Bestelllisten beim Lieferanten.

**Status-Stufen:**
1. Noch nicht bestellt (auf keiner Bestellliste)
2. Bestellt (auf offener Bestellung beim Lieferanten)
3. Im Zulauf (Bestellung bestätigt / Lieferung erwartet)

**Dashboard-Darstellung:** Kachel "Fehlbestand: X Stk. →" mit Link zur Fehlbestandsliste.
Fehlbestandsliste zeigt Artikel + Aufträge + Bestellstatus.

**How to apply:** Im Einkauf-Modul und Lager-Modul muss Fehlbestand prominent sichtbar sein. Beim Wareneingang automatisch den Fehlbestand auflösen und betroffene Aufträge auf Pickliste setzen.

## Offene Auswahl ("Mitgeben") — Stammkunden-Workflow

Pendant zu LS-POS "Offene Auswahl": Stammkunde nimmt Ware mit OHNE sofortige Bezahlung. Bringt später alles oder einen Teil zurück, erst dann wird der Bon/Rechnung erstellt.

**Ablauf:**
1. Kasse: "Mitgeben"-Button → Kundenzuweisung Pflicht (kein Laufkunde)
2. Positionen scannen → vorläufiger Datensatz (kein Bon, keine RKSV-Signatur)
3. Kunde kommt zurück → Rückgabe-Positionen erfassen (negativ)
4. Finalisieren → echter Bon + RKSV-Signatur + Rechnungsnummer

**How to apply:** An der Kasse als eigener Button "Mitgeben ▷" (Amber/Orange) neben BEZAHLEN. Kundenzuweisung ist Pflichtfeld. Die offene Auswahl ist kein Bon — erst bei Finalisierung wird RKSV-Signatur erzeugt. Liste offener Auswahlvorgänge muss in der Kasse abrufbar sein.
