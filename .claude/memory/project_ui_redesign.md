---
name: project-ui-redesign
description: "Geplantes UI/UX Redesign — Layout-Struktur, Module, Sondermodule (Stand 2026-06-12)"
metadata: 
  node_type: memory
  type: project
  originSessionId: c55c1aca-b514-4e20-98fa-732e6e1149b3
---

Vollständiges UI-Redesign — wird vor neuen Modulen als Basis umgesetzt.

**Why:** Aktuell inline styles, kein konsistentes Layout — braucht professionelle Basis für alle künftigen Module.

## Grundlayout

- Mindestauflösung: 1280×1024, responsive für größere Screens, Slider wenn kleiner (soll nicht vorkommen)
- Top-Navigation (fix oben)
- Aktionsleiste (kontext-abhängig, zwischen Nav und Inhalt)
- Sidebar links (einfahrbar: ausgeklappt = Icon+Text, eingeklappt = nur Icons)
- Hauptbereich
- Log-Leiste (einklappbar unten, wie VSCode Terminal)
- Statusleiste (1 Zeile ganz unten: User, Lager, Mandant, Sync-Zeit)

## Top-Nav Module (in dieser Reihenfolge)

| Position | Modul          | Shortcut | Anmerkung |
|----------|----------------|----------|-----------|
| 1        | 🏠 Dashboard   | —        | Startseite, KPIs |
| 2        | 📦 Artikel     | Ctrl+1   | |
| 3        | 🏭 Lager       | Ctrl+2   | |
| 4        | 👥 Kunden      | Ctrl+3   | |
| 5        | 📋 Verkauf     | Ctrl+4   | Auftragsübersicht alle Plattformen, NICHT Kassa |
| 6        | 📦 Versand     | Ctrl+5   | |
| 7        | 🔄 Retouren    | Ctrl+6   | |
| 8        | 🛒 Einkauf     | Ctrl+7   | |
| 9        | 📊 Buchhaltung | Ctrl+8   | |
| 10       | ⚙️ (Zahnrad)  | —        | Einstellungen — nur Icon, kein Text-Button |
| 11       | 📌 Sonstiges  | —        | Dropdown für Nischen-Module: Fachvermietung, Strickauftragsabrechnung, etc. |

Rechts außen: 👤 User-Button (Name, Avatar, Lager/Mandant-Wechsel, Logout)

**Grayed-out:** Module ohne Berechtigung werden grau angezeigt, nicht versteckt.

## Sondermodule — eigenes Layout, eigene Rechtestruktur, später angehen

- **Kassa** — eigenes Modul, eigenes Layout, eigene Rechtestruktur
- **Packplatz** — eigenes Modul, eigenes Layout
- **Handyseiten** (Inventur, Umlagerung, Schnell-Artikelinfo mit Lagerstand) — mobiles Modul, eigenes Layout, ganz am Schluss

## Verkaufskanäle (alle eigene Shops, noch zu bauen)

- Kassa Wollboutique
- Kassa Messe
- Shop MeaLana
- Shop Sockenwolle online
- Shop Bio-Wolle
- (weitere Shops geplant)

## Auftrags-Status Workflow

Neu (unbezahlt) → Pickliste → Packplatz (gepickt, kein Label) → Versandt
Zusätzlich: "In Produktion" für Strickaufträge

**Unbezahlt = "Neu"** — Auftrag bleibt Neu solange er nicht bezahlt ist, dann erst Pickliste.

## Dashboard — KPI-Karten (finale Struktur)

**Karte 1: Aufträge**
- Gesamt offen / Neu heute (z.B. 23 / 5)
- Offene Picklisten (z.B. 2)
- Bestandswarnungen (z.B. 3 → Klick zu Details)

**Karte 2: Umsatz Heute** — Betrag + % vs. Gestern

**Karte 3: Umsatz Monat** — Betrag + % vs. Vormonat

**Karte 4: Offene Kundenrechnungen** — Forderungen (was Kunden MeaLana schulden), Betrag + Aging

**Karte 5: Offene Lieferantenrechnungen** — Verbindlichkeiten (was MeaLana Lieferanten schuldet)
- Besonders wichtig: Zahlungsziel-Rechnungen bei Saisonware
- Zeigt: Betrag gesamt + was diese Woche fällig wird

## Assets

- **MeaLana Logo** → `d:\ERP\LOGO.png` (PNG, blau/hellblau, Wollknäuel mit Nadeln)
- Logo deployed: `public/img/logo.png` ✅

## Finetuning-Liste (für später)

- **URL-Rewriting** via `.htaccess` (Apache mod_rewrite) — hübsche URLs statt vollständiger `.php`-Pfade (z.B. `/artikel` statt `/mealana/artikel/liste.php`)
- **Größen-Feinabstimmung** — Nav-Höhe, Abstände exakt auf Mockup-Pixel abstimmen

## Artikel-Liste Design (beschlossen)

