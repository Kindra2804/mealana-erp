---
name: project-gutscheine
description: "Gutschein-Modul (Stand 2026-08-29 Abend): Baustufe 1 (Backend+ERP-UI) UND Baustufe 2 (Kasse: Retoure→Gutschein statt Bar, RKSV-sauber) fertig+verifiziert. Offen: Shop-Checkout-Snippet (wartet auf Jackys Gutschein-Artikel), Design-Vorlagen-Upload, Buchhaltungskonto."
metadata:
  node_type: memory
  type: project
  originSessionId: 40a29a40-0c3b-483d-82e6-51045de0676d
  modified: 2026-09-30T17:01:50.814Z
---

## 2026-09-30: Wiedereinstieg -- Lückenfund + neue Reihenfolge A/B/C

Jacky legte Artikel GUTSCHEIN (#27478) an und konnte `ist_gutschein` nirgends setzen -- Flag existierte nur in der DB, keine UI (Versäumnis Baustufe 1). Außerdem gefunden: Kasse kann Gutscheine weder VERKAUFEN (Kunde kauft Gutschein, zahlt bar/Karte) noch damit BEZAHLEN (Knopf ist Attrappe) -- nur Retoure→Gutschein war fertig. Jacky hat Reihenfolge freigegeben:

- **A ✅ FERTIG 2026-09-30:** Migration 177 -- `artikel_typen.ist_gutschein` + neuer Typ GUTSCHEIN (id 7, keine Varianten, kein Lagerstand) + Artikelgruppe 4700 "Gutscheine" aktiviert. Typ ist einzige Quelle: `ArtikelRepository::syncGutscheinFlag()` nach insert/update/kopiere setzt `artikel.ist_gutschein` aus dem Typ und erzwingt steuerfreie Steuerklasse (satz=0). Rückwechsel auf anderen Typ setzt Flag zurück, Steuerklasse bleibt aber 0% (muss dann händisch gewählt werden). Hinweistext unter Artikeltyp in detail.php/neu.php. Getestet per zurückgerollter Transaktion an #27478. **Jacky muss #27478 noch selbst auf Typ Gutschein + Gruppe 4700 umstellen** (war STANDARD/Gruppe 9/20%). Alter JTL-GUTSCHEIN (#23695, inaktiv, 511 historische Positionen) bewusst unangetastet.
- **B ✅ GEBAUT 2026-09-30 (nicht committed, kein Klicktest an echter Kasse):**
  - Verkauf: ⚙ Menü "🎁 Gutschein verkaufen" bzw. Antippen des Gutschein-Artikels öffnet `ov-gs-verkauf` (Betrag+Schnellbeträge+Empfänger) → Position `block:'gutschein_kauf'` (direkt gepusht, nicht über `_artikelEinfuegen` wegen Merge nur über artikel_id). `posRabatt(p)` ersetzt alle `Math.max(p.rabatt_prozent, globalRabatt)` in bon.php → Gutschein nie rabattiert. Server (`bon_speichern.php`) erzwingt 0%/kein Rabatt/ganze Menge, erstellt pro Stück einen Code NACH erstelleBon und VOR dem echo → `gutscheine_ausgestellt` in der Antwort, Kasse zeigt `zeigeGutscheinErgebnis()` (mehrere Codes+PDF), danach normale Bon-Ausgabe.
  - Bezahlen: neuer Endpunkt `gutscheine/pruefen.php` + `GutscheinService::pruefeEinloesbar()` (liefert bei eingelöstem Code den aktuellen Nachfolger aus `findKette`; `einloesen()` nutzt es jetzt auch). Rest bar/Karte über `rest_zahlungsart`; Server legt `gutschein_betrag`/`bar_betrag` (NETTO-Rest)/`karten_betrag` selbst fest, löst erst nach signiertem Bon ein, setzt `kassen_bons.gutschein_id`, Restcode → `gutschein_rest`. Fehler nach Signatur = Warnung+Log error, Bon bleibt.
  - Berichte: `umsatz_gs` = COALESCE(gutschein_betrag, bruttobetrag); kombi_bar/karte + Kassenstand zählen auch `zahlungsart='gutschein'`-Reste. Auftrag-Spiegel: Gutschein+Rest = 'gemischt'. Buchhaltungsexport: Gutschein+Rest → 'kombi' (Hinweis zur manuellen Buchung).
  - Storno: `GutscheinService::bonStorniert()` aus `ajax_bon_stornieren.php` — verkaufte unbenutzte Codes storniert (sonst Warnung), Einlösungen → neuer Code; Gegenbuchungen hängen am Storno-Bon. bon_journal zeigt es per alert.
  - Bon-Druck (80mm + A4): Gutschein-Betrag, Rest bar/Karte, Rückgeld, Restguthaben-Code, auf dem Bon verkaufte Codes.
  - **Stufe-2-Bug mitbehoben:** Sanitizer in bon_speichern verwarf `kein_lagerabzug` → Retour-Gutschein hätte Lager vom Gutschein-Artikel abgebucht. Jetzt serverseitig: ist_gutschein / keine_lagerbestandsfuehrung / Typ hat_lagerstand=0 → kein Lagerabzug (gilt damit auch für Downloads/Konfigurator an der Kasse).
  - Getestet: php -l, node --check auf bon.php-JS, 14-Punkte-Funktionstest in zurückgerollter Transaktion (Verkauf, Voll-/Teileinlösung, Nachfolger-Hinweis, Kennzahlen, A4-Render, Storno beider Richtungen), SQL-Check Lagerabzug + Buchhaltungsexport.
  - Handbuch: `docs/handbuch/14_gutscheine.md` + Abschnitt `#gutscheine` in bedienungsanleitung.php + Kasse-Zahlart-Zeile in beiden.
  - **Bekannte Lücken:** Kasse-Gutscheine werden NICHT nach WooCommerce gespiegelt (kein shop_id) → online nicht einlösbar, gehört zu C. Offline-Kasse kann Gutscheine nicht prüfen. Storno-Hinweis nur per alert.
  - **Nachtrag (Jacky-Wunsch, gleicher Tag):** ⚙ Menü → "🔍 Gutschein abfragen" = reine Auskunft ohne Buchung (Status, Wert, Restguthaben, gültig bis, Empfänger, Nachfolger-Code). `gutscheine/pruefen.php?info=1` liefert immer alle Daten, auch für eingelöste/abgelaufene/stornierte Codes. Getestet (aktiv/teilweise mit Nachfolger/unbekannt, Rollback).
  - **Committed 2026-09-30** zusammen mit Chargen-Fix + Retouren-Zusammenführung, Push stand noch aus.
- **Sync-Fund beim Shop-Test (2026-09-30, uncommitted):** "Komplettabgleich starten" (scripts/komplettabgleich.php) synchte NUR Artikel — holt jetzt am Ende auch Bestellungen + Gutschein-Coupons. Außerdem übersprang cron/shop_sync.php bei `bulk_import_aktiv=1` den GANZEN Shop (auch Bestellungen) — jetzt nur noch den Artikel-Teil. Jackys Testbestellung WC #34190 (50€ Gutschein, bacs, on-hold) → per Hand importiert als A-2026-00041 (ausstehend, 0 %); Gutschein entsteht erst bei Zahlungseingang (WC-Status processing/completed).
- **Barcode + Scanner (2026-09-30, committed, Jacky: funktioniert):** Gutschein-PDF druckt Code 128 HOCHKANT auf weißem Streifen am rechten Kartenrand (PdfGenerator::barcodeHochkantAlsBase64, GD-Rotation auf weißer Truecolor-Fläche — sonst wird transparenter Hintergrund schwarz; erste Version in der Wert-Box überlappte im echten Dompdf den Betrag). Kasse: MEA-Code im Artikel-Scanfeld → volle Kasse öffnet "Mit Gutschein bezahlen" vorbefüllt+geprüft, leere Kasse öffnet "Gutschein abfragen". `GutscheinService::findeCodeTolerant()`: US-Scanner an deutschem PC tippt "ß" statt "-" und vertauscht Y/Z → wird korrigiert (pruefeEinloesbar + pruefen.php info). Getestet per Rollback; echter Scanner-Test offen.
- **Design-Vorlagen (Entscheidung 2026-09-30):** Babsi erstellt die Hintergründe selbst (Canva), OHNE Box/Logo/Felder — ein festes Raster, das ERP legt Wert-Box (150–205 mm), Gruß-Box (212–265 mm), Logo (oben links) und Fußzeile auf A4 hoch darüber (siehe ASCII-Raster + Claude-Entwürfe in `docs/entwurf_gutschein_vorlagen/`, Generator-Skript `C:\Users\indy1\.claude-browser-tools\gutschein_vorlagen.js`, 5 gezeichnete Motive: Wolle, Weihnachten, Geburtstag, Ostern, Muttertag — Jacky hat sie nicht übernommen). **Sobald Jacky die Bilder liefert:** PDF-Template auf das Raster umbauen (Titel ist dann im Bild), Vorlagen-Verwaltung (Upload/aktiv) im ERP, Motiv-Auswahl an Kasse + im Shop-Snippet (Vorschaubilder müssen dafür in den Shop, vorlage_id ins _mealana_gutschein-JSON).
- **C IN ARBEIT 2026-09-30 (gebaut, NICHT committed, E2E-Test offen):**
  - **Schlüsselfund:** Germanized (aktiv auf indra-design.at) hat am Coupon "Wertgutschein?" (`is_voucher=yes` Meta) = Mehrzweckgutschein: Abzug NACH Steuer als negative Gebühr "Wertgutschein: code" (Ware bleibt voll versteuert). `free_shipping=true` heißt bei Germanized-Wertgutscheinen "deckt Versand" (nicht Gratisversand, `voucher_includes_shipping_costs`). Per Store-API gegen indra-design verifiziert: 500€ deckt 105,15 inkl. Versand, 100€ zieht exakt 100 ab. → KEINE eigene Rabatt-/Versandlogik nötig (alter Plan "eigene Verrechnung" überholt). Coupon-Zeile selbst hat discount=0.
  - Migration 180: `gutscheine.woo_sync_faellig` + Einstellung `gutschein_shop_id`=1 (bewusst EIN Shop, sonst Doppel-Einlösung über Shops möglich). `GutscheinService::markiereShopSync()` bei Erstellen/Einlösen/Ablauf/Storno; `syncShopCoupons($shop)` im `cron/shop_sync.php` NACH Bestellungs-Sync: einlösbar → Coupon anlegen/aktualisieren (fixed_cart, amount=restguthaben, usage_limit 1, free_shipping, is_voucher), sonst Coupon löschen (`WooCommerceClient::loescheCoupon`). Direkt-Spiegel in erstelleGutschein entfernt (hätte Kasse bei Netzproblem blockiert). Fenster bis zu 15 min (Cron-Takt) zwischen Kassen-Einlösung und Löschung im Shop — akzeptiert.
  - Bestellungs-Sync: Betrag aus fee_lines (Name enthält Code), Fallback coupon discount+tax; `auftraege.gutschein_id/gutschein_betrag` gesetzt; Restcode nach Online-Teileinlösung per `versende($id, $billingEmail)`. **2 Bugs behoben:** Online-Kauf "selbst ausdrucken" bekam nie Mail (kein kunden_id übergeben, versende() ohne Ersatz-Adresse); Restcode nach Online-Teileinlösung wurde nie verschickt.
  - Produkt-Sync (`ist_gutschein` jetzt in findFaelligeArtikel): virtual, tax_status none, manage_stock false, regular_price = gutschein_mindestbetrag_shop, Meta `_mealana_gutschein_artikel` {"mindestbetrag":10}.
  - Snippet `shop/wp-snippets/gutschein.php`: Preis "ab 10 € – frei wählbar", Formular (Betrag 10–1000, Schnellbeträge, selbst_ausdrucken/versenden, Empfänger, Zustellung am ≤1 Jahr, Grußtext), Validierung, Cart-Preis, `_mealana_gutschein`-JSON an Bestellzeile, Sperre Gutschein-mit-Gutschein (woocommerce_coupon_is_valid), Germanized-Gebühr umbenannt "Gutschein MEA-…" (`woocommerce_gzd_voucher_name`) + Rest-Hinweis in woocommerce_after_calculate_totals. **Von Jacky selbst in WPCode eingespielt** (mein Playwright-Einspielen scheiterte: Aktiv-Schalter + Speichern-Navigation; WPCode-ID noch unbekannt → README-Tabelle nachtragen).
  - Handbuch 14_gutscheine.md + bedienungsanleitung.php (#gutscheine-shop, Abfrage).
  - **E2E-Test im Testshop bestanden 2026-09-30** (Playwright, 14 Punkte, ohne Bestellung: Formular, Validierung, Preis, virtuell/steuerfrei, Sperre, 58€-Code auf 21,90-Bestellung → 0,00 inkl. Versand + "Rest 36,10 € kommt per E-Mail"; ERP-Rückweg per Reflection-Test mit Rollback). Snippet WPCode-ID 34188, committed. Pickliste blendet Gutschein-Positionen aus, reine Gutschein-Aufträge werden abgeschlossen.
  - **Rechnung (2026-09-30, uncommitted):** Steuerblock zeigt "Bezahlt mit Gutschein MEA-…: −X" pro Code (aus gutschein_transaktionen mit auftrag_id) + Restbetrag; nicht auf Gutschriften. ERP-manuelle Einlösung (gutscheine/einloesen.php) hat KEINEN Auftragsbezug → erscheint auf keiner Rechnung.
  - **Offen:** echte Testbestellung durch Jacky (bezahlen → Gutschein+Mail+Rechnung), Rechnung zeigt "bezahlt mit Gutschein" noch nicht (auftraege.gutschein_betrag wird in keinem Template genutzt), Design-Vorlagen, commit+push.
- Offene Frage an Babsi: Gruppe hängt an Erlöskonto 4700, Babsi bucht laut Notiz auf 3er-Konto "Anzahlung ohne Steuer" -- richtige Nummer klären.

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

## ✅ Nachverfolgbarkeit bei Teileinlösung ergänzt (2026-08-29, gleicher Tag)

**Jackys Anfrage:** Support-Fall vorausgedacht -- Kunde ruft an "mein Code funktioniert nicht", weil er (a) den Checkout-Hinweis übersehen und (b) die Mail mit dem neuen Restbetrag-Code nicht beachtet hat. Frage: gibt es eine nachvollziehbare Liste welcher Code zu welchem Zeitpunkt/welcher Bestellung teileingelöst wurde und was der Nachfolge-Code ist?

**Dabei einen echten Bug im ursprünglichen Bau gefunden+behoben:** Der ALTE Code behielt nach einer Teileinlösung fälschlich sein `restguthaben` auf dem übertragenen Betrag stehen (z.B. 70€), obwohl dieser Wert längst auf den neuen Code übertragen war -- hätte Support selbst in die Irre geführt ("der alte Code hat doch noch 70€ Guthaben lt. System"). Fix: `restguthaben` geht bei JEDER Einlösung (voll oder teilweise) auf 0, der tatsächliche Rest lebt ausschließlich auf dem neuen Code.

**Migration 175:** `gutscheine.vorgaenger_gutschein_id` (self-referencing FK) -- verkettet einen neu erzeugten Rest-Code mit dem Code, aus dessen Teileinlösung er entstand.

**`GutscheinRepository::findKette()`:** läuft von einem BELIEBIGEN Punkt der Kette rückwärts zum Ursprung UND vorwärts zum aktuell gültigen Code -- Support kann mit dem uralten, längst toten Code danach fragen und sofort den echten aktuellen Code samt Verlauf sehen.

**Zusätzlich in `einloesen()`:** Bei Teileinlösung bekommt die ALTE Transaktionshistorie automatisch einen Eintrag "Restguthaben X€ übertragen auf neuen Code Y" -- direkt in der Transaktionsliste sichtbar, ohne erst der Kette folgen zu müssen.

**UI (`detail.php`):** neue "🔗 Gutschein-Verlauf"-Karte (nur sichtbar wenn Kette >1 Glied hat) zeigt die komplette Kette als klickbare Chip-Kette, plus Warnhinweis wenn der aktuell angesehene Code NICHT mehr der gültige ist ("Rest wurde übertragen auf X").

**Suchfunktion nach Code:** existierte schon in `liste.php` (LIKE-Suche auf `code`), deckt den "alten Code eintippen und finden"-Fall bereits ab -- keine Zusatzarbeit nötig.

**Echter Kettentest bestanden** (2 Teileinlösungen hintereinander, 100€→70€→20€): `findKette()` liefert korrekt alle 3 Codes in Reihenfolge, alte Codes zeigen korrekt `restguthaben=0`, Transaktionslog zeigt den Übertrag-Hinweis. Committed+gepusht.

### 🔲 Noch offen (klar benannt, nicht vergessen)

1. ~~Kasse-UI-Anbindung Retoure→Gutschein~~ ✅ erledigt, siehe Baustufe 2 unten. **Der bestehende "mit Gutschein BEZAHLEN"-Knopf** (`zahlenGutschein()`/`gsPruefen()` in bon.php, prüft aktuell nur `code.length>=3`) ist eine ANDERE, weiterhin offene Baustelle -- noch NICHT an `GutscheinService::einloesen()` angebunden.
2. **Shop-seitiges WPCode-Snippet** -- Checkout-Felder am Gutschein-Artikel (Betrag/Empfänger/Zustellung/Grußtext → `_mealana_gutschein`-Meta), Versand-inklusive Rabattlogik (eigene Verrechnung Warenwert+Versand statt WC's binärem "Kostenloser Versand"-Haken), Teileinlösung-Checkout-Hinweis. Braucht zuerst einen echten "Shop-Gutschein"-Artikel in der ERP-DB (SKU "GUTSCHEIN", `ist_gutschein=1`) -- Jacky legt den als Nächstes selbst an.
3. ~~Kasse-Erstattung via Gutschein~~ ✅ erledigt, siehe Baustufe 2 unten.
4. **Design-Vorlagen** -- `gutschein_vorlagen` ist leer, Jacky muss die 8 JTL-Bildvorlagen (oder neue) hochladen.
5. **Buchhaltungs-Konto** -- Gutschein-Anzahlungskonto-Nummer kommt noch von Babsi (siehe [[project_buchhaltung]]).
6. **Zugriffsregeln** -- granulare Berechtigungen nachziehen sobald Rollen-Zuordnung klar ist.
7. **Browser-Test/Live-Test** aller neuen Kasse- und ERP-Seiten steht aus (echte BFR-Kasse, echter `ist_gutschein=1`-Artikel nötig).

**How to apply beim Wiedereinstieg:** Backend + Kasse-Ausstellung sind fertig und verifiziert -- NICHT nochmal neu bauen. Nächster Schritt hängt an Jacky: sobald der Gutschein-Artikel angelegt ist, Punkt 2 (Shop-Checkout-Snippet) angehen.

## ✅ Baustufe 2 GEBAUT 2026-08-29 Abend: Kasse-Anbindung Retoure → Gutschein statt Bar

Jackys Anfrage: "Machen wir die Kassenanbindung... damit die bei Rückgaben Gutscheine erstellen kann anstatt von Bar-Auszahlungen." Mit kritischer Ergänzung mid-turn: **muss RKSV-sauber als echter "Gutschein-Verkauf"-Bon-Posten laufen**, damit die Bon-Summe auf 0,- kommt (Retour negativ + Gutschein-Verkauf positiv) -- keine stille DB-Zeile ohne Bon-/Signatur-Bezug.

**Migration 176:** `kassen_bons.zahlungsart` ENUM erweitert um `'gutschein_ausgabe'` -- bewusst NICHT der bestehende `'gutschein'`-Wert (der bedeutet "Kunde BEZAHLT mit Gutschein", eine andere, weiterhin offene Kasse-Funktion, siehe Punkt 1 oben). Angewendet.

**Ablauf:** `bon.php::retourAlsGutschein()` hängt eine `block:'gutschein_verkauf'`-Position (0% MwSt, `kein_lagerabzug:true`) an, die die Bon-Summe exakt auf 0 bringt, und schickt mit `zahlungsart:'gutschein_ausgabe'`. `bon_speichern.php` erkennt diese Positions-Markierung serverseitig (NICHT den Client-Betrag -- Retourbetrag wird aus den `block:'retour'`-Positionen serverseitig neu berechnet, gleiche "nie dem Client trauen"-Philosophie wie beim Konfigurator-Preis), ruft `GutscheinService::erstelleGutschein()` mit `kanal_erstellt:'kasse'`, `kassen_bon_id`, `auftrag_id_ursprung` auf. Neuer Lookup-Endpunkt `gutscheine/letzter_fuer_bon.php` liefert Code/Betrag/PDF-Link an die Kasse-UI zurück (eigener Request NACH dem Bon-Speichern-Response, weil `bon_speichern.php` sein JSON schon vor den Retour-Zeilen `echo`t und PHP das nicht mehr nachträglich ändern kann).

**Verifiziert (CLI, ohne echte BFR-Kasse-Session):**
- `php -l` auf allen 3 Dateien (`bon.php`, `bon_speichern.php`, `letzter_fuer_bon.php`) sauber.
- `GutscheinService::erstelleGutschein()` mit echtem Testaufruf durchlaufen (Test-Datensatz danach wieder gelöscht, siehe [[feedback_test_isolation]]) -- Rückgabeformat (`['erfolg'=>true,'id','code']`) passt exakt zu dem was `bon_speichern.php` erwartet.
- `letzter_fuer_bon.php`-SQL direkt gegen die Test-Transaktion geprüft -- Join über `kassen_bon_id` liefert korrektes Ergebnis; Endpunkt selbst verlangt korrekt Login (302 auf `/login.php`, wie alle anderen `gutscheine/*.php`-Seiten).
- Code-Review bestätigt: `new GutscheinService()`-Konstruktor ist No-Arg (passt), `$gErgebnis['erfolg']`-Check passt zum tatsächlichen Rückgabewert.

**Nicht möglich ohne Jackys nächsten Schritt:** echter End-to-End-Klicktest an der Kasse -- braucht einen realen `ist_gutschein=1`-Artikel (Jacky legt den als Nächstes selbst an) und eine echte BFR-Session, beides nicht sinnvoll per CLI simulierbar (analog zu allen anderen Kassen-Features in diesem Projekt, die immer erst am echten Gerät final abgenommen werden).

### ✅ Nachtrag gleicher Tag: Auftrag-Anzeige bei Gutschein-Erstattung korrigiert

Jackys Nachfrage: bei einem "überbezahlten" Web-Auftrag (Kunde nimmt weniger mit) muss die Auftrag-Detailseite bei Gutschein-Erstattung genauso korrekt "erstattet" zeigen wie bisher bei Bar -- inkl. Code/Link, damit Support sofort nachvollziehen kann. Beim Nachprüfen echten Bug im frisch gebauten Kasse-Zweig gefunden: die Gutschein-Ausgabe erzeugte KEINEN negativen `auftrag_zahlungen`-Posten (anders als der Bar-Zweig) -- Folge: `detail.php`s `$offenBetrag`-Rechnung (`bruttobetrag - retourGesamtbetrag - summeBezahlt`) wäre bei Gutschein-Erstattung fälschlich negativ geblieben und hätte "Überbezahlt ... Gutschrift" statt "Vollständig bezahlt" angezeigt.

**Fix `bon_speichern.php`:** Bei erfolgreicher `erstelleGutschein()` wird jetzt genau wie beim Bar-Zweig ein negativer `auftrag_zahlungen`-Posten gebucht, Notiz enthält den echten Gutschein-Code ("Rückerstattung als Gutschein MEA-XXXX... — Bon Y") statt "bar" -- hält die Zahlungsverlauf-Arithmetik konsistent UND macht den Code direkt in der bestehenden Zahlungsverlauf-Liste sichtbar.

**Neu in `auftraege/detail.php`:** eigene Karte "Als Gutschein erstattet" (nur sichtbar wenn vorhanden), findet über `gutscheine.auftrag_id_ursprung = auftrag.id` (Feld existierte schon, keine Schema-Änderung nötig) alle für diesen Auftrag ausgestellten Gutscheine und zeigt sie als klickbaren Link zu `gutscheine/detail.php` -- Notiz-Text allein kann keinen Link tragen (wird escaped), deshalb eigene Karte statt nur Text.

**Verifiziert:** `php -l` beide Dateien sauber, neue SQL-Query per echtem Testdatensatz (danach gelöscht) bestätigt, Arithmetik-Symmetrie zum bereits bewährten Bar-Zweig durch Code-Vergleich bestätigt.

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
