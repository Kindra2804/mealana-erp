---
name: feedback-test-isolation
description: "Beim Debuggen keine Scratch-Testskripte gegen echte Artikel/Kassen in Jackys Dev-DB laufen lassen, ohne danach aufzuräumen"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: db02ffa8-aab5-44a1-a954-8cc195e7d369
  modified: 2026-07-31T20:03:29.771Z
---

Beim Debuggen von Backend-Logik (z.B. `MesseSyncService`) wurden Scratch-Testskripte direkt gegen echte Artikel (z.B. Artikel 245 "DROPS Karisma silberrosa") und Jackys reale Test-Kasse ("Messe-Laptop (Test)", id=2) ausgeführt — inklusive echter Lagerbuchungen (`umbuchungZurMesse`, `rueckkehrVerarbeiten`), ohne die Test-Daten danach zurückzusetzen.

Jacky bemerkte die Kontamination selbst beim nächsten Test ("Lagerstand beim Artikel um 1 erhöht, aber nirgends auffindbar... Chargen wieder beim Teufel") — was wie ein neuer Bug aussah, war tatsächlich mein eigener Testrückstand.

**Why:** Jackys Dev-Datenbank enthält echte, für ihn nachvollziehbare Testdaten (Lagerbewegungen mit Datum/Referenz, die er selbst beim Debuggen liest). Ungetilgte Scratch-Test-Schreibvorgänge sehen für ihn identisch aus wie echte Bugs und kosten ihn Zeit beim Diagnostizieren — er kann nicht unterscheiden "das ist mein Fehler beim Klicken" von "das ist Claudes Testrückstand von vorhin".

**How to apply:** Wenn eine Backend-Methode direkt (PHP-CLI, nicht über die echte UI) getestet werden muss:
- Wenn möglich einen dedizierten Test-Artikel/Test-Kasse verwenden statt echte Produktionsdaten (z.B. `artikelnummer LIKE 'TEST-%'`), oder
- Nach jedem Testlauf explizit zurückrechnen (Lagerbestand, `lager_bewegungen`, abhängige Sync-/Umbuchungs-Tabellen) und das auch tun, BEVOR der nächste inhaltliche Schritt beginnt — nicht erst wenn Jacky die Verwirrung meldet.
- Bei Unsicherheit, ob eine bestehende DB-Zeile (Kasse, Lager, Artikel) "echt" oder für den Test angelegt ist: nachfragen statt löschen (siehe generelle Vorsicht bei destruktiven Aktionen).

Siehe [[project_kassen_verwaltung]] für den konkreten Vorfall und die Bereinigung.

## Nachtrag 2026-07-31: geteilte globale Stammdaten (z.B. Achsen) sind noch gefährlicher als eigene Testzeilen

Beim JTL-Import-Testen wiederholt `DELETE FROM varianten_achsen WHERE code='staerke'` als letzten Cleanup-Schritt verwendet — anfangs sicher, weil die Achse rein synthetisch war. Nachdem Jacky zwischendurch den echten KnitPro-Import gefahren hatte, nutzten test-eigene Kind-Artikel dieselbe **globale** Achse (`findByCode()`-Wiederverwendung), da der Code schon existierte. Der pauschale `DELETE FROM varianten_achsen WHERE code=...` hätte damit eine von 83 echten Vater-Artikeln verwendete Achse gelöscht — nur eine FK-Constraint-Sperre (artikel_achsen referenziert die Achse noch) hat das verhindert, kein eigenes Verschulden.

**Why:** Anders als bei rein artikel-gescopten Testzeilen (`artikelnummer LIKE 'ZZTEST%'`) sind Achsen (und vermutlich Kategorien, Hersteller, Einheiten, Steuerklassen — alles globale Stammdaten) NICHT pro Testlauf isoliert. Ein Cleanup-Skript, das zu Sessionbeginn sicher war, wird unsicher, sobald reale Daten in derselben Session denselben globalen Datensatz zu nutzen beginnen.

**How to apply:** Bei Cleanup nach CLI-Testläufen NIE pauschal nach `code`/`name` auf globalen Stammdaten-Tabellen löschen. Stattdessen vor dem Löschen einer globalen Zeile (Achse, Kategorie, Hersteller, Einheit...) per LEFT JOIN prüfen, ob noch andere (nicht-Test-)Artikel-IDs darauf verweisen — nur löschen wenn wirklich verwaist. Bei jedem DB-Cleanup-Statement kurz gedanklich prüfen: "Ist das WIRKLICH nur meine Testzeile, oder könnte das inzwischen von echten Daten mitbenutzt werden?"
