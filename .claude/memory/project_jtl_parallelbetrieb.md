---
name: project_jtl_parallelbetrieb
description: "Entscheidung 2026-11-11 — MeaLana + Laden bleiben vorerst auf JTL, SOW auf ERP; täglicher Bestandsabgleich ERP→JTL, dann JTL→ERP mit Sperre"
metadata:
  node_type: memory
  type: project
  originSessionId: 7f8fbe8b-7883-4d92-8028-9fb07effaffc
  modified: 2026-11-11T19:45:41.138Z
---

**Entscheidung 2026-11-11 (Babsi + Jacky):** mealana.at und der Laden (LS-POS) bleiben vorerst auf JTL, bis die Kinderkrankheiten des ERP über SOW gefunden sind. Nur SOW läuft im ERP. MeaLana + Laden machen rund 95 % der Artikelbewegungen aus. Gegen Überverkauf legt Babsi in JTL Reserven über den Mindestbestand an. Die Kassen sollen evtl. schon im Jänner aufs ERP (LS-POS braucht ~30 s/Beleg für die Signatur), das ist noch offen.

**Gewünschter Ablauf (mind. 1× täglich):**
1. ERP → JTL: Export der ERP-Bestandsänderungen (SOW-Verkäufe, Retouren) seit dem letzten Abgleich, Import in JTL per Ameise.
2. JTL → ERP: JTL-Bestand (eigener Export) ins ERP übernehmen, Differenz als Lagerbewegung „Korrektur durch JTL-Lagerstandsabgleich“.
3. **Sperre:** Der JTL-Import lässt sich nicht starten, solange es seit dem letzten Export ERP-Bewegungen gibt, die noch nicht exportiert/bestätigt sind.

Jacky hat in JTL mehrere „eigene Exporte“ mit SQL (viele AS/CASE). Herkunft unklar; in den Notizen ist kein SQL von Claude dazu zu finden.

**Why:** Ein reiner absoluter JTL→ERP-Import würde die SOW-Verkäufe täglich überschreiben. Darum zuerst ERP→JTL.
**How to apply:** Offene Klärungen siehe Gespräch 2026-11-11 (Ameise-Importmodus relativ/absolut, Lager-Zuordnung, Chargen, wo Wareneingänge gebucht werden). Basis-Skript: [[project_jtl_kunden_auftraege_import]] (jtl_eigener_export_import.php: lager_id=1 und Lieferant 33 fest verdrahtet → für Live anpassen). Siehe auch [[project_sow_umstellung]], [[project_1_0_check]].

**Fakt 2026-11-11 (August-Export geprüft):** Ein leeres „Warenlager“ im eigenen Export bedeutet **Bestand 0**. Die SQL hängt tWarenLagerEingang nur bei fAnzahlAktuell > 0 an; alle 22.978 Zeilen ohne Lager haben Bestand 0. Der Bestand liegt praktisch komplett im Lager „Ladengeschäft“ (3.588 Zeilen), „Mealana-Hauptlager“ hat 1 Zeile. Die frühere Deutung „Export deckt das Hauptlager nicht ab“ war falsch. Folge für den Abgleich: Ein Artikel, der in der Datei nicht vorkommt, hat Bestand 0 → mit Plausibilitätsprüfung arbeiten, falls die Datei gefiltert oder abgeschnitten ist.
Jackys SQL-Exporte in JTL: „Komplettexport_JackyERP“ und „CargenArtikelLager_JackyERP_2“ (Bestand je Artikel/Lager/Lagerplatz/Charge aus tWarenLagerEingang.fAnzahlAktuell).

**Entscheidungen 2026-11-11 (später am Tag):**
- JTL-Ameise „Preise/Bestände (QuickSync)“ hat die Option „Eigenen Lagerbestand aktualisieren = Neuen und alten Lagerbestand addieren“, bucht also relativ. Damit funktioniert die Reihenfolge ERP→JTL, dann JTL→ERP. Spalten: Artikelnummer, Lagername, Charge, Lagerbestand; windows-1252, Semikolon. **Jacky testet noch** an einem JTL-Artikel mit Charge, ob negative Werte gehen und ob die Charge-Spalte beim Abziehen die richtige Charge trifft (-1, dann +1).
- Lager in ERP und JTL gleich: „Ladengeschäft“.
- SOW-Verkauf ohne Charge → in der Exportdatei von der ältesten Charge mit Bestand abziehen (wie LS-POS).
- Wareneingänge in der Parallelzeit in JTL. Die **Inventur will Jacky im ERP machen** (mehrere Zähler parallel, ohne Papier).
- **Konzept-Änderung:** Der Export basiert nicht auf Lagerbewegungen, sondern auf „ERP-Bestand jetzt minus Schnappschuss beim letzten Abgleich“, je Artikel/Lager/Charge. So kommen auch Inventur-Differenzen und Chargen-Umverteilungen mit (die erzeugen keine Bewegung je Charge). Der JTL-Import setzt ERP = JTL + (ERP jetzt − Export-Schnappschuss), bucht die Differenz als „Korrektur durch JTL-Lagerstandsabgleich“ und setzt einen neuen Schnappschuss. Sperre: Import nur nach bestätigtem Export. Plausibilitätsprüfung: Mehr als ~10 % der Artikel würden auf 0 gehen → Stopp mit Vorschau.
- Schlanke JTL-SQL „Lagerabgleich_ERP“ (nur Bestand > 0, Artikelnummer/WarenlagerID/Warenlager/Lagerplatz/Charge/BestandAktuell) vorgeschlagen.

