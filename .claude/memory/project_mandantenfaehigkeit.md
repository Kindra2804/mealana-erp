---
name: project-mandantenfaehigkeit
description: Idee Jacky 2026-11-25 „NAHTLOS mandantenfähig“ — nur im Hinterkopf, Claude-Einschätzung DB je Mandant
metadata:
  type: project
---

Jacky 2026-11-25: Vielleicht irgendwann mandantenfähig (Anlass: er braucht 2 eigene Installationen, Indranet + Direktvertrieb). **Nur Idee, nicht bauen.**

**Claude-Einschätzung:** Variante „eigene Datenbank je Mandant“ (so macht es auch JTL-Wawi): Mandanten-Auswahl beim Login, Database::getInstance nimmt dbname aus Mandantenliste, Uploads/storage/Belege je Mandant-Unterordner, Crons + CLI-Skripte mit --mandant / Schleife, Shop-Webhooks/Paperless/Kundenanzeige-URLs mit Mandant, Lizenz je Mandant (passt zur Bindung Firma+UID). Aufwand mittel (viele verstreute Pfade). Variante „mandant_id in jeder Tabelle“: sehr groß + riskant → nein.

**Why:** Lizenzbindung ist je Firma, also passt DB je Mandant natürlich. **How to apply:** Erst wieder aufgreifen, wenn Jacky es anspricht oder ein Kunde mehrere Firmen hat.
