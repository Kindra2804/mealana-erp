---
name: bug-offline-resync-kollision
description: "BEHOBEN 2026-07-07: Resync-Sperre + Arbeitsplatz-Erkennung gebaut, verhindert Bon-Nr-Kollision zwischen Online-Kasse und unsynchronisierter Messe-Kasse"
metadata: 
  node_type: memory
  type: project
  originSessionId: 1018675b-06b0-4bee-b923-24fdc5ebd59a
---

## Problem (von Jacky erkannt, 2026-07-07)

Eine Kasse wird als Messe-Kasse offline eingerichtet, macht offline Verkäufe (Belege landen nur in der Browser-IndexedDB, siehe [[project_infrastruktur]]). Kasse kommt wieder ins Netzwerk, Resync wird vergessen. Öffnet man jetzt trotzdem `bon.php` auf derselben Kasse und schließt dort einen Verkauf ab, vergibt `KassenService::naechsteBonNr()` eine Nummer, die mit den noch unsynchronisierten Offline-Belegen kollidiert.

## ✅ Lösung gebaut (2026-07-07) — zusammen mit dem größeren Arbeitsplätze-Thema

Beim Durchdenken kam heraus, dass die Sperre eine funktionierende "welche Kasse bin ich"-Erkennung braucht — die es vorher gar nicht gab (`bon.php` etc. hatten `getKasse(1)` überall hart codiert). Deshalb wurde gleich das komplette [[project_kassen_verwaltung]] Arbeitsplätze-Thema mitgebaut, siehe dort für Details. Die eigentliche Resync-Sperre:

- **`MesseSyncService::hatOffenenResync(int $kasseId): bool`** (neu) — true, solange ein `kassen_messe_sync`-Datensatz mit `status='vorbereitet'` existiert (= Pre-Sync raus, Post-Sync noch nicht hochgeladen). Bewusst NICHT an `kassen.modus` geknüpft — der Admin-Schalter kann falsch stehen, der Sync-Status ist die tatsächliche Quelle der Wahrheit.
- **`bon_speichern.php`**: die eigentliche, nicht umgehbare Sperre. `kasse_id` wird bewusst NICHT aus dem Client-Payload übernommen, sondern serverseitig über `ArbeitsplatzService::aktuelleKasseId()` (Arbeitsplatz-Bindung der Session) ermittelt — ein direkter POST per Shortcut kann die Sperre dadurch nicht umgehen.
- **`bon.php`**: zusätzlich eine UX-Ebene — bei offenem Resync sofortiger Redirect auf `messe_vorbereiten.php` (zeigt die offenen Sync-Pakete direkt an) statt die leere Kassenoberfläche zu zeigen.

**Getestet:** isoliert gegen die echte Dev-DB (künstlicher `kassen_messe_sync`-Datensatz mit status='vorbereitet' angelegt → Sperre greift; auf 'abgeschlossen' gesetzt → Sperre wieder weg), danach aufgeräumt. **Nicht getestet:** echter Browser-Durchlauf (Redirect-Verhalten in `bon.php`, JSON-Fehlermeldung im bestehenden Frontend-Fehlerpfad von `bon.php`'s `bonSpeichern()`).

**Why:** Doppelt vergebene oder lückenhafte Bon-Nummern sind für eine Finanzprüfung ein Problem, unabhängig von RKSV/BFR.
**How to apply:** Vor dem nächsten Messe-Einsatz einmal echt im Browser gegenprüfen: Kasse in Messe-Modus mit offenem Sync versetzen, `bon.php` aufrufen → sollte auf `messe_vorbereiten.php` umleiten, nicht die Kassenoberfläche zeigen.