**✅ Gebaut 2026-11-11 (Commit ce0b654, Migration 231):** Lager → JTL-Abgleich (`public/lager/jtl_abgleich.php`, `src/modules/lager/JtlAbgleichService.php`), Tabellen jtl_abgleich_laeufe/_bestand/_umfang. JTL-SQL `docs/jtl/Lagerabgleich_ERP.sql` (alle Nicht-Väter, auch Bestand 0). Getestet an einer Dev-Kopie mit der August-Datei: Erstabgleich (19.857 Artikel, 157 Korrekturen, 2. Lauf 0), Sperren, Lücke zwischen Export und Import, JTL-Verkauf, Plausibilitätsstopp. Ohne Charge: zuerst chargenloser JTL-Bestand, dann älteste Charge. JTL-Charge „0“ (67 Zeilen im August) wird wörtlich übernommen; das alte Skript machte daraus NULL → beim Erstabgleich auf Live harmlos, auf Dev entstehen Umbuchungen '' ↔ '0'. Migration 231 ist auf Dev eingespielt, auf Live fehlt sie noch (nächstes Update 1.0.4). Nächste Migration: 232.


## Früherer Index-Eintrag (aus MEMORY.md verschoben 2026-11-18)
- [🔁 JTL-Parallelbetrieb](project_jtl_parallelbetrieb.md) — 2026-11-11: MeaLana+Laden bleiben auf JTL, SOW im ERP; Lager → JTL-Abgleich ✅ gebaut (Migr. 231), Live-Update offen

## Kasse aufs ERP, MeaLana-Online bleibt auf JTL (Gespräch 2026-11-19)
Jackys Frage: Live-ERP + Kasse in den Laden, LS-POS weg, mealana.at bleibt auf JTL. Ergebnis: **machbar**. Der Lagerabgleich arbeitet mit Deltas und ist deshalb richtungsunabhängig. Online-Aufträge kommen per JTL-Import als Archiv (ohne Lagerbuchung, nicht im Buchhaltungs-Export).
- Abgleich öfter, je nach Shop-Aktivität (Jacky ok).
- **Zu bauen:** „Bareingang zu Rgn. xxx“ für eine **JTL-Rechnung** (freie Rechnungsnummer + Betrag, 0 %, RKSV-Beleg). Der heutige Zahlbeleg braucht einen ERP-Auftrag, Archiv-Aufträge sind an der Kasse gesperrt. Export: Kassa an ein Verrechnungskonto/Debitor, mit der Steuerberatung klären.
- Retouren von Online-Käufen: Freitext-Retour (selten).
- Buchhaltung: Laden ab dem Stichtag nur noch aus dem ERP-Export, im Umstellungsmonat nach Datum trennen; der JTL-Export darf keine Laden-Umsätze mehr enthalten.
- Gutscheine: Jacky macht eine Liste (im Umlauf, Herkunft, Online-Einlösungen im Laden). Notfalls Newsletter: alte Gutscheine gelten nur noch online, bei der Komplettumstellung neuer Code.
- **Ameise-Chargen: bereits getestet ✅**: negative Werte gehen; mit Charge wird diese Charge geändert, neu angelegt oder davon abgezogen.
- Reihenfolge: Artikel (nach Babsis Ausschlussliste) → Erstabgleich → **Inventur im ERP** → per Export zurück nach JTL. Während der Inventur keinen JTL-Versand aus dem Ladenlager (sonst doppelt abgezogen).
- Jacky will eine **Checkliste + einen Ablaufplan** für die ersten Tage (Abgleich, Abholung/Bareingang, Retouren, Artikel/Preise doppelt pflegen, Gutscheine).
