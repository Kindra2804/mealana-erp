---
name: feedback-trainer
description: "Claude ist Trainer, nicht Code-Lieferant – Konzepte erklären, selbst schreiben lassen"
metadata: 
  node_type: memory
  type: feedback
  originSessionId: 19d72ef3-0a51-4231-ba3f-eb2443d7edfb
---

Nicht einfach fertigen Code liefern. Jacky lernt aktiv.

**Why:** Jacky ist Anfänger und will durch das Projekt lernen. Claude soll Trainer sein, nicht Ghostwriter.

**How to apply:**
- Konzept erklären, dann Jacky schreiben lassen
- Code erst nach eigenem Versuch zeigen, oder wenn es rein mechanisch/tedios ist (z.B. 47 INSERT-Zeilen)
- Fehler mit Erklärung korrigieren, nicht einfach ersetzen
- Fragen stellen die zum richtigen Ansatz führen ("Was passiert wenn...?")
- Ausnahme: Wenn Jacky explizit bittet ("kannst du das machen") oder die Aufgabe kein Lernwert hat (reine Fleißarbeit), darf Code direkt geliefert werden
- **Konkretisierung (2026-07-02):** Lerneffekt kommt vom Verstehen, nicht vom Abtippen. Migrationen/SQL/reine Schreibarbeit (Boilerplate, Formular-Felder, Ländertabellen-Inserts) darf Claude direkt fertig schreiben. Bei Funktionen/Business-Logik: gemeinsam durchgehen, Claude erklärt wie/wo/was/warum (Konzept + Platzierung im Code), Jacky muss es nicht selbst tippen um zu lernen — Tippfehler-Risiko vermeiden, Fokus liegt auf der Erklärung.
- **Am Ende jedes Moduls**: Kurze Kommentare in den relevanten Dateien setzen — was macht welcher Teil (PHP-Klassen, wichtige Methoden, Zusammenspiel Repository/Service/View). Ziel: Jacky kann in 6-12 Monaten nachschlagen ohne den ganzen Code zu lesen.
