---
name: project-widerrufsbutton
description: "Widerrufsbutton (EU-RL 2023/2673, Pflicht seit 19.06.2026) in allen Woo-Shops über Germanized ab 4.0, kein eigenes Plugin nötig; Stand indra-design 2026-11-05"
metadata:
  node_type: memory
  type: project
  originSessionId: 745e566f-eead-45c0-b033-974ece3f704d
  modified: 2026-11-05T15:58:38.534Z
---

Jacky am 2026-11-05: Der Widerrufsbutton muss in alle Woo-Shops. Die Gewährleistungs-Info kommt schon über Germanized und muss nur je Shop aktiviert werden.

**Ergebnis der Prüfung:** Germanized (gratis) hat den Widerrufsbutton ab Version 4.0 eingebaut: WooCommerce → Einstellungen → Germanized → Allgemein → Abschnitt „Widerrufsbutton“ (`tab=germanized-general&section=withdrawal_button`). Wir brauchen also **weder ein eigenes Plugin noch eigenen Code**.

**indra-design.at (Germanized 4.1.6), gelesen am 2026-11-05:** schon aktiv. Seite „Vertrag widerrufen“ (ID 49, /vertrag-widerrufen/), im Footer eingebettet = AN, **Überall anzeigen = aus** (der Button erscheint nur auf Shop-Seiten, nicht auf der Startseite), Teilwiderrufe AN, Frist 14, nicht widerrufbar = Virtuelles Produkt, **Kontakt-E-Mail leer**, **Mein-Konto-Link aus**, Gastvalidierung „Nicht validieren“.
**✅ 2026-11-05 umgestellt (Jacky-OK):** Überall anzeigen AN, Kontakt-E-Mail office@indra-design.at, Mein-Konto-Link AN (Skript `wp_widerruf_setzen.js`; die Kippschalter sind versteckte Checkboxen, darum per evaluate setzen). Cache geleert, der Button ist jetzt auch auf Startseite und Kontakt zu sehen. **Offen:** Test-Widerruf. Danach einen Test-Widerruf machen und dabei prüfen, was mit dem Bestellstatus passiert und ob der ERP-Sync darauf reagiert.

**How to apply:** Bei jedem neuen Woo-Shop (SOW, bio-wolle, MeaLana) auf die Checkliste setzen: Germanized ≥ 4.0, Widerrufsbutton + Gewährleistungs-Info aktivieren, gleiche Einstellungen wie auf indra-design. Prüfskript nur lesend: `.claude-browser-tools/wp_widerruf_check4.js`. Siehe [[project-soft-golive-sockenwolle]], [[project-bio-wolle-shop-design]].