- Sidebar: Module-Nav-Items OBEN + Kategoriebaum UNTEN (kombiniert, eine Sidebar)
- Kategoriebaum: Klick filtert Liste, Standard = alle Artikel, Kanal-Chips unter Kategoriename
- Thumbnail: immer kleines 36×24px Bild inline; Hover zeigt 130×130px Popup (besonders wichtig bei ähnlichen Farbnuancen wie Garn)
- Varianten: Elternartikel mit ▶/▼ Expand-Arrow, Kinder eingerückt (20px), keine eigene Thumbnail-Zeile
- Spalten-Konfiguration: User kann Spalten ein/ausblenden über ⚙ Spalten Button
- Bulk-Aktionen: Checkboxen + Aktions-Dropdown (aktivieren, deaktivieren, Kategorie zuweisen, Export)
- Status-Flags als farbige Chips: [Aus]=grau, [Üv]=orange, [Ausl.]=amber, [Fehl.]=rot
- Deaktivierte Zeilen: grauer Hintergrund + graue Schrift
- Kanal-Kürzel: K1=Kassa Wollboutique, K2=Kassa Messe, S1=Shop MeaLana, S2=Sockenwolle, S3=Bio-Wolle + Legende irgendwo
- Pagination mit konfigurierbarer Zeilenzahl (10/25/50/100)

## Artikel-Detail Design (beschlossen 2026-06-12)

### Layout-Grundstruktur
- **Artikelkopf** (sticky, immer sichtbar): Thumbnail + Name (groß) + Art-Nr │ Typ │ Kategorie + Aktiv-Toggle rechts
- **Tab-Bar**: 7 Tabs (Stammdaten | Varianten | Preise | Lager | Bilder | Merkmale | Lieferanten)
- **Sidebar** wechselt im Detail-Modus: zeigt Artikel-Kontext-Box (Art-Nr + Kurzname) + alle Nav-Items + SEO/Statistik als Sidebar-only

### Tab-Entscheidungen
| Tab | Inhalt | Besonderheit |
|-----|--------|-------------|
| Stammdaten | Name, Texte, Maße, Flags | WYSIWYG für Beschreibungen |
| Varianten | Achsen+Generator / Kinder | **Quasi-Slider** (Panel A ↔ Panel B, CSS translateX) |
| Preise | VK/EK, Staffeln, Sale | — |
| Lager | Bestand je Lager, Meldebestand | — |
| Bilder | Galerie, Upload, Swatch | — |
| Merkmale | Nadelstärke, Fasergehalt, Pflegeanw. | — |
| Lieferanten | Zuordnung, EK, Lieferzeit | — |

**SEO + Statistik → Sidebar-only** (selten gebraucht, kein eigener Tab)

### Varianten-Slider (Tab "Varianten")
Zwei Panels, scrollen seitlich per CSS transform:
- Panel A: Achsen-Definition + VarKombi-Generator
- Panel B: Kinder-Liste mit eigenen Zeilen
Toggle-Switcher oben im Tab: [◀ Achsen & Generator] ○─○ [Kinder-Liste ▶]
**Smart-Default:** Generator aktiv wenn noch neue Kombinationen möglich. Automatischer Wechsel zur Kinder-Liste wenn alle Kombinationen bereits existieren.

### Artikelnummer-Builder (im Generator, beschlossen 2026-06-12)
Aufbau: [Vater-Nr.] [Trennzeichen] [Variabler Teil]
- **Vater-Nr.**: immer vorne, fix, nicht verschiebbar
- **Trennzeichen**: editierbar (z.B. "-", "/", "_") ODER leer (kein Trennzeichen)
- **Variabler Teil** — User wählt zwischen:
  - **Achswert**: welche Achse bei Mehrfach-Achsen auswählbar (z.B. Farbe-Wert oder Stärke-Wert oder Kombination beider)
  - **Laufende Nummer**: aufsteigend, nullpadded (01, 02, 03...)
- Verfügbare aber ungenutzte Blöcke als gestrichelte Chips neben der Vorlage
- Live-Vorschau der ersten paar Kombinationen direkt im Builder

### WYSIWYG ✅ FERTIG (2026-07-01)
- **TinyMCE 6.8.6** self-hosted (`public/js/tinymce/`) — MIT-Lizenz, kein API-Key, kommerzielle Weitergabe OK
- **NICHT TinyMCE 7** — v7 braucht Lizenzschlüssel auch self-hosted
- Eingebunden in `artikel/detail.php` + `artikel/bearbeiten.php`
- Init in `artikel_detail.js` + `artikel_bearbeiten.js` (kein license_key nötig bei v6)
- Kurzbeschreibung: Mini-Toolbar (bold/italic/underline), Langbeschreibung: voll (lists/link)

### Sidebar im Detail-Modus
```
ARTIKEL
← zur Liste
────────────
[ART-00456]
Merino 100g      ← Kontext-Box
────────────
● Stammdaten     ← Tab-Links (7)
  Varianten [3]
  Preise
  Lager
  Bilder [5]
  Merkmale
  Lieferanten
────────────
  SEO            ← Sidebar-only
  Statistik      ← Sidebar-only
```

## Design-Workflow

ASCII-Wireframe → SVG-Mockup → HTML-Umsetzung (nie direkt in HTML springen)
Barbara hat Mitspracherecht — SVG-Stufe nicht überspringen.

## Referenzen

JTL-WAWI als grobe Orientierung. Odoo für Log-Stil.

**How to apply:** Basislayout vor neuen Modulen umsetzen. Alle neuen Seiten folgen dieser Struktur.
