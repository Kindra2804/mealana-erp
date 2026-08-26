---
name: project-meterware-mindestabnahme
description: "Meterware Mindestabnahme + Abnahmeintervall -- ✅ 2026-08-26 KOMPLETT FERTIG + LIVE BESTÄTIGT (Migration 167/168, Artikeltyp-Vorgabe + Artikel-Override, Shop-Sync-Meta-Felder inkl. Kundenhinweis-Text, WordPress-Snippet eingespielt, Screenshot-Bestätigung)"
metadata:
  node_type: memory
  type: project
  originSessionId: 1fe37890-2d54-4c96-bcae-65d709049cbe
  modified: 2026-08-26T13:17:40.631Z
---

Jacky zeigte am 2026-08-13 ein Beispiel vom alten JTL-Shop (mealana.at, Vlieseline H250, Meterware-Einlage): "Mindestabnahme 20 cm", "Abnahmeintervall 10 cm". Am 2026-08-26 umgesetzt.

**Entscheidungen (Jacky, 2026-08-26):**
- Eigener Code statt Fremd-Plugin (Grund: Lizenz-pro-Domain-Falle wie bei WoodMart vermeiden, siehe [[project_shop_theme]]).
- Vorgabe lebt am Artikeltyp (Default), einzelner Artikel kann überschreiben oder ganz abschalten -- nicht global erzwungen.

## ✅ GEBAUT 2026-08-26 (ERP-Seite)

**Datenmodell (Migration 167):**
- `artikel_typen`: `mindestabnahme_default`, `abnahmeintervall_default` (DECIMAL) -- seed 20/10 für METERWARE (Vlieseline-Beispiel als Startwert).
- `artikel`: `mindestabnahme_modus` ENUM('erbt_typ','eigene_werte','deaktiviert') DEFAULT 'erbt_typ', `mindestabnahme`, `abnahmeintervall` (Override-Werte, nur bei `eigene_werte` verwendet).
- Bewusst genutzt: der bereits vorhandene `artikel_typen.teilbar`-Flag (bisher nur ERP-intern in Wareneingang/Artikeldetail verwendet) entscheidet, ob die ganze Funktion für einen Typ überhaupt sichtbar/anwendbar ist -- kein neues Flag nötig, aktuell nur METERWARE=1.

**Vererbung Vater->Kind:** wie `grundpreis_anzeigen`/`charge_pflicht` an drei Stellen mitgezogen: `ArtikelRepository::propagiereZuKindern()` (Cascade bei jedem Vater-Save), `ArtikelService::saveKind()` (manuelle Einzel-Kind-Anlage), `ArtikelRepository::insert()`/`update()`-Whitelists. Bulk-Kombigenerator (`VariantenRepository::insertKindArtikel()`) bewusst NICHT angefasst -- der DB-Spalten-Default `erbt_typ` deckt frisch generierte Kinder automatisch korrekt ab, ohne dass jeder Insert-Pfad einzeln nachgezogen werden muss.

**UI:**
- `artikel/detail.php` + `artikel/neu.php`: neue Card/Container "Mindestabnahme (Shop)", sichtbar nur wenn `artikeltyp_teilbar` -- Auswahl Typ-Vorgabe/Eigene Werte/Deaktiviert, JS-Toggle in `artikel.js` (`mindestabnahmeModusToggle()`, in `zeigeFelder()` eingehängt).
- **Neu:** Einstellungen-Tab "Mindestabnahme" (`einstellungen/index.php`, Handler in `speichern.php`) -- editierbare Typ-Vorgabe-Liste (nur teilbare Typen), gleiches Card+Zeilen-Formular-Muster wie der bestehende "Einheiten"-Tab. Kein Bedarf für eine volle Artikeltypen-CRUD-Seite (nur 6 fixe Typen, bewusst kleine Lösung).

**Shop-Sync:** `ShopSyncRepository::findMindestabnahmeFelder()` + `ShopSyncService::baueMindestabnahmeFelder()` -- löst Modus/Typ-Default zur effektiven Menge auf, schreibt als `meta_data`-Keys `_mealana_mindestabnahme`/`_mealana_abnahmeintervall` ins Produkt (Standalone) bzw. jede Variation (Kind). Sendet bei "deaktiviert"/keinem Wert bewusst einen Leerstring statt das Feld wegzulassen (WooCommerce meta_data-Keys, die nicht im Request stehen, bleiben unverändert stehen -- ein alter Wert würde sonst hängen bleiben, gleiches Prinzip wie beim dokumentierten `manage_stock`-Fund).

