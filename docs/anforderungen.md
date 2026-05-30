- EAN ändert sich beim Hersteller    → Artikel-Historie / EAN-Log
- VPE hat anderen EAN als Einzelstück → Artikel bekommt mehrere EANs
- Artikel ohne EAN                   → Pflichtfeld ODER "kein EAN" Flag
- Eigene EAN generieren              → möglich! (EAN-8 oder intern)
- Mehrere Lieferanten, verschiedene EKs  → Lieferantenmodul
- Durchschnittlicher EK                  → wird automatisch berechnet
- Spanne anzeigen                        → VK minus Ø-EK = Marge
- Brutto/Netto                           → überall relevant, Grundlage
- MOSS (Mini One Stop Shop)              → Onlineshop, EU-Verkäufe
- konfigurierbare Artikel
- Downloadartikel (Anleitungen)
- Personalisierbare Artikel
- Verkauf auf fremden Namen (Mietfächer für Produkte anderer Unternehmen die bei uns verkauft werden)
- Abrechnung Mietfächer
- Strickaufträge (Abrechnung, Dokumentation, Materialberechnung)

## Import-Modul (geplant)

### Quellen
- JTL-WAWI Export (Erstmigration)
- CSV (Hersteller-Produktlisten, z.B. DROPS)
- Manuell (Einzelartikel)

### Anforderungen
- Konfigurierbar pro Quelle (Feldzuordnung)
- Vorschau vor Import ("was würde importiert?")
- Protokoll (was wurde importiert, was fehlgeschlagen?)
- Duplikat-Erkennung (EAN bereits vorhanden?)
- Vater/Kind Zuordnung beim Import

## Aktions-/Sonderpreismodul (geplant)

### Typen
- Eigene Aktionen (prozentuell oder absolut)
- Hersteller-Aktionspreise (max. VPE vom Hersteller)
- Kundengruppen-Sonderpreise (zeitlich begrenzt)
- Mengenrabatte (ab 3 Knäuel 5% Rabatt)

### Anforderungen  
- Start- und Enddatum
- Priorität (welcher Preis gilt wenn mehrere aktiv?)
- Automatisch aktivieren/deaktivieren
- Protokoll wer wann welchen Preis gesehen hat