---
name: project-retouren-zusammenfuehrung
description: "2026-09-30 gebaut, committed+gepusht (79e2c1f), Browser-Klicktest offen — Kasse/Packplatz-Retoure/ERP-Gutschrift teilen Zähler + Rücklagerungs-Weg; Zustand wirkt (Zustandsartikel), Chargen aus Verkauf vorbefüllt, Shop-Sperre für B-Ware"
metadata:
  node_type: memory
  type: project
  originSessionId: 3204dc86-54bf-4800-9ff8-19338a0b843e
  modified: 2026-09-30T13:10:04.151Z
---

Anlass: Jacky fragte, ob Gutschriften die verkauften Chargen zurückbuchen, und ob Retouren über den Packplatz laufen. Befund: Bausteine existierten (Packplatz-Retoure, Rücklagerungen, Zustandsumbuchung intern), waren aber nicht verbunden; Zustand war nur Notiz; ERP-Gutschrift buchte direkt ohne Charge in Lager 1 (Null-Charge-Duplikate); Packplatz-Retoure und ERP-Gutschrift zählten nichts → Doppel-Gutschriften möglich.

**Jackys Entscheidungen:** Zustandsartikel mit Anhang am Ende (`D-101071-RET`, schon so gebaut, kein Präfix). Gebraucht/Retour → Zustandsartikel, zählt nie für Onlineshop. Kassen-Retoure (auch als Gutschein) → Packplatz zur Kontrolle (war schon so). Packplatz-Retoure muss gegen vorherige Kassen-Gutschrift gesperrt sein. Beschädigt → Zustandsartikel -BSC. **Defekt (Jacky bestätigt 2026-09-30): nur ausbuchen + in Lagerverfolgung dokumentieren** → RetourService bucht Retoure-Eingang + sofort warenSchwund() (netto 0, beide Bewegungen mit Charge im Bewegungslog), committed.

**Gebaut:**
- Migration 179: `auftrag_positionen.menge_gutgeschrieben` (Startwert = menge_retourniert), `charge` 20→255 Zeichen, `packplatz_ruecklagerungen` + quelle(kasse|gutschrift)/gutschrift_nr/auftrag_position_id/lager_vorschlag_id/erledigt_artikel_id, kassen_bon_id/bon_nr/kasse_id nullable, Zustand-Enum + retour.
- Zwei Zähler: `menge_retourniert` = physisch zurück, `menge_gutgeschrieben` = Geld erstattet. Kasse erhöht beide + prüft GREATEST(beide); ERP-Gutschrift erhöht gutgeschrieben (+ retourniert wenn an Packplatz); Packplatz-Retoure prüft beide serverseitig, erhöht retourniert (Gutschrift über erstelleGutschrift → gutgeschrieben).
- `src/modules/packplatz/RetourService.php`: findeOderLegeZustandsartikelAn() (aus packplatz/intern/zustand_anlegen_umbuchen.php extrahiert, jetzt + Artikelgruppe + Preise als Startwert), einbuchen() mit Zustandsregel, verkaufteChargen() aus lager_bewegungen (referenz = auftrag_nr oder 'Kassenbon X', bekannte Chargen vor NULL), verteileAufChargen().
- ERP-Gutschrift "Lager zurückbuchen" (Label jetzt "Ware zur Prüfung an den Packplatz") legt Rücklagerungs-Einträge pro verkaufter Charge an statt direkt zu buchen.
- Packplatz-Retoure-UI: pro Position Teile (Menge·Charge·Zustand), "＋ Charge", max = offen, Hinweis schon zurück/gutgeschrieben.
- Shop-Sync: `a.zustand = 'neu'` in findFaelligeArtikel/zaehleFaellige.
- Artikelliste: Filter "Zustand (B-Ware, mit Bestand)" + Suche findet Zustandsartikel-Nummern; beides über vorab berechnete ID-Liste (`holeOriginalIdsZuZustandsartikeln`) — korrelierte Unterabfrage hing >5 min (gleiche Falle wie Doppelte-EAN, [[bug_ean_excel_notation_repariert]]).
- Handbuch 05_packplatz.md + bedienungsanleitung.php.

**Getestet:** 17-Punkte-Test in zurückgerollter Transaktion (Zustandsregel, Zustandsartikel-Anlage/Wiederverwendung, defekt, Shop-Sperre, Filter, Suche, verkaufte Chargen, Gutschrift→Rücklagerung pro Charge, Zähler, Vollstorno nur Rest), CLI-Render von 5 Seiten ohne PHP-Fehler, node --check. Kein Browser-Klicktest.

**Offen:** Kein Browser-Test. Kassen-Retoure legt Rücklagerung nur mit der Bon-Charge an (eine Charge pro Bon-Position) — unverändert.
