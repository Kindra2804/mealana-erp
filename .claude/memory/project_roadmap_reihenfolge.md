---
name: project-roadmap-reihenfolge
description: "Große Reihenfolge der noch offenen ERP-Themen, von Jacky festgelegt 2026-07-10 — bei jedem Session-Start hier nachsehen was als Nächstes dran ist"
metadata:
  node_type: memory
  type: project
  originSessionId: 3c350eb2-8eb3-43e3-bac5-de17c4ce7718
  modified: 2026-07-22T16:35:03.463Z
---

## Festgelegte Reihenfolge (Jacky, 2026-07-10)

Ausgangspunkt war die Frage "was kommt nach diesem Modul, ist das ERP dann fast fertig" — Antwort: nein, zwei ganze Module fehlen noch komplett (Gutscheine, Inventur), dazu Buchhaltung ist erst zu ~10% da. Jacky hat daraufhin die Reihenfolge festgelegt:

1. **Buchhaltung** — ✅ FERTIG 2026-07-17: Kontenplan, Debitoren/Kreditoren, Zahlungsart-/Steuerklasse-Mappings, Verwaltungsseiten, DATEV+CSV-Export. Siehe [[project_buchhaltung]]. Restpunkt: Mahnstufen-Ausbau für Rechnungszahler (Mahngebühr/Verzugszinsen) war als "noch zu bauen" vermerkt, aber nicht blockierend — kann später nachgezogen werden.
2. **Inventur-Modul** — ✅ KOMPLETT FERTIG 2026-07-18 (inkl. Slice 5 Komfort-Ergänzungen), siehe [[project_inventur_konzept]]. Live-Akzeptanztest steht noch aus — Jacky testet bei seiner ersten echten Voll-Inventur (alle Lagerplätze leer → Echtbestand).
3. **Online-Shop-Anbindung** — Live-Anbindung muss noch warten (zu wenig Daten auf Live). ✅ Phase 1 komplett FERTIG 2026-07-20, ✅ Vater/Kind-Variationssync (Variable Products) FERTIG 2026-07-21 (Migration 143, Achsen→globale WC-Attribute, Achsenwerte→Terms, Kind-Artikel→Variationen, End-to-End gegen Testshop verifiziert) — siehe [[project_shop_sync]] für vollen Stand + Phasenplan. Daran gekoppelt bzw. kurz davor: [[project_paperless_rechnung_modul]] (QR-Rechnung), [[project_sammelabholung_auftraege]] (mehrere Aufträge ein Bon), lose auch [[project_gutscheine]] (bewusst so gebaut werden soll, dass es mit WooCommerce/eigenem Shop matcht).

   **Konkrete Sub-Reihenfolge festgelegt (Jacky, 2026-07-21):**
   1. ~~Hersteller-Filter~~ ✅ FERTIG 2026-07-21 (siehe [[project_hersteller_shop_filter]]) + ~~Bilder pro Vater/Kind~~ ✅ FERTIG 2026-07-21 (siehe [[project_shop_sync]] — neue WordPress-Application-Password-Zugangsdaten nötig, echter Fälligkeits-Bug bei nachträglich hochgeladenen Bildern gefunden+gefixt)
   2. ~~Phase 2 (Bestand/Lagerstand)~~ ✅ FERTIG 2026-07-21 (siehe [[project_shop_sync]] — echter Fälligkeits-Bug analog zum Bilder-Fund gefunden+gefixt). ~~Phase 3 (Bestellungen, reines Polling statt Webhook — ERP hat keinen öffentlichen Endpunkt)~~ ✅ FERTIG 2026-07-21 (vierter Fund desselben Session-Cron-Bugs in `AuftragService`, gefixt). ~~Phase 4 (eingegrenzt: Bestellungen mit echten Kunden verknüpfen statt nur Snapshot)~~ ✅ FERTIG 2026-07-21 (fünfter Fund desselben Bugs, diesmal in `KundenService`) — volles Kunden-Merge-Szenario (ERP→Shop-Account-Push, DSGVO-Löschsync, Fuzzy-Merge-Queue) bewusst zurückgestellt, siehe [[project_kundendatenbank]]
   3. ~~Theme-Gespräch~~ ✅ Recherche FERTIG 2026-07-21 (WoodMart vs. Blocksy Pro, siehe [[project_shop_theme]] — wichtige Lizenz-Falle gefunden: ThemeForest-Lizenzen gelten pro Live-Domain, bei 3 Shops also 3× Kosten). **Kaufentscheidung selbst pausiert** — Jacky bespricht das Budget zuerst mit Barbara, angedachter Plan: erst eine WoodMart-Lizenz testen, dann über Rest/Umstieg auf Blocksy entscheiden. Nicht von selbst weitermachen.
   4. ~~`cron/shop_sync.php` + Kategorie-Umbenennung-Sync~~ ✅ FERTIG 2026-07-22, vorgezogen (nicht mehr an die Theme-Kaufentscheidung gekoppelt, siehe unten) — dazu gleich noch FTP-Bulk-Bild-Erstbefüllung + Bulk-Import-Sperre (JTL-Komplettabgleich-Analogie) gebaut. ~~Versionierungs-Sprung + Live-Deploy~~ ✅ FERTIG 2026-07-22 (gleicher Tag, Jacky war schon per AnyDesk am Server) — Live auf 0.4.0(beta), Migrationen 142-150 + kompletter Shop-Sync-Code deployed, dabei echten Lücken-Fund behoben (WordPress-Zugangsdaten-Felder fehlten komplett in Einstellungen → Kanäle), Verbindung von Live aus bestätigt. Details siehe [[project_shop_sync]].
   4b. **Wichtige Entscheidung 2026-07-22:** Jacky hat parallel zur Theme-Kaufentscheidung eine **Gratis-Theme-Basis** (Blocksy free+Elementor+Max Mega Menu+Germanized) gebaut, siehe [[project_shop_theme]] — Barbara kann darauf schon jetzt üben, unabhängig vom Budget-Gespräch. Ein "echter" Go-Live findet ohnehin erst mit der Basisinventur statt; bis dahin wird die Live-ERP-Umgebung testweise an den Testshop (`indra-design.at`) angebunden, Cron läuft dort probeweise, Barbara spielt parallel Artikel/Kategorien ein. Erst wenn alles passt, werden die Zugangsdaten auf die echten neuen Shops umgestellt.
   4c. **Kundendaten-Migration von JTL geklärt (2026-07-22):** Passwörter (Blowfish/bcrypt-Hash) sind grundsätzlich nicht migrierbar — gilt für JEDES System, nicht JTL-spezifisch, jeder Kunde muss beim Umstieg einmal das Passwort zurücksetzen. Name/Adresse/Bestellhistorie sind davon unabhängig migrierbar (reiner Datenimport) — eigenes, größeres Thema, hängt an [[project_kundendatenbank]] + JTL-Anreicherungs-Import, nicht heute gelöst.
   - GPSR-Herstellerangaben (Punkt 2 der letzten Session) bewusst **außerhalb** dieser Reihenfolge — hängt an rechtlicher Klärung, nicht am Zeitplan, kann jederzeit dazwischengeschoben werden sobald Jacky Klarheit hat (siehe [[project_hersteller_shop_filter]])
   - **Auch vorgemerkt (2026-07-19): JTL-Anreicherungs-Import.** Nicht voller Produktimport, sondern gezielt Beschreibungen/Bilder/etc. per Artikelnummer-Match aus JTL-Export-Listen in bereits von Hand angelegte Vater-Artikel nachziehen — spart Jacky das Abtippen, er macht nur noch Vater-Artikel+Achsenzuweisung selbst. JTL-CSV-Struktur/Spaltenindizes/Encoding-Fallstricke schon dokumentiert in [[project_jtl_import]]. Offene Frage vor der Detailplanung: enthält Jackys JTL-Export Bild-Referenzen (Dateinamen/URLs), oder nur Text — entscheidet ob Bilder gleich in Phase 1 reinkönnen.

