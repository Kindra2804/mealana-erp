---
name: project-whitelabel-branding
description: "NAHTLOS-Branding entschieden + umgesetzt: Root-Pfad konfigurierbar, Software-Logo ersetzt Kunden-Logo im Header"
metadata: 
  node_type: memory
  type: project
  originSessionId: 11cd53c7-877f-4fd4-89a6-5f721bd74ce1
---

## Status: Root-Pfad FERTIG, Logo ENTSCHIEDEN (wartet auf Asset-Dateien) — Stand 2026-07-03

Ursprünglich zwei offene Punkte aus der Installationsanleitung (Weitergabe an andere Betriebe, siehe [[project_installationsanleitung]]):

### 1. URL-Pfad `/mealana/` konfigurierbar — FERTIG (2026-07-03)
`erp/config/bootstrap.php` definiert `BASE_PATH` automatisch aus `$_SERVER['SCRIPT_NAME']` (erster Pfadteil, z.B. `/mealana`) — passt sich an, egal wie der Installationsordner/Symlink heißt. Eingebunden in `auth_check.php`, `login.php`, `index.php` (deckt praktisch jede Seite ab). JS-Dateien nutzen `window.BASE_PATH` (gesetzt in `shell_top.php` `<head>`). Alle 449 hartcodierten Stellen in 122 Dateien ersetzt (PHP-Tokenizer-Script für den Großteil, ~19 Heredoc-Sonderfälle mit `{$basePath}`-Interpolation von Hand).

### 2. Software-Branding "NAHTLOS" — ENTSCHIEDEN (2026-07-03), Umsetzung wartet auf Assets
Barbara hat ein eigenes Software-Logo designt: **NAHTLOS** — "ERP für Handarbeitsgeschäfte", Kleeblatt/Nadel-Icon in Koralle/Grau/Türkis-Verlauf, Wortmarke in Navy.

**Entscheidung (nicht mehr offen):**
- Das Kunden-/Shop-Logo im Header **entfällt komplett** — wird durch das NAHTLOS-Icon ersetzt (gleiche Größe wie bisheriges Logo, aktuell 36px Höhe, `.erp-nav-logo img`)
- Nur die reine Grafik im Header (Icon ohne Schriftzug), da 52px Nav-Höhe zu knapp für die Vollversion ist
- Überall wo genug Platz ist (Login, Start/Dashboard, Kassa-Auswahl, Packplatz-Auswahl) wird die **Vollversion** (Icon + Schriftzug + Tagline) prominent gezeigt
- Begründung/Vorbild: wie JTL-WAWI oder Shopware-Backend — das Tool zeigt die Software-Marke, nicht die Marke des Kunden. Kunden-Branding lebt in Rechnungskopf/Webshop (Firma-Einstellungen haben eigenes Logo-Feld dafür, bleibt unberührt)
- Spart den ganzen "Logo pro Installation konfigurierbar machen"-Baustein aus der Weitergabe-Anforderungsliste komplett ein

**Blocker:** Wartet auf echte Export-Dateien von Barbara (SVG oder PNG transparent, einmal Icon-only, einmal Vollversion). Jacky hat sie gerade angefordert (Stand 2026-07-03).

**Betroffene Stellen sobald Dateien da sind:**
- `erp/public/includes/shell_top.php` — Logo-`<img>` ersetzen (einfacher Tausch)
- `erp/public/kasse/shell_top.php` + `erp/public/packplatz/shell_top.php` — haben aktuell KEIN Logo, noch klären ob Icon rein soll
- `erp/public/login.php` — aktuell nur Text ("MeaLana"/"ERP"), perfekt für Vollversion-Bild
- Start/Dashboard + Kassa-Auswahl + Packplatz-Auswahl — noch nicht identifiziert/gebaut wo genau

### 3. Neue Idee: "Powered by NAHTLOS" im Shop-Footer + Brandfree-Modulpaket (2026-07-03, noch nicht geplant)
Analog "Powered by JTL-Shop": NAHTLOS-Hinweis im Footer der WooCommerce-Shops, mit einer **brandfreien Option als kaufbares Modulpaket** (passt zum bestehenden Lizenz-Pakete-Modell, siehe [[project_rechte_rollen]]).
Jacky war unsicher ob das mit WooCommerce vereinbar ist — ist es: reine Theme-Anpassung im eigenen Shop, keine WooCommerce/WordPress-Lizenzeinschränkung (GPL erlaubt beliebige Footer-Anpassungen). Muss also nicht auf ein eigenes Shopsystem warten.

**Why:** NAHTLOS wird zunehmend als eigenständige Produktmarke behandelt (nicht nur MeaLana-intern) — relevant für die geplante Weitergabe an andere Betriebe.
**How to apply:** Sobald Barbaras Logo-Dateien da sind: Header-Tausch + Vollversion-Platzierungen umsetzen. "Powered by"-Footer + Brandfree-Paket erst wenn Shop-Sync/eigener Shop ansteht, dann als neues Paket in der Lizenz-Pakete-Tabelle ergänzen.
