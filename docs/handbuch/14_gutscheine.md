# 14 — Gutscheine

> **Fertig:** Gutschein-Artikel, Verwaltung im ERP, Verkauf und Einlösung an der Kasse, Retoure als Gutschein, Storno.
> **Noch offen:** Kauf und Einlösung im Online-Shop (Checkout), Design-Vorlagen hochladen.

## Grundprinzip

- Jeder Gutschein hat einen eigenen Code im Format **MEA-XXXX-XXXX-XXXX** (keine verwechselbaren Zeichen wie 0/O oder 1/I).
- Gültigkeit standardmäßig **10 Jahre** (Einstellung `gutschein_gueltigkeit_tage`).
- Gutscheine sind beim Verkauf **steuerfrei** (Mehrzweckgutschein) — die Umsatzsteuer fällt erst an, wenn damit Ware gekauft wird.
- **Teileinlösung:** Wird nur ein Teil des Guthabens verbraucht, wird der alte Code ungültig und der Kunde bekommt für den Rest einen **neuen Code**. So kann ein Code nie doppelt verwendet werden.

---

## Gutschein-Artikel einrichten (einmalig)

**Navigation:** Artikel → Neu (oder bestehenden Artikel öffnen)

1. **Artikeltyp: "Gutschein"** wählen.
2. **Artikelgruppe: "4700 – Gutscheine"** wählen.
3. Speichern — die Steuerklasse wird automatisch auf **steuerfrei** gesetzt, ein Preis ist nicht nötig (der Betrag wird beim Verkauf eingegeben).

---

## Gutschein an der Kasse verkaufen

1. ⚙ Menü → **🎁 Gutschein verkaufen** (oder den Gutschein-Artikel suchen/scannen).
2. Betrag eingeben oder Schnellbetrag antippen (10 / 20 / 25 / 30 / 50 / 100 €).
3. Optional: **Für** — Name des Beschenkten, erscheint auf dem Gutschein.
4. **+ Hinzufügen** → der Gutschein steht im Warenkorb. Weitere Artikel oder Gutscheine können dazu.
5. Normal bezahlen (Bar, Karte oder Kombi).
6. Nach dem Bezahlen erscheinen **Code und "📄 PDF öffnen"** zum Ausdrucken — danach wie gewohnt der Bon.

> Der Code steht zusätzlich auf dem Kassenbon — falls gerade kein A4-Drucker da ist, reicht der Bon als Nachweis.
> Auf Gutscheine gibt es keinen Rabatt, auch nicht über den Bon-Rabatt.

---

## Mit Gutschein bezahlen

1. **BEZAHLEN** → **🎁 Gutschein**.
2. Code eintippen (Groß-/Kleinschreibung egal) → **Prüfen** (oder Enter).
3. Die Kasse zeigt das Guthaben an:

| Fall | Was passiert |
|------|-------------|
| Guthaben reicht genau oder ist höher | **✓ Einlösen**. Bei Restguthaben bekommt der Kunde nach dem Bezahlen einen **neuen Code** (mit PDF), der auch auf dem Bon steht. |
| Guthaben reicht nicht | Anzeige "Offen: € …" → **Rest bar** (optional gegebenen Betrag eintragen, Rückgeld wird angezeigt) oder **Rest Karte**. |
| Code schon eingelöst | Die Kasse zeigt, auf welchem **neuen Code** das Restguthaben liegt — praktisch, wenn der Kunde die Mail mit dem neuen Code übersehen hat. |
| Code storniert / abgelaufen / unbekannt | Rote Meldung, keine Einlösung möglich. |

> Einen Gutschein kann man nicht mit einem Gutschein bezahlen.

---

## Retoure als Gutschein

Bei einer Rückgabe (z.B. Kunde nimmt bei der Abholung weniger mit) kann statt Bargeld ein Gutschein ausgestellt werden: im Retour-Dialog **🎁 Als Gutschein ausstellen**. Der Bon geht dabei auf € 0,00 und wird normal RKSV-signiert.

---

## Storno

**Navigation:** Kasse → Bon-Journal → Bon → Stornieren

- Wurde auf dem Bon ein Gutschein **verkauft**, wird er mitstorniert, solange er noch nicht benutzt wurde. Wurde er schon (teil)eingelöst, kommt eine Warnung — dann bitte manuell klären.
- Wurde auf dem Bon mit einem Gutschein **bezahlt**, bekommt der Kunde den eingelösten Betrag als **neuen Code** zurück (wird nach dem Storno angezeigt).

---

## Verwaltung im ERP

**Navigation:** Verkauf → Gutscheine

- **Liste** mit Filter nach Status (aktiv / teilweise / eingelöst / abgelaufen / storniert) und Suche.
- **+ Neuer Gutschein:** manuell ausstellen (z.B. Kulanz, Telefon-Bestellung), mit Empfänger, Grußtext, Versand per Mail oder zum Selbstausdrucken.
- **Detail:** komplette Historie (wann ausgestellt, wo eingelöst, welcher Nachfolger-Code), PDF, erneut versenden, manuell einlösen.

---

## Kassenbericht & Buchhaltung

- X-/Z-Bon zeigen unter **Gutschein** nur den tatsächlich per Gutschein bezahlten Teil. Ein Barrest zählt zum Bargeld (Kassenstand stimmt).
- Gutschein-Verkäufe laufen auf die Artikelgruppe **4700 Gutscheine** (0 %).
- Bons mit **Gutschein + Restzahlung** erscheinen im Buchhaltungs-Export wie Kombi-Zahlungen als Hinweis zur manuellen Buchung.
