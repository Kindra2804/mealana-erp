---
name: project-bestellmodul
description: Designentscheidungen und Anforderungen für das Bestellmodul (Lieferantenbestellungen)
metadata: 
  node_type: memory
  type: project
  originSessionId: ddb2db19-4f9b-4e55-bc3a-9ddf0bb1637b
---

Bestellmodul ist ein eigenständiges Modul (nach L2), nicht Teil des einfachen Wareneingangs.

## Anforderungen (von Karl, 2026-06-07)

### Wareneingang gegen offene Bestellung
- Übersicht: offene Bestellungen als Kacheln ("Offene Bestellung von [Lieferant]")
- Detail erst beim Anklicken: Artikel, bestellte Menge, EAN, etc.
- EAN-Scan gegen offene Positionen
- Mengen-/Chargenabfrage je nach Artikel-Einstellung
- "Bestelleingang abschließen"-Button: wenn alles da → Bestellung erledigt + archiviert
- Bei Fehlmengen → Rückstandsliste (warten oder verwerfen)
- Vollständige Dokumentation (Bewegungslog)

### Freier Wareneingang (= L2, jetzt)
- Lieferant auswählen oder neu anlegen
- LS-Nummer + EK-Preis erfassen
- Artikel manuell eingeben (bestehender Wareneingang-Flow)

### Geplante DB-Tabellen
- `bestellungen` (purchase orders): lieferant_id, status, datum, ls_nummer, ek_gesamt
- `bestellung_positionen`: bestellung_id, artikel_id/varianten_id, menge_bestellt, menge_eingegangen, ek_preis
- `bestellung_eingaenge`: Verknüpfung mit lager_bewegungen

### Modul-Trennung
- Logische Trennung jetzt (eigene Ordner/Dateien)
- Physische Trennung (Packplatz als eigenes UI) kommt später
- Packplatz = traditionell der Ort für Wareneingang + Kommissionierung

**Why:** Zu groß für L2. Eigene Session wenn wir dazu kommen.

**How to apply:** Wenn Bestellmodul gestartet wird, diese Anforderungen als Basis nehmen. Nicht nochmals von vorne besprechen.
