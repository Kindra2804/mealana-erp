---
name: bug-kasse-charge-unbekannt
description: "2026-09-30 behoben — Kasse verlangte bei Chargenpflicht \"Charge im Wareneingang eintragen\", obwohl Bestand da war (zwei Ursachen)"
metadata:
  node_type: memory
  type: project
  originSessionId: 3204dc86-54bf-4800-9ff8-19338a0b843e
  modified: 2026-09-30T12:29:45.139Z
---

Jacky fand beim Gutschein-Test ([[project_gutscheine]]): Chargenpflicht-Artikel an der Kasse → leeres Chargen-Popup mit "bitte zuerst Charge im Wareneingang eintragen". Der Nachtrage-Weg aus [[project_chargen_konzept]] griff nicht.

**Ursache 1:** `KassenService::getAlleChargenFuerKasse()` nahm nur `charge IS NOT NULL OR charge_status='nachzutragen'`. 527 Chargenpflicht-Artikel (3.122 Stk.) hatten Bestand mit `charge NULL, charge_status='unbekannt'` — ohne jede Lagerbewegung, also direkt eingespielt vom JTL-Lagerbestand-Import 2026-08-11 ([[project_jtl_kunden_auftraege_import]]). Entsteht auch, wenn charge_pflicht nachträglich gesetzt wird. Fix: bei charge_pflicht zählt auch 'unbekannt', Zeilen ohne Charge kommen als 'nachzutragen' zurück → Popup zeigt Chargennummer-Feld, erstelleBon() bucht per chargeNachtragen() um.

**Ursache 2 (vermutlich der eigentliche Treffer):** Varianten-Auswahl (`getKinderFuerKasse`) und Textsuche liefern Kurzdaten OHNE alle_chargen/hat_chargen → Popup bei Kind mit Chargenpflicht IMMER leer, auch mit benannten Chargen. Fix: `artikelVollstaendigHinzufuegen()` in bon.php lädt vor dem Hinzufügen per ajax_artikel.php nach (wie beim Scannen).

Getestet: findArtikelByCode an BC-011010173-12 (unbekannt) und D-101071 (3 Chargen), node --check. Kein Klicktest.

**Nachgezogen (Jackys OK, gleicher Tag):** Migration 178 setzt Pflicht + keine Charge + Bestand > 0 von 'unbekannt' auf 'nachzutragen' (527 Zeilen). Die 9.762 Leerzeilen (Bestand <= 0) aus demselben Import bewusst NICHT, sonst wäre die Nachtragsliste mit ~10.300 Einträgen geflutet. `findNachzutragendeChargen()` filtert zusätzlich auf bestand > 0. Importskript `scripts/jtl_eigener_export_import.php`: `chargeStatus()` = gleiche Regel wie LagerService::wareneingang() (+ Bestand > 0); hebt bestehende 'unbekannt'-Zeilen beim Re-Import an, stuft 'nachzutragen'/'erfasst' nie zurück. Dry-Run mit echter CSV vom 11.08. fehlerfrei.

**Nebenbefund, nicht behoben:** `DokumentService` (~Zeile 318, Rückbuchung bei Gutschrift/Rechnungskorrektur) schreibt lagerbestand direkt: `INSERT (lager_id, artikel_id, bestand) ON DUPLICATE KEY UPDATE` mit charge NULL -- greift wegen NULL != NULL nie, legt bei jeder Gutschrift eine NEUE Null-Charge-Zeile an, Status Default 'unbekannt'. Sollte über LagerService::wareneingang() laufen. Jacky gemeldet.

Packplatz-`chargen_ajax.php` zeigt nur benannte Chargen (eigener Ablauf, unverändert).
