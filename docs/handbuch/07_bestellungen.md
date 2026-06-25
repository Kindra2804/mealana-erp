# 07 — Bestellungen (Einkauf)

## Wofür?

Hier werden Lieferantenbestellungen verwaltet — was wurde wann bei welchem Lieferanten bestellt, und was ist davon bereits eingetroffen.

> Das ist der **Einkauf** (wir bestellen bei Lieferanten).  
> Nicht zu verwechseln mit dem Auftragsmodul (Kunden bestellen bei uns).

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

**Navigation:** Bestellungen → Neue Bestellung

1. **Lieferant** auswählen
2. **Artikel** hinzufügen:
   - Artikelnummer / Name suchen
   - Bestellmenge eingeben
   - EK-Preis eingeben (vom aktuellen Angebot des Lieferanten)
3. Optional: **Notiz** (z.B. "Dringend — für Messe-Termin")
4. → Speichern → Bestellnummer wird generiert

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
| Lieferant fehlt in der Liste | Lieferant muss erst im Artikelmodul angelegt werden (Artikel → Lieferanten) |
| Eingebuchte Menge falsch | Lagerbewegung kann nicht rückgängig gemacht werden — Korrektur über einen neuen Wareneingang mit negativer Menge (Abgang) |