## Geplant für 2026-07-19 — Stand nach der Session

Jacky hatte vier Punkte festgelegt:
1. **RKSV-Hardwaretest** — ✅ FERTIG 2026-07-19, siehe [[project_rksv_bfr]]. Hardware-Wechsel-Flow, Normalverkauf, Ausfall-Test alle erfolgreich durchgespielt, zwei echte Bugs gefunden+gefixt (Ausfall-Erkennung, bfr_url-Selbstheilung nie funktionsfähig — Feature abgebaut, durch leichtgewichtiges manuelles bfr_url-Bearbeiten ersetzt).
2. **Packplatz-Teillieferung** (Phase 2, Positions-Split-Logik) — ✅ FERTIG 2026-07-19, siehe [[project_packplatz]]. Neue Tabelle `auftrag_lieferung_positionen`, Charge-Anzeige auf Lieferschein optional (Einstellungen → System).
3. **Logger-UI** — ✅ FERTIG 2026-07-19, siehe [[project_logger_ui]]. Shell-Zeile + Admin-Aktivitäten-Seite mit info/warn/error-Stufen, Zugriff-verweigert loggt bereits als warn. Weitere warn/error-Stellen (Import, Shop-Abgleich) bewusst zurückgestellt bis diese Module gebaut werden.
4. **Live-Datenbank auf aktuellen Dev-Stand bringen** — ✅ FERTIG 2026-07-19, gemeinsam per AnyDesk (Version 0.3.0(beta), Migrationen 126–141 eingespielt). Dabei einen deutlich größeren Bootstrap-Skip-Bug gefunden als erwartet — mehrere Stammdaten-Tabellen (Steuerklassen, Artikeltypen, Einheiten, Länder, Zahlungsbedingungen, Kundengruppen, Nummernkreise) und zwei ganze BFR-Tabellen fehlten komplett auf Live. Details + Fix-Dateien in [[project_installationsanleitung]].

