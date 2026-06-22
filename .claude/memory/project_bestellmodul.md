---
name: project-bestellmodul
description: Vollständiges Design für Bestellwesen (PO-Workflow, Wareneingang/Packplatz, Chargen, Teillieferung, Rechnungsabfrage)
metadata: 
  node_type: memory
  type: project
  originSessionId: ddb2db19-4f9b-4e55-bc3a-9ddf0bb1637b
---

## Module-Struktur

Zwei getrennte Verzeichnisse, ein Kontext:

```
modules/
├── bestellungen/      ← PO anlegen, verwalten, Übersicht
└── wareneingang/      ← Scan-UI, Buchung, Chargen
                          (wird auch Packplatz-Modul)
```

## Packplatz-Prinzip

Wareneingang ist eigenständige Seite — keine Abhängigkeit von der vollen ERP-Shell.
Gleiche Codebasis, aber verwendbar ohne Hauptnavigation (eigene Route `/wareneingang/scan`).
Packplatz-PC bekommt später nur Packplatz-Module via Rollen/Station-Konfiguration sichtbar.
Packplatz-Module: Wareneingang, Pickliste/Kommissionierung, Versand/Labelerstellung, Umlagerung.
UI-Stil Packplatz: größere Buttons, EAN-Scan als primäre Eingabe, Cursor immer im Scan-Feld.

## Workflow: Bestellung anlegen

1. Lieferant wählen
2. **Infobox oben**: reservierte Artikel die noch nicht lagernd sind (nur wenn Artikel überverkaufbar UND reserviert)
3. Positionen eingeben: Artikel + Menge bestellt
   - Lieferzeit aus Lieferantenstamm anzeigen, aber bearbeitbar (Klick → freie Eingabe, z.B. "ab KW38")
   - Standard = normale Lieferzeit; Saison-Override frei eintippen
   - Evtl. auf Artikelebene wenn Saison- und Standardartikel auf einer Bestellung gemischt
4. Optionale Felder: Lieferanten-AB-Nummer, Zahlungsart (DROPS = Vorkasse vormerken)
5. [Bestellung speichern] → Status "Offen"

**Bestellvorschlag:** Nur manuelle Infobox "unter Meldebestand" + reservierte Artikel.
Automatische Saisonvorschläge erst wenn mind. 1 Jahr Verkaufsdaten vorliegen — Saisonware (Sommergarne) braucht Langzeit-Beobachtung.

## Workflow: Wareneingang (EAN-Scan)

Kachelübersicht offener Bestellungen → Bestellung auswählen → Scan-Modus:

1. Mengenvorwahl eingeben (z.B. 10)
2. EAN scannen → Position erkannt
3. **Artikelanzeige**: Hauptbild links + Artikelname + EAN + bestellte Menge rechts
   → visueller Abgleich für Praktikanten ob richtiger Artikel gescannt
4. Chargenabfrage (wenn chargenpflichtig):
   - bestehende Charge wählen ODER neue anlegen
   - überspringbar ("zu erfassen" bleibt offen — wir haben schon das "zu erfassen"-Flag)
5. Menge wird gebucht
6. Nächste Mengenvorwahl → nächster Scan

Fortschrittsanzeige pro Position: bestellt / eingegangen / offen

## Workflow: Abschluss

**Vollständig:**
- [Bestellung abschliessen] → Status "Erledigt" + archiviert
- Rechnungsabfrage falls noch nicht vorab erfasst:
  - LS-Nummer (immer Pflicht)
  - Rechnungs-Nummer
  - Rechnungsbetrag (für Dashboard-Kennzahlen)

**Teillieferung:**
Dialog: "Auf Nachlieferung warten" ODER "Abschliessen + Rest streichen"
- "Rest streichen" → Notizfeld "Gutschrift erwartet von [Lieferant]" + bereits gezahlter Betrag sichtbar
- DROPS-Modell: Vorkasse → keine Nachlieferung → Gutschrift auf nächste Rechnung

Lieferantenrechnung kann auch **vorab** erfasst werden (kommt oft per Mail vor Lieferung).

## Logger-Einträge

| Aktion | Logeintrag |
|---|---|
| Bestellung angelegt | Wer, Lieferant, Positionsanzahl, Gesamtbetrag |
| Wareneingang gebucht | Wer, welche Position, Menge, Charge |
| Charge neu angelegt | Wer, Artikel, Charge-Bezeichnung |
| Bestellung abgeschlossen | Wer, Zeitpunkt, Rechnung-Nr., Betrag |
| Rest gestrichen (Teillieferung) | Wer, gestrichene Positionen, Gutschrift-Notiz |

## Geplante DB-Tabellen

- `bestellungen`: lieferant_id, status, datum, ls_nummer, rechnung_nummer, rechnung_betrag, zahlungsart, notiz
- `bestellung_positionen`: bestellung_id, artikel_id, varianten_id, menge_bestellt, menge_eingegangen, ek_preis, lieferzeit_text
- `bestellung_eingaenge`: Verknüpfung mit lager_bewegungen + chargen_id

## Bekannter Bug / Gap

- **KundenService.php fehlt Logger** — als nächstes nachziehen vor Bestellwesen-Start

**Why:** Zu groß für L2. Eigene Session. Packplatz-Anforderung von Anfang an berücksichtigen.
**How to apply:** Diese Anforderungen als Basis nehmen. Wareneingang als eigenständiges Modul bauen, nicht als Unterseite von Bestellungen.
