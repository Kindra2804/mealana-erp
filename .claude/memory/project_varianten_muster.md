---
name: project-varianten-muster
description: "2026-11-16 Vater-Bestand bei Varianten-Erstellung übergeben + Massenaktion „Varianten-Muster übernehmen“ + „Varianten mitkopieren“ (Commits 86d79e9, c4f4afc), Paket 1.0.22"
metadata:
  node_type: memory
  type: project
  originSessionId: c93adcf2-ed10-4b2f-ab3a-cb436120bb32
  modified: 2026-11-16T16:29:53.458Z
---

**Fund (Jacky 2026-11-16):** Live-Artikel ID-U-A71 (4 Stk., Lager INDRA-DESIGN) bekam Varianten → Bestand blieb beim Vater liegen, unsichtbar (Liste zählt nur Kinder), aber im Lagerwert. Nur dieser eine Fall auf Live. Behebung auf Live: nach Paket 1.0.22 im Tab Varianten „Bestand übergeben“ (oder von Hand korrigieren).

**Gebaut:**
- `LagerService::bestandAufVarianteUebertragen()` (Zeile umhängen bzw. addieren, Bewegungen Ausgang/Eingang „Übergabe an Variante“), Generator verlangt Pflicht-Auswahl, Hinweis-Kasten im Tab Varianten (`artikel/bestand_an_variante.php`, Recht inventur.anlegen).
- `VariantenMusterService` (vorschau/uebernehmen), Seite `artikel/varianten_muster.php` + `js/varianten_muster.js`, Massenaktion „🧬 Varianten-Muster übernehmen“. Abgleich über Achse + Wert-Text; nur ergänzen (Jacky); neue Kinder aktiv (Jacky: sonst sucht Babsi sie / legt neu an); Preis = Ziel-Vater + (Master-Kind − Master-Vater); abweichende Maße mit; Shops des Ziel-Vaters; inaktive Master-Kinder kein Muster.
- Kopieren: Häkchen „Varianten mitkopieren“ (gleiche Logik).
- Nebenfund: `setzeBasisPreis` darf nicht auf rowCount() bauen (MariaDB 0 bei gleichem Wert → doppelte Preiszeile).

**Nachtrag 2026-11-16 (3d8cf14, e04e481):** Preis aus der Achse (artikel_achsen.preis_modus/wert beim Ziel) → wie Generator, sonst Unterschied Master-Kind. Eigentümer-Badge (lila, Partner-Kürzel) in der Artikelliste; Artikel neu: Feld „Gehört“ (nur bei Partnern mit online_verkauf). **Fix:** neue Varianten + Kopien übernehmen partner_id/partner_modus (`uebernimmEigentuemer`) — vorher Hauptfirma. Live prüfen: Varianten von ID-U-A71 vermutlich Hauptfirma → Massenaktion „Eigentümer setzen“ INDRA.

**Offen:** Klicktest Jacky (echtes Übernehmen auf Dev), dann Paket 1.0.22 (keine Migration; nächste Migr. weiter 254). Browser-Test `.claude-browser-tools/varianten_muster_ui_test.js` (nur lesend).

Siehe [[project-1-0-check]], [[project-indra-sortiment]].
