---
name: project-chargen-nachverfolgung
description: "Geplantes Feature — zentrale Chargen-Historie/Nachverfolgung (Artikelsuche → Charge-Dropdown → Lagerbewegungen), noch nicht gebaut"
metadata: 
  node_type: memory
  type: project
  originSessionId: db02ffa8-aab5-44a1-a954-8cc195e7d369
---

Jacky möchte eine **Chargen-Nachverfolgbarkeit** (Traceability) — die Rohdaten dafür existieren bereits vollständig in `lager_bewegungen` (jede Bewegung trägt `charge`, `bestand_vorher`, `bestand_nachher`, `referenz`, Datum, Benutzer), es fehlt nur die Abfrage-Oberfläche.

**Zwei Ausbaustufen, von ihm selbst unterschieden:**

1. **Direkt umzusetzen** (erledigt 2026-07-04): Auf der Artikel-Detailseite (`artikel/detail.php`, Tab "Lager") sollen bei "X verschiedene Chargen" und in der aufklappbaren Chargen-Liste nur Chargen mit **tatsächlichem Bestand > 0** gezählt/angezeigt werden — sonst sammeln sich bei gut laufenden Artikeln nach einem Jahr zig längst ausverkaufte 0-Stück-Chargen an. Umgesetzt in `LagerRepository::findBestandChargeProLager()` (liefert jetzt auch die Charge=NULL-Zeile mit) + Filterung in `detail.php` (nur bestand>0, Charge=NULL nur bei `charge_pflicht`-Artikeln sichtbar).

2. **Noch zu bauen — zentrale Chargen-Historie-Seite**: Eine eigene Seite (Vorschlag von Jacky: bei Lager ansiedeln, analog zum bestehenden Chargen-Nachtrag-Workflow unter `lager/nachtrag_liste.php`) mit dem Ablauf:
   - Artikelsuche (Name, EAN, Artikelnummer) — wie die bestehende Typeahead-Suche in anderen Modulen
   - Dropdown/Select, das sich mit den **historischen Chargen dieses konkreten Artikels** selbst befüllt (aus `lager_bewegungen` oder `lagerbestand`, DISTINCT charge WHERE artikel_id=X)
   - Darunter: vollständige Liste aller Lagerbewegungen für genau diese Artikel+Charge-Kombination (vom Wareneingang/EK bis zum letzten Verkauf)

**Warum eine eigene zentrale Seite statt nur am Artikel selbst:** Genau diese Ansicht (Artikel→Chargen→Bewegungen) gibt es technisch schon auf der Artikel-Detailseite selbst (Tab Lager, "Letzte Lagerbewegungen"). Der Wunsch ist eine **zentrale, artikelübergreifende** Version — wenn er mehrere Artikel nacheinander nachschlagen will, will er nicht jedes Mal über Artikelsuche → Artikel öffnen → Lager-Tab gehen müssen, sondern an einer Stelle direkt durchklicken können (Artikel wechseln, ohne die Seite zu verlassen).

**How to apply:** Wenn das Lager-Modul oder eine Inventur-/Rückverfolgungs-Funktion als nächstes ansteht, dieses Feature einplanen. Kandidat-Ort: `erp/public/lager/` (dort ist auch `nachtrag_liste.php` für den verwandten Anwendungsfall "Charge fehlt noch"). Siehe auch [[project_chargen_konzept]] für das Gesamtkonzept der Chargen-Typen, und [[project_lager_konzept]] für den Stand der Lager-Verwaltungs-UI generell (die laut Jacky auch noch fehlt).