**Getestet:** `php -l` auf allen geänderten/neuen Dateien. Repository-Kette read-only gegen echte Daten verifiziert (Artikel #22892, METERWARE, `erbt_typ` -> löst korrekt auf Typ-Default 20/10 auf). **Kein Browser-Test** (Formular-UI/Einstellungen-Tab noch nicht im Browser geklickt) und **kein echter WooCommerce-Sync-Lauf** dieser neuen Meta-Felder.

## 🟢 Echtbetrieb-Test mit Jacky (BEL-P2400), 2026-08-26 -- zwei echte Bugs gefunden + behoben

1. **Einheiten-Mismatch (Kern-Bug):** Mindestabnahme/Abnahmeintervall wurden im Formular in der
   physischen Inhalt-Einheit des Artikels eingegeben (z.B. Meter, analog zur Grundpreis-Bezugsmenge),
   aber 1:1 unkonvertiert als WooCommerce-"Stück"-Zahl gesendet. WooCommerce zählt Mengenfeld/Bestand
   aber immer in Stück, wobei 1 Stück = `inhalt_menge` der Inhalt-Einheit entspricht (bei BEL-P2400:
   inhalt_menge=0,01m -> 1 Stück = 1cm, Preis 0,07€/Stück = 7€/m). Jackys Eingabe 0,02m/0,01m wäre
   ohne Fix als "0,02 Stück Mindestabnahme" beim Shop angekommen -- sinnlos. **Fix:**
   `ShopSyncRepository::findMindestabnahmeFelder()` liefert zusätzlich `inhalt_menge`,
   `ShopSyncService::baueMindestabnahmeFelder()` teilt Mindestabnahme/Abnahmeintervall durch
   `inhalt_menge` bevor sie als Meta gesendet werden. Verifiziert: BEL-P2400 (0,02m/0,01m,
   inhalt_menge=0,01m) löst jetzt korrekt zu 2/1 Stück auf.
   **Migration 168:** METERWARE-Typ-Default war mit 20/10 gesät (in der Annahme "cm"), ist aber seit
   der Grundpreis-Rechtslage-Korrektur (2026-08-13, siehe [[project_grundpreis_rechtslage_wolle]]) in
   Metern zu verstehen -- korrigiert auf 0,2/0,1 (weiterhin "20cm/10cm", nur in Metern ausgedrückt).
   Formulare (`detail.php`/`neu.php`) + Einstellungen-Tab zeigen jetzt einen Einheiten-Hinweis, damit
   das nicht nochmal passiert.
2. **JS-Toggle lief ins Leere:** `mindestabnahmeModusToggle()` wurde nur in `artikel.js` (genutzt von
   `neu.php`/`bearbeiten.php`) angelegt -- die tatsächlich benutzte Edit-Seite `artikel/detail.php`
   lädt aber `artikel_detail.js`, eine komplett andere Datei. `onchange` rief dadurch eine dort nicht
   existierende Funktion auf, nichts passierte -- erst nach Speichern (Seiten-Reload, Server rendert
   den Sichtbarkeits-Status dann korrekt aus der DB) waren die Felder sichtbar. **Fix:** dieselbe
   Funktion zusätzlich in `artikel_detail.js` ergänzt.
   BEL-P2400 wurde nach dem Fix per gezieltem `sync_status='pending'`-Update erneut zur
   Synchronisierung vorgemerkt.

## ✅ ERWEITERT 2026-08-26: Kundenhinweis-Text ("Bitte beachten Sie die Mindestabnahme von X cm")

Jacky zeigte einen Screenshot (Mengenfeld mit "cm"-Badge + persistenter Hinweistext unterm Warenkorb-Button) und fragte ob sowas geht. Wichtige Rückfrage vorher geklärt: **1 Stück im Mengenfeld ist NICHT bei jedem Meterware-Artikel = 1cm** (`inhalt_menge` variiert, z.B. 0,01m vs. 0,05m) -- die reine Stückzahl fürs Mengenfeld (`_mealana_mindestabnahme`/`_mealana_abnahmeintervall`, unverändert) taugt deshalb NICHT direkt als Kundentext.

**Gebaut:** `ShopSyncService` liefert jetzt zusätzlich vier Meta-Felder für die Anzeige, getrennt von der Stückzahl-Logik:
- `_mealana_mindestabnahme_anzeige` / `_mealana_abnahmeintervall_anzeige` -- menschlich lesbarer Wert
- `_mealana_je_stueck_anzeige` -- wie viel EIN Stück in der Anzeige-Einheit ist (für ein optionales Feld-Badge, nur sinnvoll wenn genau 1)
- `_mealana_anzeige_einheit` -- z.B. "cm" oder "m"

Neue `ShopSyncService::ermittleAnzeigeEinheit()`: Meter unter 1 werden als Zentimeter angezeigt (0,02m -> "2 cm", übliche Konvention für kurze Längen), alles andere bleibt in der Original-Einheit. Verifiziert an zwei Fällen: BEL-P2400 (inhalt_menge=0,01m) -> cm, je Stück=1cm, Mindestabnahme=2cm; hypothetischer Fall inhalt_menge=0,05m -> je Stück=5cm (Badge würde dort zurecht nicht angezeigt), Mindestabnahme 0,20m korrekt zu 20cm/4 Stück aufgelöst.

**Bewusst NICHT gebaut:** das exakte "cm"-Badge direkt im Mengenfeld aus Jackys Screenshot -- würde bei Artikeln mit `je_stueck_anzeige != 1` eine falsche 1:1-Beziehung zwischen eingegebener Zahl und Einheit vortäuschen. Der Hinweistext unterm Button (immer korrekt umgerechnet) deckt den eigentlichen Zweck (Kunde versteht die Mindestmenge) trotzdem ab.

## ✅ LIVE BESTÄTIGT 2026-08-26: WordPress-Snippet eingespielt, Kundenhinweis + Typ-Vorgabe funktionieren

Jacky hat das Snippet auf dem Testshop eingespielt. Screenshot bestätigt: Mengenfeld mit Mindestwert 20 vorbelegt, Hinweisbox "Bitte beachten Sie die Mindestabnahme von 20 cm." / "...das Abnahmeintervall von 10 cm." -- exakt wie im ursprünglich gewünschten Referenz-Screenshot. Getesteter Artikel läuft über "Typ-Vorgabe übernehmen" (erbt_typ), zeigt also korrekt den METERWARE-Default (0,2m/0,1m = 20cm/10cm aus Migration 168). Damit ist der komplette Pfad ERP -> Sync -> Meta-Felder -> WordPress-Snippet -> Kundenanzeige für den Default-Fall live verifiziert.

**Noch nicht extra bestätigt:** BEL-P2400 selbst (der "Eigene Werte"-Override-Fall, 2cm/1cm) wurde nicht nochmal einzeln im Screenshot gezeigt -- Code-Pfad ist aber identisch (nur andere Werte aus `mindestabnahme_modus='eigene_werte'`), keine gesonderte Sorge. Serverseitige Mindestmengen-Validierung (Warenkorb-Fehlermeldung bei Unterschreitung/falschem Intervall) noch nicht mit einem echten Fehlversuch getestet -- nur das visuelle Mengenfeld+Hinweistext.

## 🔲 NOCH OFFEN (klein, kein Blocker)

1. **Server-seitige Validierung noch nicht mit einem echten Fehlversuch getestet** -- eine zu kleine Menge/falsches Intervall im Warenkorb bestellen und prüfen ob `wc_add_notice()`-Fehlermeldung wirklich erscheint (inkl. Umgehungsversuch per DevTools, ob die Sperre auch ohne das Mengenfeld-Attribut greift).
2. **BEL-P2400 (Eigene-Werte-Fall, 2cm/1cm) nicht extra im Screenshot bestätigt** -- Code-Pfad identisch zum verifizierten Default-Fall, aber noch nicht eigens angeschaut.
3. Dezimal-Mengen (z.B. 0,5-Schritte) sind über `step` im Mengenfeld technisch abgedeckt, aber **nicht eigens gegen WooCommerce-Rundungsverhalten bei Bestand/Cart-Berechnung getestet**.
4. Aktuell nur METERWARE betroffen (`teilbar=1`). Falls lose GARN-Kammzüge später auch eine Mindestabnahme brauchen, einfach `artikel_typen.teilbar=1` für den Typ setzen -- Code ist dafür bereits generisch (keine Typ-Codes hartcodiert außer im JS-Toggle `zeigeFelder()`, dort müsste der Typ-Code ergänzt werden).

**How to apply:** Feature ist funktional fertig und live bestätigt. Bei Gelegenheit Punkt 1 (echter Fehlversuch) nachholen, sonst keine aktive Baustelle mehr.
