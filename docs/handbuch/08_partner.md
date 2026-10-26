# 08 — Partner-Modul

## Wofür?

Partnerbetriebe und Einzelpersonen, deren Ware wir verkaufen. Jeder Partner hat genau
**eine Verkaufsart** (geklärt mit der Steuerberatung, Oktober 2026):

| Verkaufsart | Was ist das? |
|-----|-------------|
| **Fremdrechnung** | Wir verkaufen **im Namen und auf Rechnung** des Partners. Nie unser Umsatz, nicht in der RKSV, keine Provision. Partner bekommt eine Verkaufsaufstellung + Zahlungsaviso, aber keine Rechnung von uns. |
| **Kommission** | Unser eigener Umsatz (normal RKSV). Der Partner stellt uns eine Rechnung zu vorab vereinbarten EK-Preisen (= netto − Provision), die wie ein normaler Einkauf gebucht wird. |
| **Spende** | Durchlauf (z.B. Yarnpride): eigene Kassenlade, Liste bei der Übergabe. |

Ein **Mietfach** ist bei jeder Verkaufsart zusätzlich möglich. Die Fachmiete ist immer
eine eigene, normale Rechnung an den Partner — sie wird **nie** mit Verkäufen verrechnet.

---

## Partner-Übersicht

**Navigation:** Partner → Partnerliste

Zeigt alle Partner mit Verkaufsart, Mietfächern, Provision (nur Kommission) und Ort.
„Adresse fehlt“ = bitte nachtragen, sie steht am Bon und auf der Abrechnung.

---

## Neuen Partner anlegen

**Navigation:** Partner → Neuer Partner

1. **Name** + **Adresse** (Straße, PLZ, Ort, Land)
2. **Verkaufsart** wählen — darunter steht kurz, was sie bedeutet
3. Nur bei **Kommission**: **Provision %** (immer auf netto) und der **Lieferant**, der uns die Rechnung stellt
4. **Kleinunternehmer** anhaken, falls zutreffend → optional eigener Text für den Bon (leer = Standardtext „Umsatzsteuerbefreit – Kleinunternehmer gem. § 6 Abs. 1 Z 27 UStG“)
5. Kontaktdaten, IBAN, UID/ZVR → Speichern

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

### Partnerware am Kassenbon

Der Kunde bekommt immer **einen** Bon und zahlt den ganzen Betrag auf einmal (bar oder Bankomat).
Im Warenkorb steht bei Partnerware klein „im Namen von …“ bzw. „Spende für …“.

| Verkaufsart | Am Bon | RKSV / Umsatz | Geld |
|---|---|---|---|
| **Kommission** | normale Zeile | normal signiert, eigener Umsatz | normal |
| **Fremdrechnung** | eigener Abschnitt „Verkauft im Namen und auf Rechnung von …“ mit Name, Adresse, UID — bei Kleinunternehmern „ohne USt“ + KU-Text | **nicht** signiert, **kein** eigener Umsatz | bleibt in der Lade (durchlaufend), wird an den Partner weitergegeben |
| **Spende** | eigener Abschnitt „Spende für …“ mit ZVR | **nicht** signiert, kein Umsatz | Bar-Anteil kommt in die **Spendenlade** und zählt nicht zum Kassenstand |

- Unter „GESAMT“ steht bei Mischbons „davon MEALANA € …“ (= signierter Anteil).
- Ein Bon **nur** mit Fremdrechnung/Spende hat keine RKSV-Signatur — das steht unten am Bon.
- **Z-Bon / X-Bon:** „Umsatz“ ist nur der eigene Umsatz. Darunter steht „Kassiert gesamt (inkl. Partnerware)“ und ein eigener Abschnitt **Partnerumsätze** je Partner; der Bar-Anteil der Spenden (Spendenlade) wird extra ausgewiesen.
- Storno und Rückgabe funktionieren wie gewohnt; die Partneranteile werden automatisch mit zurückgenommen.

**Nachverfolgung:**
- Reiter **Bewegungen** — jede Übernahme, jeder Verkauf (mit Bon-Nr.), jedes Storno, jede Rückgabe.
- Reiter **Belege & Verkaufsliste** — alle Übernahme-/Rückgabescheine; **Verkaufsliste** als PDF für einen frei wählbaren Zeitraum (was wann um wie viel verkauft wurde).

> **Noch nicht gebaut:** Partner-Konto (Saldo, Verkaufsaufstellung + Zahlungsaviso, Auszahlung, Spendenliste, Kontrollliste Kommission), Fachmiete-Monatslauf und die Konten im Buchhaltungs-Export. Bis dahin dient die Verkaufsliste als Grundlage.

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
