# 02 — Preise & Aktionen

## Wie funktioniert die Preis-Rangordnung?

Wenn mehrere Preise für einen Artikel gelten, gewinnt immer der spezifischste:

```
1. SALE-Override (manueller Aktionspreis) — höchste Priorität
2. Aktionspreis (aus einer laufenden Aktion)
3. Kundengruppen-Preis (z.B. Großhändler-Preis)
4. Standard-Preis — niedrigste Priorität, immer vorhanden
```

---

## Standard-Preis setzen

1. Artikel öffnen → Tab **Preise**
2. Bei "Standard-Kundengruppe" den **Brutto-VK** eingeben
3. Das System berechnet Netto automatisch
4. Speichern (kleines Speichern-Symbol in der Zeile)

---

## Kundengruppen-Preise

Verschiedene Kundengruppen (z.B. Privat, Händler, Mitarbeiter) können unterschiedliche Preise haben.

1. Artikel → Tab Preise
2. Zeile der gewünschten Kundengruppe suchen
3. Brutto-VK eingeben
4. Optional: **Gültig ab** / **Gültig bis** Datum eingeben
5. Speichern

> **Tipp:** Wenn keine separate Zeile für eine Kundengruppe vorhanden ist, gilt automatisch der Standard-Preis.

---

## Staffelpreise

Ab einer bestimmten Menge wird es günstiger:

1. Artikel → Tab Preise → Bereich **Staffelpreise**
2. **"+ Staffelpreis hinzufügen"** klicken
3. **Ab Menge:** (z.B. 3) und **Brutto-VK** eingeben
4. Speichern

> Beispiel: Normal 5,90 € — ab 3 Stück 5,50 € — ab 10 Stück 5,00 €

---

## UVP (Unverbindliche Preisempfehlung)

Wird als Streichpreis neben dem VK angezeigt.

1. Artikel → Tab Preise → **UVP** Feld
2. Betrag eingeben
3. Speichern

---

## Aktionen {#aktionen}

Zeitlich begrenzte Rabattaktionen für ganze Kategorien.

### Neue Aktion erstellen:

**Navigation:** Artikel → Aktionen → Neue Aktion

1. **Name** der Aktion eingeben (z.B. "Frühjahrsverkauf 2026")
2. **Zeitraum:** Von/Bis Datum wählen
3. **Kategorien zuweisen:** Welche Artikel-Kategorien sollen Teil der Aktion sein?
4. **Preis-Typ** wählen:
   - Prozent-Rabatt (z.B. −10%)
   - Absoluter Rabatt (z.B. −1,00 €)
   - Fixer Preis
5. **Wert** eingeben
6. Speichern → Aktion startet automatisch zum eingestellten Datum

> **Hinweis:** Aktionen laufen über einen Cronjob — sie werden täglich um 0:00 Uhr aktiviert/deaktiviert. Ein Auftrag kurz nach Mitternacht bekommt möglicherweise noch den Vortages-Preis.

### Aktion manuell starten/stoppen:

- In der Aktions-Liste: Button **Starten** oder **Stoppen**

---

## SALE-Override (sofortiger Aktionspreis)

Für einzelne Artikel einen Aktionspreis schnell setzen — überschreibt alles andere.

1. Artikel → Tab Preise → Button **SALE-Override**
2. **Aktionspreis** (Brutto) eingeben
3. Optional: **Streichpreis** (zeigt durchgestrichenen Original-Preis)
4. Optional: **Gültig von/bis** — leer lassen = sofort und unbegrenzt
5. Optional: **Bis Lagerstand = 0** — Aktion endet automatisch wenn ausverkauft
6. Speichern

> Im Shop und in der Artikelliste erscheint ein roter **SALE**-Chip.

---

## Häufige Probleme

| Problem | Lösung |
|---------|--------|
| Kundengruppen-Preis gilt nicht | Prüfen ob "Gültig bis" abgelaufen ist |
| Aktion wurde nicht aktiviert | Cronjob läuft? Datum prüfen. Manuell über "Starten"-Button |
| SALE-Preis lässt sich nicht entfernen | SALE-Override → leeren Preis speichern oder "Entfernen"-Button |
