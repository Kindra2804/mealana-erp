---
name: project-idee-massenaktion-preis
description: "Massenaktion „Preise ändern“ in der Artikelliste (Jacky 2026-11-14) – Entscheidungen 1–6 getroffen, JTL-Check + Datenlage erledigt, Bau als Nächstes"
metadata:
  node_type: memory
  type: project
  originSessionId: ef85806d-4326-4027-a26f-a0cf057f4a93
  modified: 2026-11-14T12:52:53.334Z
---

**Idee (Jacky 2026-11-14):** Massenaktion in der Artikelliste, um den VK vieler Artikel auf einmal zu ändern. Anlass: Lieferanten-Preiserhöhung (z.B. Drops). Erster Punkt der neuen Reihenfolge, siehe [[project-roadmap-reihenfolge]].

**Entscheidungen Jacky 2026-11-14:**
1. Art: fester Preis / + x € / + x % (+ Rundung, z.B. ,90 / ,50). „+x %“ ist inzwischen selten, aber mit dabei.
2. Ziel-Preis per **Dropdown**, vorausgewählt **Standardpreis**; weitere Ziele: andere Kundengruppen (Händler …), UVP. Händler haben meist eigene Vereinbarungen.
3. Kinder mit eigenem (abweichendem) Preis: auslassen + blaues „!“ in der Vorschau, per Haken trotzdem mitnehmen.
4. Vorschau-Tabelle alt → neu ist Pflicht, **neuer Preis je Zeile überschreibbar**.
5. Shop-Sync + Preisschilder „veraltet“ ziehen nach.
6. Protokoll ins Aktivitäten-Log (wer, wann, wie viele, welcher Wert) – wichtig für Nachvollziehbarkeit.

**Datenlage Dev (2026-11-14), wichtig für den Bau:**
- Kein echtes Vererben: alle 17.852 Kinder haben eine eigene Zeile in `artikel_preise` (KG 1). `preis_speichern.php` am Vater gibt NICHT an Kinder weiter. `artikel.preise_vererben` wird nur gelesen, ist überall 0 (toter Schalter).
- „Kind mit eigenem Preis“ = Kindpreis ≠ Vaterpreis (vorher). 262 Väter haben solche Kinder (z.B. Größen).
- Andere Kundengruppen: 0 Zeilen; Staffeln: 0; UVP: bei 21.059 Artikeln gesetzt.
- `artikel_preise` hat brutto + netto → netto aus der Steuerklasse neu rechnen.
- Preisänderung setzt `artikel.aktualisiert_am` NICHT von selbst → explizit setzen, sonst kein Shop-Sync.

**JTL-Check (guide.jtl-software.com „Preisberechnung“):** Rechtsklick → Preisberechnung; Preisbasis wählbar (VK, UVP, EK …) ≠ Ziel-Preis; Anpassungstabelle nach Preisbereichen („ab 10 €: +5 %“, Operatoren + − × / = +% −%); „Smart Rounding“ je Preisbereich; Vorschau, gespeichert wird genau die Vorschau; Einstellungs-Sets speichern/laden; Protokoll-Reiter. Varianten nicht beschrieben.
- **Entschieden Jacky 2026-11-14:** getrennte Rechenbasis kommt gleich mit (z.B. Händler = Standard − 30 %), Protokoll ja. Ziel-Dropdown: Standardpreis (vorausgewählt), andere Kundengruppen, UVP. Preisbereiche + gespeicherte Sets: vorerst weglassen (Scope ohne Bedarf).

**Stand 2026-11-14 nachmittags: gebaut + committed (2205453).** Artikelliste → Aktion „💶 Preise ändern“ → neuer Tab `artikel/preise_massen.php` (Logik `src/modules/preise/PreisMassenService.php`, JS `js/preise_massen.js`, Zugriffsregel artikel.bearbeiten). Vorschau + Speichern per CLI getestet (Netto aus Steuerklasse, Vater+Kind aktualisiert_am, Protokoll `preis.massen` mit alt/neu je Artikel, unten auf der Seite „Letzte Preisänderungen“). Basis 0,00 € gilt als „keine Basis“.
- ✅ `rundePreis()` von Jacky geschrieben (match + ceil in Cent, aufwärts), 12 Prüffälle ok.
- ✅ Klicktest Jacky 2026-11-14 bestanden; danach Protokoll mit Artikelnummer + Name statt nur ID, Handbücher ergänzt (67e2be5). Kommt mit dem nächsten Paket auf Live.
- Fund: 761 Artikel haben Standardpreis 0,00 € (z.B. Lang-Kinder LY-784-0023) → erscheinen als „!“ bzw. „keine Basis“.


## Früherer Index-Eintrag (aus MEMORY.md verschoben 2026-11-18)
- [💶 Massenaktion Preise ändern](project_idee_massenaktion_preis.md) — ✅ gebaut 2026-11-14 (2205453), ✅ fertig + Klicktest 2026-11-14 (67e2be5), Live mit nächstem Paket
