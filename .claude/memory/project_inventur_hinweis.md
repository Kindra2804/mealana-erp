---
name: inventur-schwund-typ
description: "bewegungstyp 'schwund' in lager_bewegungen bereits vorhanden — bei Inventur-Modul nutzen"
metadata: 
  node_type: memory
  type: project
  originSessionId: ca0c5951-aedc-432f-be05-4f481a4956fc
---

`lager_bewegungen.bewegungstyp` hat seit Migration 083 den Wert `'schwund'` (neben eingang/ausgang/korrektur/inventur).

**Warum:** Beim Messe-Sync wurde Schwund (Verlust/Beschädigung) eingeführt, damit er im Lagerprotokoll direkt filterbar ist — nicht nur über die referenz-Spalte.

**How to apply:** Beim Inventur-Modul KEINEN neuen Typ anlegen — `'schwund'` über `LagerService::warenSchwund()` verwenden für Inventurdifferenzen (Ist < Soll). Für Inventur-Zählungen selbst den Typ `'inventur'` (bereits vorhanden) nutzen.
