---
name: project-verkauf-workflows
description: "Verkauf-Geschäftsregeln: Zahlungsarten, Mahnprozess, Fehlbestand-Konzept"
metadata: 
  node_type: memory
  type: project
  originSessionId: c55c1aca-b514-4e20-98fa-732e6e1149b3
---

## Zahlungsarten

Hauptsächlich **Vorkasse / PayPal** — fast alle Kunden zahlen vor Versand.
Nur wenige **Stammkunden** zahlen auf Rechnung (Rechnungszahler).

**How to apply:** Aging-Logik und Mahnwesen betrifft primär die kleinen Anzahl Rechnungszahler.

## Offene Bestellungen — Mahnprozess (Rechnungszahler)

- **14 Tage+** ohne Zahlung → automatisches Erinnerungsmail an Kunden
- **30 Tage+** ohne Zahlung → Auftrag stornieren, Artikel wieder freigeben (Lagerbestand zurückbuchen)

**Status-Flags im UI:**
- Unter 14 Tage: kein Flag
- 14+ Tage, Mail noch nicht gesendet: "→ Erinnerung senden"
- 14+ Tage, Mail bereits gesendet: "✓ Mail gesendet"
- 30+ Tage: "⚠ Stornieren?" mit Option Auftrag stornieren + Artikel freigeben

**How to apply:** Diese Logik muss in den Auftrag-Workflow eingebaut werden. Dashboard-Widget zeigt Zusammenfassung mit Links. Stornierung + Artikelfreigabe müssen als Aktion im System verfügbar sein.

## Fehlbestand (Überverkauf)

**Definition:** Artikel mit "Überverkauf aktiviert" die von Kunden bestellt wurden, aber aktuell nicht am Lager sind — sie befinden sich auf Bestelllisten beim Lieferanten.

**Status-Stufen:**
1. Noch nicht bestellt (auf keiner Bestellliste)
2. Bestellt (auf offener Bestellung beim Lieferanten)
3. Im Zulauf (Bestellung bestätigt / Lieferung erwartet)

**Dashboard-Darstellung:** Kachel "Fehlbestand: X Stk. →" mit Link zur Fehlbestandsliste.
Fehlbestandsliste zeigt Artikel + Aufträge + Bestellstatus.

**How to apply:** Im Einkauf-Modul und Lager-Modul muss Fehlbestand prominent sichtbar sein. Beim Wareneingang automatisch den Fehlbestand auflösen und betroffene Aufträge auf Pickliste setzen.
