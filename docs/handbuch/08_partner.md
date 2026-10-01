# 08 — Partner-Modul

## Wofür?

Partnerbetriebe und Einzelpersonen die mit MEALANA zusammenarbeiten — in drei Formen:

| Typ | Was ist das? |
|-----|-------------|
| **Mietfach** | Jemand mietet einen physischen Platz im Geschäft und verkauft dort eigene Ware |
| **Kommission** | Ware des Partners wird bei uns verkauft — Partner bekommt Anteil vom Erlös |
| **Spende** | Überschussware wird gespendet, Gegenwert wird protokolliert |

---

## Partner-Übersicht

**Navigation:** Partner → Partnerliste

Zeigt alle aktiven Partner mit Typ-Chip (Mietfach / Kommission / Spende).

---

## Neuen Partner anlegen

**Navigation:** Partner → Neuer Partner

1. **Name** (Person oder Firma)
2. **Typ** wählen (Mietfach / Kommission / Spende / beides)
3. **Kontaktdaten** eintragen
4. → Speichern

---

## Mietfächer

Mietfächer sind physische Einheiten (Regal, Vitrine, Tisch) im Geschäft.

### Mietfach einem Partner zuweisen:

1. Partner öffnen → Tab **Mietfächer**
2. **"Mietfach zuweisen"**
3. Mietfach-Nummer vergeben (z.B. "MF-01")
4. **Mietbeginn** eingeben
5. **Monatlicher Mietbetrag** festlegen
6. → Speichern

### Mietfach beenden:

1. Partner → Tab Mietfächer → betreffendes Mietfach
2. **"Mietverhältnis beenden"**
3. Enddatum eingeben

> Die Vertragshistorie bleibt erhalten — es ist nachvollziehbar wer wann welches Fach hatte.

---

## Partner-Lager und Partnerware

Ware eines Partners liegt in einem **eigenen Lager des Partners**, nicht im Ladengeschäft-Bestand.

**Einrichten:** Partner → Name anklicken → **Partner-Lager anlegen**. Die gemieteten Fächer werden automatisch Lagerplätze darin (z.B. „Mollramer Seifen Fabrik · R1-F1“). Wird später ein Fach neu vermietet, kommt es beim Start des Mietvertrags automatisch dazu. Wechselt ein Fach den Mieter und liegt dort noch Ware des Vormieters, bleibt es beim Vormieter, bis die Rückgabe gebucht ist.

**Partner-Artikel** (Reiter **Artikel**):
- Artikelnummer beginnt immer mit `XP` + Partner-ID, z.B. `XP01-SEIFE-LAV` — den Teil dahinter wählt ihr frei. Eigene Artikel dürfen nicht mit „XP“ + Zahl beginnen.
- Bezeichnung, Verkaufspreis, MwSt, EAN, Fach.
- Partnerware steht **nicht** in der normalen Artikelliste (Umschalter „inkl. Partnerware“ zeigt sie zur Ansicht) und geht **nie** in den Onlineshop.

**Ware übernehmen / zurückgeben** (Reiter **Bestand**):
- **📥 Ware übernehmen** — Mengen eintragen oder EAN scannen (jeder Scan +1) → **Buchen** → **Übernahmeschein** (US-Nummer) als PDF zum Unterschreiben.
- **📤 Rückgabe an Partner** — höchstens der aktuelle Bestand → **Rückgabeschein** (RS-Nummer).

**Kasse:** Partner-Artikel werden automatisch aus dem Lager ihres Partners abgebucht (Storno bucht dorthin zurück). Eine Rückgabe an der Kasse schlägt beim Einlagern am Packplatz das Partner-Lager vor.

**Nachverfolgung:**
- Reiter **Bewegungen** — jede Übernahme, jeder Verkauf (mit Bon-Nr.), jedes Storno, jede Rückgabe.
- Reiter **Belege & Verkaufsliste** — alle Übernahme-/Rückgabescheine; **Verkaufsliste** als PDF für einen frei wählbaren Zeitraum (was wann um wie viel verkauft wurde).

> **Noch nicht gebaut:** die eigentliche Partner-**Abrechnung** (Gutschrift / Fremdrechnung / Info-Abrechnung) und der Hinweis „im Namen und auf Rechnung von“ auf dem Kassenbon samt RKSV-Trennung — das wird gesondert geplant. Die Verkaufsliste dient bis dahin als Grundlage.

---

## Spenden-Log

Für steuerliche Aufzeichnungen:

1. Partner öffnen → Tab **Spenden**
2. **"Spende erfassen"**
3. Artikel, Menge und Gegenwert eintragen
4. Speichern → Datum und Benutzer werden protokolliert

---

## Häufige Probleme

| Problem | Lösung |
|---------|--------|
| Mietfach-Nummer bereits vergeben | Andere Nummer wählen — Nummern müssen eindeutig sein |
| Kommissions-Artikel erscheint nicht | Artikel dem Partner zugeordnet? (Artikel → Tab Partner) |
