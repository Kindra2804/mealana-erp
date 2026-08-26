---
name: project-meterware-mindestabnahme
description: "Meterware braucht Mindestabnahme + Abnahmeintervall + Teilbarkeit (z.B. Vlieseline: 20cm Mindestabnahme, 10cm Intervall, 0,5-Schritte) -- existiert im ERP noch gar nicht, alter JTL-Shop konnte das; WooCommerce nativ ohne Plugin nicht moeglich"
metadata:
  node_type: memory
  type: project
  originSessionId: 1fe37890-2d54-4c96-bcae-65d709049cbe
  modified: 2026-08-13T10:19:41.793Z
---

Jacky zeigte am 2026-08-13 ein Beispiel vom alten JTL-Shop (mealana.at, Vlieseline H250, Meterware-Einlage): "Mindestabnahme 20 cm", "Abnahmeintervall 10 cm", "Stückzahl teilbar (z.B. 0,5)". Sowas gibt's im ERP/WooCommerce-Sync noch gar nicht.

**Bestätigt (Schema-Check 2026-08-13):** `artikel`-Tabelle hat nichts dafür — `standardbestellmenge` ist nur die Bestellvorschlagsmenge Richtung Lieferant (Einkauf), kein kundenseitiges Feld. Betrifft vermutlich hauptsächlich Artikeltyp METERWARE (Meterware/Einlagen/Bänder), evtl. auch manche GARN-Kammzüge/lose Ware.

**Bekannter Stolperstein:** WooCommerce kann Mindestmenge/Schrittweite NICHT nativ (kein Kernfeature) — braucht entweder ein Zusatz-Plugin (z.B. "Min/Max Quantities for WooCommerce" o.ä., noch nicht recherchiert ob es zuverlässig mit Germanized zusammenspielt) oder eigenen Cart-Validierungs-Code im Theme/Snippet. Vor dem ERP-seitigen Datenmodell erst klären, welcher Weg auf der Shop-Seite tragfähig ist.

**How to apply:** Beim Wiedereinstieg wie gewohnt erst Referenz-Check (siehe [[feedback_modul_vorgehen]]) — was macht der alte JTL-Shop genau, was brauchen wir extra. Kein Code bisher, nur der Bedarf festgehalten. Nicht von selbst starten, Jacky hat explizit "für nächste Session" gesagt.