**Why:** Direkte Ansage von Jacky nach Abschluss von Buchhaltung+Inventur, bevor das nächste große Thema (Online-Shop-Anbindung) angegangen wird.
**How to apply:** Bei der nächsten Session mit Punkt 2, 3 oder 4 weitermachen (Jackys Wahl), nicht neu improvisieren.

## Geplant für nächste Session (Jacky, 2026-07-22 festgelegt) — in dieser Reihenfolge

1. **ALS ERSTES: Separate Germanized-"Hersteller"-Funktion prüfen** — eigener WP-Menüpunkt mit "Herstelleradresse"/"Verantwortliche Person (EU)"-Feldern, vermutlich die eigentlich vorgesehene GPSR-Lösung dieses Plugin-Stacks. Siehe [[project_hersteller_shop_filter]]/[[project_shop_sync]] für den vollen Fund.
2. **Grundpreis-Sync-Automatisierung** — ERP-Grundpreis direkt in Germanized' Feld pushen (Nice-to-have, siehe [[project_shop_theme]]).
3. **Dashboard** — Online-Kanäle mit einbinden.
4. **Statistik/Auswertungen** — siehe [[project_statistik]].
5. **JTL-Anreicherungs-Import** — siehe Beschreibung oben, [[project_jtl_import]].

## Kleinere Punkte — "zwischendurch, je nach Lust und Laune", aber NICHT verlieren

Jackys ausdrücklicher Wunsch: diese dürfen zwischen den großen Themen oben opportunistisch angegangen werden, müssen aber auf der Liste bleiben, nicht in Vergessenheit geraten:

- **Lizenzserver / 2-Ebenen-Konzept** — Jacky selbst: "glaub ich nicht so klein" — trotzdem in diese Kategorie einsortiert, aber mit dem Hinweis dass es vermutlich mehr Aufwand ist als die anderen Punkte hier. Siehe [[project_rechte_rollen]].
- **Statistik/Auswertungen** — aktuell nur das Dashboard, keine eigene Reporting-Seite, siehe [[project_statistik]].
- **Kundenanzeige-Feedback** — V1 läuft live, wartet auf Barbaras Rückmeldung zum Praxistest, siehe [[project_kundenanzeige_modul]].
- **Laden-/Telefon-Auftrag im Online-Kundenkonto sichtbar** — Jackys Frage 2026-07-21 nach dem Bestellungs-Sync: Phase 3 synct nur Shop→ERP (lesend), NICHT umgekehrt. Ein Kasse/Telefon-Auftrag würde aktuell NICHT im WooCommerce-Kundenkonto des Kunden auftauchen — bräuchte aktives Anlegen einer Bestellung in WooCommerce (Gegenrichtung, nicht gebaut) UND eine WC-Kundenaccount-Zuordnung (Phase 4 Kunden-Merge als Voraussetzung). Aktuell kein Bedarf, nur als Idee vorgemerkt.

## Bewusst pausiert, keine Baustelle (nicht vergessen, aber auch nicht aktiv verfolgen)

- **Backup-Strategie** — erst wenn Live mit echten Daten befüllt ist, siehe [[project_backup_strategie]].
- **Whitelabel/Branding** — wartet auf Logo-Assets von Jacky, siehe [[project_whitelabel_branding]].
- **Update-Mechanismus** — zurückgestellt bis zum Lizenz-Thema, siehe [[project_update_mechanismus]].

**How to apply:** Bei jedem neuen Themenwechsel-Wunsch ("was steht als Nächstes an") zuerst diese Datei konsultieren, bevor eine neue Priorisierung improvisiert wird — die Reihenfolge kommt direkt von Jacky, nicht aus eigener Einschätzung.
