# 07 — Einkauf: Lieferanten & Bestellungen

## Wofür?

Hier wird der **Einkauf** verwaltet: welche Lieferanten gibt es, was wurde wann bei wem bestellt, und was ist davon bereits eingetroffen.

> Das ist der **Einkauf** (wir bestellen bei Lieferanten).
> Nicht zu verwechseln mit dem Auftragsmodul (Kunden bestellen bei uns).

Lieferanten und Bestellungen gehören im Menü zusammen unter **Einkauf**.

---

## Lieferanten verwalten

**Navigation:** Einkauf → Lieferanten

Lieferanten sind ein **eigenständiges Modul** (`Lieferanten → Liste`), nicht Teil der Artikel-Stammdaten. Die Liste zeigt Name, Land, Website, E-Mail, Telefon und Status (aktiv/inaktiv). Über die Suche lässt sich nach Name, Land oder Website filtern; deaktivierte Lieferanten sind standardmäßig ausgeblendet ("Auch deaktivierte" einblenden).

### Stammdaten

Beim Anlegen/Bearbeiten eines Lieferanten:

- **Name** (Kurzbezeichnung, für Suche/Listen) + optional **Firma** und **Firmenzusatz** (offizieller Rechnungsname, falls abweichend)
- **Land** (Dropdown aus hinterlegter Länderliste, kein Freitext mehr)
- **Adresse**, **Website**, **E-Mail**, **Telefon**
- **Kundennummer** — unsere Kundennummer bei diesem Lieferanten
- **UStID** und **Steuerregel** (Inland / EU-Innergemeinschaftlicher Erwerb / Drittland-Einfuhr / Reverse-Charge) — wichtig für die Buchhaltung
- **Lieferbedingung** (Frei Haus / Ab Werk / Ab Lager / Sonstige)

### Konditionen

Eigener Bereich für Einkaufskonditionen: Zahlungsziel (Tage), Skonto (% + Tage), Mindestbestellwert, Standard-Lieferzeit, Standard-Lieferkosten. Diese Werte sind reine Referenzwerte — beim Anlegen einer Bestellung müssen sie nicht nochmal eingetragen werden, dienen aber als Gedächtnisstütze.

### Bankverbindung

IBAN/BIC/Bank/Kontoinhaber — wird nur angezeigt, wenn mindestens ein Feld befüllt ist. Für Überweisungen bei Rechnungserhalt.

### Vertreter (Ansprechpartner)

Tab **Vertreter**: beliebig viele Ansprechpartner pro Lieferant (Anrede, Name, E-Mail, Telefon, Mobil, Notizen). Nützlich wenn ein Lieferant mehrere Kontaktpersonen für unterschiedliche Themen hat (Verkauf, Reklamation, Buchhaltung).

### Zugänge / Händlerportale

Tab **Zugänge**: Login-Daten für Online-Bestellportale des Lieferanten (Bezeichnung, URL, Benutzername, Passwort, Notizen). Das Passwort ist standardmäßig als Punkte versteckt — Klick auf "Zeigen" blendet es im Klartext ein.

> ⚠️ Passwörter werden hier **unverschlüsselt** hinterlegt (anders als z.B. Kundendaten). Nur für interne Portale mit unkritischen Zugängen gedacht, nicht für sensible Zugänge verwenden.

### Weitere Tabs

- **Artikel** — alle Artikel, die diesem Lieferanten zugeordnet sind (mit EK-Preis, Verpackungseinheit, Lieferzeit, Standard-Lieferant-Kennzeichnung)
- **Bestellungen** — alle bisherigen Bestellungen bei diesem Lieferanten, mit direktem "+ Neue Bestellung"-Button

---

## Bestellübersicht

**Navigation:** Bestellungen → Bestellübersicht

Status-Farben:
- **Offen** — Bestellung raus, Ware noch nicht da
- **Teilweise geliefert** — ein Teil ist eingetroffen
- **Abgeschlossen** — alles erhalten
- **Storniert**

---

## Neue Bestellung anlegen

**Navigation:** Bestellungen → Neue Bestellung (oder direkt vom Lieferanten aus, Tab "Bestellungen")

1. **Lieferant** auswählen
2. **Artikel** hinzufügen:
   - Artikelnummer / Name suchen
   - Bestellmenge eingeben
   - EK-Preis eingeben (vom aktuellen Angebot des Lieferanten)
3. Optional: **Notiz** (z.B. "Dringend — für Messe-Termin")
4. → Speichern → Bestellnummer wird generiert (Format `BE-Jahr-Nummer`)

---

## Bestellung als PDF erstellen & an den Lieferanten senden

In der Bestellungs-Detailansicht, Bereich **"Bestellung an Lieferant"**:

1. **"PDF erstellen"** klicken — erzeugt eine Bestellungs-PDF (Firmendaten, Lieferant, Positionen mit EK-Preis, Summe) und öffnet sie automatisch in einem neuen Tab, zum Ansehen oder Drucken.
2. Kann beliebig oft neu erstellt werden (z.B. nach einer Mengenänderung) — jede Version bleibt in der Dokumente-Historie sichtbar.
3. Nur bei Lieferanten nötig, die eine E-Mail-Bestellung erwarten: neben dem jeweiligen Dokument auf **"Per Mail senden"** klicken. Das öffnet eine Vorschau mit Empfänger/Betreff/Nachricht (vorausgefüllt, aber editierbar) — erst nach Klick auf "Mail senden" geht die Mail tatsächlich raus, inkl. PDF-Anhang.

> Bei Lieferanten, bei denen ihr über deren eigenes B2B-Portal bestellt, reicht meist nur das PDF zum Ausdrucken/Ablegen — dort muss keine Mail verschickt werden.

---

## Wareneingang zur Bestellung buchen

Wenn (ein Teil der) bestellten Ware eintrifft:

1. Bestellung öffnen
2. Button **"Wareneingang buchen"**
3. Für jede Position: Wie viel ist tatsächlich eingetroffen?
4. **EK-Preis** bestätigen (aus Bestellung vorausgefüllt, kann angepasst werden)
5. Optional: **Charge** eingeben
6. → Einbuchen

> Das System bucht die Menge ins Lager und aktualisiert den Bestellstatus.

---

## Teillieferung

Wenn nicht alle Positionen auf einmal kommen:

1. Nur die tatsächlich gelieferten Mengen eingeben
2. Einbuchen → Status wechselt auf "Teilweise geliefert"
3. Beim nächsten Eingang: erneut "Wareneingang buchen" → restliche Mengen eintragen

---

## Häufige Probleme

| Problem | Lösung |
|---------|--------|
| Lieferant fehlt in der Liste | Neuer Lieferant unter Einkauf → Lieferanten → Neuer Lieferant anlegen (eigenes Modul, nicht in den Artikel-Stammdaten) |
| Eingebuchte Menge falsch | Lagerbewegung kann nicht rückgängig gemacht werden — Korrektur über einen neuen Wareneingang mit negativer Menge (Abgang) |
| Bestellungs-Mail kommt beim Lieferanten nicht an | E-Mail-Adresse beim Lieferanten (Stammdaten) prüfen — die Vorschau übernimmt diese automatisch als Empfänger, kann dort aber auch einmalig korrigiert werden |
