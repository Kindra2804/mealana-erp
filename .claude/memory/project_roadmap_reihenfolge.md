---
name: project-roadmap-reihenfolge
description: "Große Reihenfolge der noch offenen ERP-Themen, von Jacky festgelegt 2026-07-10 — bei jedem Session-Start hier nachsehen was als Nächstes dran ist"
metadata:
  node_type: memory
  type: project
  originSessionId: 3c350eb2-8eb3-43e3-bac5-de17c4ce7718
  modified: 2026-07-18T18:00:42.436Z
---

## Festgelegte Reihenfolge (Jacky, 2026-07-10)

Ausgangspunkt war die Frage "was kommt nach diesem Modul, ist das ERP dann fast fertig" — Antwort: nein, zwei ganze Module fehlen noch komplett (Gutscheine, Inventur), dazu Buchhaltung ist erst zu ~10% da. Jacky hat daraufhin die Reihenfolge festgelegt:

1. **Buchhaltung** — ✅ FERTIG 2026-07-17: Kontenplan, Debitoren/Kreditoren, Zahlungsart-/Steuerklasse-Mappings, Verwaltungsseiten, DATEV+CSV-Export. Siehe [[project_buchhaltung]]. Restpunkt: Mahnstufen-Ausbau für Rechnungszahler (Mahngebühr/Verzugszinsen) war als "noch zu bauen" vermerkt, aber nicht blockierend — kann später nachgezogen werden.
2. **Inventur-Modul** — ✅ KOMPLETT FERTIG 2026-07-18 (inkl. Slice 5 Komfort-Ergänzungen), siehe [[project_inventur_konzept]]. Live-Akzeptanztest steht noch aus — Jacky testet bei seiner ersten echten Voll-Inventur (alle Lagerplätze leer → Echtbestand).
3. **Online-Shop-Anbindung** — daran gekoppelt bzw. kurz davor: [[project_paperless_rechnung_modul]] (QR-Rechnung), [[project_sammelabholung_auftraege]] (mehrere Aufträge ein Bon), lose auch [[project_gutscheine]] (bewusst so gebaut werden soll, dass es mit WooCommerce/eigenem Shop matcht).

## Geplant für morgen (2026-07-19)

Jacky hat für die nächste Session diese vier Punkte festgelegt:
1. **RKSV-Hardwaretest** — siehe [[project_rksv_bfr]], jetzt entsperrt (A-Trust-Ursache geklärt), A-Sign-Client 2.7.0 auf der Testmaschine nötig.
2. **Packplatz-Teillieferung** (Phase 2, Positions-Split-Logik) — siehe [[project_kasse_bon_design]].
3. **Logger-UI** — Admin-Aktivitäten-Seite, bisher nur Mockup, siehe [[project_logger_ui]].
4. **Live-Datenbank auf aktuellen Dev-Stand bringen** — alle ausstehenden Migrationen auf die Live-Umgebung (192.168.178.222) spielen. **Ausdrücklich gemeinsam machen**, nicht allein — Jacky: "machen wir das dann nochmal gemeinsam".

**Why:** Direkte Ansage von Jacky nach Abschluss von Buchhaltung+Inventur, bevor das nächste große Thema (Online-Shop-Anbindung) angegangen wird.
**How to apply:** Bei Session-Start morgen diese vier Punkte als Ausgangspunkt nehmen, nicht neu improvisieren.

## Kleinere Punkte — "zwischendurch, je nach Lust und Laune", aber NICHT verlieren

Jackys ausdrücklicher Wunsch: diese dürfen zwischen den großen Themen oben opportunistisch angegangen werden, müssen aber auf der Liste bleiben, nicht in Vergessenheit geraten:

- **Lizenzserver / 2-Ebenen-Konzept** — Jacky selbst: "glaub ich nicht so klein" — trotzdem in diese Kategorie einsortiert, aber mit dem Hinweis dass es vermutlich mehr Aufwand ist als die anderen Punkte hier. Siehe [[project_rechte_rollen]].
- **Logger UI / Admin-Aktivitäten-Seite** — bisher nur Mockup, siehe [[project_logger_ui]].
- **Statistik/Auswertungen** — aktuell nur das Dashboard, keine eigene Reporting-Seite, siehe [[project_statistik]].
- **Packplatz Teillieferung-Split-Logik (Phase 2)** — Restmenge bleibt aktuell nur im Auftrag "hängen" statt echtem Positions-Split, siehe [[project_kasse_bon_design]].
- **RKSV/BFR Hardwaretest** — ✅ Ursache des `/register`-Crashs am 2026-07-18 geklärt (A-Trust-Tool-Version, nicht unser Code), Pausierung aufgehoben, siehe [[project_rksv_bfr]]. Nächster Schritt: Hardwaretest (Offline-Fix + Selbstheilung) mit der älteren A-Sign-Client-Version durchführen.
- **Kundenanzeige-Feedback** — V1 läuft live, wartet auf Barbaras Rückmeldung zum Praxistest, siehe [[project_kundenanzeige_modul]].

## Bewusst pausiert, keine Baustelle (nicht vergessen, aber auch nicht aktiv verfolgen)

- **Backup-Strategie** — erst wenn Live mit echten Daten befüllt ist, siehe [[project_backup_strategie]].
- **Whitelabel/Branding** — wartet auf Logo-Assets von Jacky, siehe [[project_whitelabel_branding]].
- **Update-Mechanismus** — zurückgestellt bis zum Lizenz-Thema, siehe [[project_update_mechanismus]].

**How to apply:** Bei jedem neuen Themenwechsel-Wunsch ("was steht als Nächstes an") zuerst diese Datei konsultieren, bevor eine neue Priorisierung improvisiert wird — die Reihenfolge kommt direkt von Jacky, nicht aus eigener Einschätzung.
