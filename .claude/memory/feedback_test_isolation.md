---
name: feedback-test-isolation
description: "Beim Debuggen keine Scratch-Testskripte gegen echte Artikel/Kassen in Jackys Dev-DB laufen lassen, ohne danach aufzuräumen"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: db02ffa8-aab5-44a1-a954-8cc195e7d369
---

Beim Debuggen von Backend-Logik (z.B. `MesseSyncService`) wurden Scratch-Testskripte direkt gegen echte Artikel (z.B. Artikel 245 "DROPS Karisma silberrosa") und Jackys reale Test-Kasse ("Messe-Laptop (Test)", id=2) ausgeführt — inklusive echter Lagerbuchungen (`umbuchungZurMesse`, `rueckkehrVerarbeiten`), ohne die Test-Daten danach zurückzusetzen.

Jacky bemerkte die Kontamination selbst beim nächsten Test ("Lagerstand beim Artikel um 1 erhöht, aber nirgends auffindbar... Chargen wieder beim Teufel") — was wie ein neuer Bug aussah, war tatsächlich mein eigener Testrückstand.

**Why:** Jackys Dev-Datenbank enthält echte, für ihn nachvollziehbare Testdaten (Lagerbewegungen mit Datum/Referenz, die er selbst beim Debuggen liest). Ungetilgte Scratch-Test-Schreibvorgänge sehen für ihn identisch aus wie echte Bugs und kosten ihn Zeit beim Diagnostizieren — er kann nicht unterscheiden "das ist mein Fehler beim Klicken" von "das ist Claudes Testrückstand von vorhin".

**How to apply:** Wenn eine Backend-Methode direkt (PHP-CLI, nicht über die echte UI) getestet werden muss:
- Wenn möglich einen dedizierten Test-Artikel/Test-Kasse verwenden statt echte Produktionsdaten (z.B. `artikelnummer LIKE 'TEST-%'`), oder
- Nach jedem Testlauf explizit zurückrechnen (Lagerbestand, `lager_bewegungen`, abhängige Sync-/Umbuchungs-Tabellen) und das auch tun, BEVOR der nächste inhaltliche Schritt beginnt — nicht erst wenn Jacky die Verwirrung meldet.
- Bei Unsicherheit, ob eine bestehende DB-Zeile (Kasse, Lager, Artikel) "echt" oder für den Test angelegt ist: nachfragen statt löschen (siehe generelle Vorsicht bei destruktiven Aktionen).

Siehe [[project_kassen_verwaltung]] für den konkreten Vorfall und die Bereinigung.
