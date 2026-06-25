# 04 — Auftragsmodul

## Konzept

Jeder Auftrag hat zwei getrennte Status:

| Status | Was er bedeutet |
|--------|-----------------|
| **Zahlungsstatus** | Wurde bezahlt? (ausstehend / bezahlt / teilbezahlt / storniert) |
| **Lieferstatus** | Wurde versendet? (neu / in Bearbeitung / versandbereit / versendet / abgeschlossen) |

Diese zwei sind absichtlich getrennt — ein Auftrag kann bezahlt aber noch nicht versendet sein, oder umgekehrt.

---

## Auftrags-Liste

**Navigation:** Aufträge → Auftragsübersicht

Filter oben:
- **Suche:** Auftragsnummer, Kundename, E-Mail
- **Zahlungsstatus:** z.B. nur "ausstehend"
- **Lieferstatus:** z.B. nur "versandbereit"
- **Zeitraum:** Aufträge von … bis …

---

## Auftrag manuell anlegen

**Navigation:** Aufträge → Neuer Auftrag

### Schritt für Schritt:

1. **Kunden** suchen (Name, E-Mail) oder "Laufkunde" für anonyme Käufer
2. **Zahlungsart** wählen (Vorkasse, Rechnung, Bar, PayPal, Nachnahme)
3. **Lieferart** wählen (Versand oder Abholung)
4. **Artikel hinzufügen:**
   - EAN scannen oder Artikelnummer/Name eingeben
   - Menge anpassen
   - Preis (wird automatisch aus dem Preissystem befüllt, kann überschrieben werden)
5. Optional: **Notiz intern** (wird nur intern gesehen) und **Notiz Versand** (erscheint auf Lieferschein)
6. → **Auftrag speichern**

> Das System generiert automatisch eine Auftragsnummer (A-2026-00001).

---

## Zahlungseingang buchen {#zahlungseingang}

Wenn eine Überweisung eingegangen ist:

1. Auftrag öffnen
2. Button **"Zahlung buchen"**
3. Zahlungsart und Betrag bestätigen
4. → Zahlungsstatus wird auf "bezahlt" gesetzt
5. System sendet automatisch Auftragsbestätigung per Mail (wenn konfiguriert)

> **Vorkasse-Aufträge:** Lager-Abgang wird erst bei Zahlungseingang gebucht.

---

## Status manuell ändern

1. Auftrag öffnen → Bereich "Status"
2. Gewünschten Zahlungs- oder Lieferstatus wählen
3. Optional: Notiz hinzufügen
4. Speichern

---

## Auftrag bearbeiten

Positionen und Stammdaten können geändert werden, solange der Auftrag noch nicht "versendet" oder "abgeschlossen" ist.

1. Auftrag öffnen → **Bearbeiten**-Button
2. Artikel hinzufügen / entfernen / Menge ändern
3. Speichern → Gesamtbetrag wird neu berechnet

---

## Auftrag stornieren

1. Auftrag öffnen → **Stornieren**-Button
2. Bestätigung
3. System setzt beide Status auf "storniert"
4. Bei bereits ausgebuchtem Lagerstand: Ware wird automatisch zurückgebucht

> **Achtung:** Wenn die Ware bereits auf dem Weg ist (versendet), erscheint eine Warnung. In dem Fall muss die Retoure manuell über den Packplatz abgewickelt werden.

---

## Mahnwesen — automatisch

Der Cronjob läuft täglich und prüft automatisch:

| Zeitraum | Zahlungsart | Aktion |
|----------|-------------|--------|
| 14 Tage offen | Vorkasse oder Rechnung | Zahlungserinnerung per Mail |
| 30 Tage offen | **Vorkasse** | Automatische Stornierung + Ware wird zurückgebucht |
| 30 Tage offen | **Rechnung** | Nur Hinweis (kein Auto-Storno — Ware ggf. bereits geliefert!) |

> **Warum kein Auto-Storno bei Rechnung?** Bei Rechnungszahlern ist die Ware oft schon unterwegs. Ein automatischer Storno würde den Lagerstand falsch zurückbuchen. Bei Rechnung ist daher manuelle Prüfung nötig.

Das Protokoll der gesendeten Mahnungen ist in der Auftragsliste sichtbar.

---

## Häufige Probleme

| Problem | Lösung |
|---------|--------|
| Auftragsnummer fehlt | Wurde der Auftrag tatsächlich gespeichert? F5 drücken und in Liste suchen |
| Preis im Auftrag ist falsch | Bearbeiten → Position-Preis manuell korrigieren |
| Mahnung wurde nicht gesendet | Mail-Einstellungen prüfen (Einstellungen → Mail/SMTP → Test-Mail) |
| Storno geht nicht | Auftrag schon "abgeschlossen"? Dann muss Storno manuell mit Notiz vermerkt werden |
