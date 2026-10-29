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

### Fach vermieten

1. **Partner → Mietfächer** → beim freien Fach **Vermieten**
2. Partner, **Mietbetrag/Monat netto**, MwSt (20 %)
3. **Mietbeginn = immer ein Monatserster** (vorbelegt: nächster Monatserster), Mietende leer = unbefristet
4. → Vertrag starten (hat der Partner ein Partner-Lager, wird das Fach dort sofort Lagerplatz)

### Fach bearbeiten

Der **Standard-Mietpreis** ist netto (Vorschlag beim Vermieten). **Fach aktiv** abhaken sperrt das Fach nur
für neue Vermietungen — ein laufender Vertrag wird weiter verrechnet (das Bearbeiten-Fenster weist darauf hin).
Zum Beenden **Kündigen** verwenden.

### Kündigen

**Beenden** beim belegten Fach → Mietende ist **immer ein Monatsletzter** (vorbelegt: Ende des laufenden
Monats). Ein schon verrechneter Monat kann nicht weggekündigt werden — dann zuerst die Mietrechnung gutschreiben.

### Fachmiete verrechnen

Die Fachmiete ist eine ganz normale Rechnung an den Partner — **nie** mit seinen Verkäufen verrechnet.
Monatlich **im Voraus**: ab **2 Tage vor Monatsende** wird der Folgemonat verrechnet.

- Karte **Fachmiete verrechnen** auf der Mietfächer-Seite: zeigt, was fällig ist → **Jetzt verrechnen**.
  Läuft zusätzlich täglich automatisch (`cron/fachmiete.php`, wie das Mahnwesen).
- Je Partner und Monat **eine Rechnung** mit einer Zeile pro Fach („Fachmiete R1-F1 · November 2026“),
  eigener Nummernkreis **MR-** (Einstellungen → Nummernkreise), Leistungsdatum = Monatserster, Konto 4800 (Gruppe „Fachmiete“, änderbar in Buchhaltung → Artikelgruppen).
- Beim ersten Mal wird aus den Partnerdaten automatisch ein **Kunde** angelegt (Adresse nötig!).
- Danach ist es ein normaler Auftrag: Rechnung mailen/drucken, **Zahlung buchen**, an der Kasse
  „Rechnung bezahlen“, Mahnwesen — alles wie gewohnt. Verpasste Monate werden nachgeholt, nie doppelt.

> Die Vertragshistorie bleibt erhalten — es ist nachvollziehbar wer wann welches Fach hatte.

---

## Partner-Lager und Partnerware

Ware eines Partners liegt in einem **eigenen Lager des Partners**, nicht im Ladengeschäft-Bestand.

**Einrichten:** Partner → Name anklicken → **Partner-Lager anlegen**. Die gemieteten Fächer werden automatisch Lagerplätze darin (z.B. „Mollramer Seifen Fabrik · R1-F1“). Wird später ein Fach neu vermietet, kommt es beim Start des Mietvertrags automatisch dazu. Wechselt ein Fach den Mieter und liegt dort noch Ware des Vormieters, bleibt es beim Vormieter, bis die Rückgabe gebucht ist.

**Partner-Artikel** (Reiter **Artikel**):
- Artikelnummer beginnt immer mit `XP` + Partner-ID, z.B. `XP01-SEIFE-LAV` — den Teil dahinter wählt ihr frei; **leer lassen** = automatisch `XP01-0001`, `XP01-0002` … Eigene Artikel dürfen nicht mit „XP“ + Zahl beginnen.
- Partnerware **nur hier** (oder beim „Ware übernehmen“) anlegen, nie über die normale Artikelanlage — dort wird die Gruppe „Partnerware“ abgelehnt, weil der Artikel sonst keinen Partner, keine XP-Nummer und kein Partner-Lager bekäme.
- Bezeichnung, Verkaufspreis, MwSt, EAN, Fach.
- Partnerware steht **nicht** in der normalen Artikelliste (Umschalter „inkl. Partnerware“ zeigt sie zur Ansicht) und geht **nie** in den Onlineshop.

**Ware übernehmen / zurückgeben** (Reiter **Bestand**):
- **📥 Ware übernehmen** — fehlt ein Artikel noch, **+ Neuer Artikel** legt ihn direkt dort an (Bezeichnung, Preis, MwSt, optional Nummer/EAN/Fach) und setzt ihn mit Menge 1 in die Liste. Sonst: Mengen eintragen oder ins Suchfeld EAN scannen / Artikelnummer (auch ohne „XP01-“) / Name tippen: die Liste filtert mit, Enter = +1 beim passenden Artikel → **Buchen** → **Übernahmeschein** (US-Nummer) als PDF zum Unterschreiben.
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

### Partner-Konto und Auszahlung (Fremdrechnung / Spende)

Partner öffnen → Reiter **Konto & Auszahlung**. Das Geld aus Fremdrechnung und Spende gehört dem
Partner — wir verwahren es nur, daher gibt es **keine Rechnung und keine Gutschrift** von uns.

- **Offen (wir schulden)** = alle Verkäufe an der Kasse − alle Auszahlungen. Bei Spenden zusätzlich
  **In der Spendenlade**: der bar kassierte Anteil, der noch nicht übergeben wurde (der Rest kam per
  Karte auf unser Konto).
- Zeitraum wählen → **📄 Verkaufsaufstellung** bzw. **Spendenliste (PDF)** — monatlich oder jederzeit auf Abruf.
- **Auszahlung buchen**: Betrag (vorbelegt mit dem offenen Saldo), Datum, Art:
  - **Überweisung** — nur vermerkt, überweisen über die Bank (IBAN steht am Aviso)
  - **Bar aus der Kasse** — Kasse wählen; wird als Kassenbuch-Entnahme gebucht
  - **Spendenlade übergeben** (nur Spende) — höchstens der Inhalt laut Kasse, kein Kassenbuch
  → es entsteht ein **Zahlungsaviso** (ZA-Nummer) als PDF mit Unterschriftfeldern bei Barübergabe.
- Mehr als den offenen Saldo kann man nicht auszahlen. Eine falsche Auszahlung mit **↶** stornieren —
  Bar aus der Kasse wird dabei per Kassenbuch-Einlage zurückgebucht.

### Kommission: EK und Kontrollliste

- Beim Partner (✎) den **Lieferanten** hinterlegen, der uns die Rechnung stellt, und die Provision (auf netto).
- Beim Partner-Artikel das Feld **EK netto** ausfüllen — **„aus Provision“** rechnet VK netto − Provision vor.
  Der EK wird wie bei jedem Einkauf beim Lieferanten gespeichert.
- Reiter **Kontrollliste**: verkaufte Menge × EK je Artikel im Zeitraum (auch als PDF) — zum Abgleich mit
  der Rechnung des Partners. Die Rechnung selbst wird wie ein normaler Einkauf gebucht; ausgezahlt wird hier nichts.

### Buchhaltungs-Export

Der Export (Buchhaltung → DATEV/CSV-Export) bucht Partnerware getrennt: Fremdrechnung auf das
Verrechnungskonto Partner (3710), Spenden auf Durchlaufende Spenden (3720, nur der unbar bezahlte Teil),
Kommission als Erlös (4080), Auszahlungen gegen Kassa/Bank. Konten einstellbar unter **Partner-Konten**
auf der Export-Seite — Details im Buchhaltungs-Handbuch.

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
