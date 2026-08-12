---
name: feedback-dedup-intra-gruppe-pruefen
description: "Bei Datenbereinigung von Dubletten nie blind \"neueste Zeile gewinnt\" anwenden, wenn Gruppenmitglieder legitim unterschiedliche Werte haben koennten"
metadata:
  type: feedback
  originSessionId: c9c5b016-f30a-42cf-9c6e-b1d797b48f58
  modified: 2026-08-12T09:19:17.956Z
---

Beim Entfernen von Duplikat-Datensätzen (z.B. mehrere `artikel_preise`-Zeilen für denselben Artikel+Kundengruppe) nicht pauschal "die neueste/höchste ID gewinnt" anwenden, ohne vorher zu prüfen, ob innerhalb der übergeordneten Gruppe (z.B. Geschwister-Artikel eines Vaters) eine **echte, beabsichtigte Differenzierung** existiert, die durch die Dublette zufällig überschrieben wurde.

**Warum:** Bei MeaLana ([[project_datenqualitaet_20260812]]) hat genau das die Achsen-Preisdifferenzierung von 177 Vater/Kind-Artikeln zerstört (z.B. DROPS Fabel Uni/Print/Long Print hatten unterschiedliche Preise, alle Duplikate zeigten aber zufällig den flachen Vater-Preis als "neueste" Zeile). Bei den meisten anderen Artikeln war "neueste = korrekt" tatsächlich richtig — die Regel griff also in den meisten Fällen, brach aber genau bei den Fällen, wo es am teuersten war (echte Preisdaten).

**Wie anwenden:**
- Vor dem Löschen: prüfen, ob es eine autoritative Quelle gibt (Original-Importdatei, Audit-Log, Backup), gegen die man die Dublette verifizieren kann — nicht nur gegen sich selbst (z.B. "stimmt neueste Zeile mit dem Elternobjekt überein?").
- Bei Gruppen mit mehreren Kindern/Varianten: prüfen, ob die Gruppe VOR der Bereinigung eine plausible Differenzierung zeigt (z.B. mehrere unterschiedliche Werte unter Geschwistern), die nach "neueste gewinnt" verloren ginge.
- Im Zweifel: erst einen Scope-Check mit einem präzisen Fingerprint des tatsächlichen Fehlermusters fahren (nicht pauschal "DB weicht von Quelle ab" — das erfasst auch legitime spätere manuelle Änderungen), dann gezielt nur die Treffer korrigieren.
- Danach: die zugrunde liegende Ursache fixen (hier: fehlender `NOT EXISTS`-Schutz beim Kopieren), damit das nächste Mal keine Dubletten mehr entstehen — Bereinigung allein reicht nicht.
