---
name: zahlung-buchen
description: "Zahlungserfassung im Auftragsdetail — Teilzahlung, Überzahlung, Buchungsdatum"
metadata: 
  node_type: memory
  type: project
  originSessionId: 3bbaa246-5729-4e26-8fb8-4785822652ed
---

Aktueller Stand: Auftrag/detail hat nur einen einfachen "Zahlung setzen"-Button (Status-Wechsel).

Gewünschter Umbau (nächster Arbeitstag):

**Eingabemaske statt Status-Knopf:**
- Betrag-Feld (vorausgefüllt mit offenem Restbetrag, aber überschreibbar)
- Datum-Picker (vorausgefüllt mit heute, aber änderbar)
  - Grund: Banküberweisung kommt abends an, soll auf den richtigen Buchungstag gebucht werden
  - Wichtig für Buchhaltung/DATEV-Export (Valutadatum ≠ Erfassungsdatum)

**Why:** Kunden überweisen oft zu wenig oder zu viel. Teilzahlungen und Überzahlungen müssen korrekt erfasst werden.

**How to apply:** Neue Tabelle `auftrag_zahlungen` oder Erweiterung bestehender Struktur — beim Design darauf achten dass mehrere Teilzahlungen pro Auftrag möglich sind. Offener Betrag = bruttobetrag - SUM(gezahlte Beträge).
