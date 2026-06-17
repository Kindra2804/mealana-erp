---
name: projekt-artikel-features
description: "Offene und geplante Features im Artikel-Modul — Prioritäten, MeaLana-Extras, Shop-Vorbereitung"
metadata: 
  node_type: memory
  type: project
  originSessionId: 2201806f-a656-4f8c-9f4f-9cf04a3cdd71
---

Stand: 2026-06-14 (aktualisiert 2026-06-14)

## Zustandsartikel-System (nach Preise-Modul, 2026-06-14)

**Konzept:** Artikel mit `zustand != 'neu'` bekommen eine eigene Artikelnummer mit Auto-Suffix (nicht änderbar), werden aber visuell beim Neu-Artikel angezeigt — nicht irgendwo verloren.

**DB:** `zustand` VARCHAR(30) DEFAULT 'neu' existiert bereits auf `artikel`. Neue Spalte: `zustand_vater_id INT NULL FK → artikel(id)`.

**Zustandswerte + Suffix:**
| Zustand | Suffix |
|---|---|
| neu | — |
| gebraucht | GEB |
| generalueberholt | GUE |
| beschaedigt | BSC |
| retour | RET |
| demo | DMO |
| muster | MST |
| ausstellungsstueck | AST |

**Artikelnummer:** `{vater_artikelnummer}-{SUFFIX}` — auto-gesetzt, read-only wenn `zustand != 'neu'`.

**Zustand ändern (wichtig!):**
- Zustand muss nachträglich änderbar sein
- Bei Änderung: Artikelnummer-Suffix wird automatisch angepasst
- Logger-Eintrag mit Alt/Neu-Zustand
- Lagerbewegung: Ausgang beim alten Zustandsartikel, Eingang beim neuen
- Workflow-Beispiel: RET-Artikel wird geprüft → OK → Umbuchung zurück in Neu-Bestand (eigene Artikelnummer entfernt, Bestand zum Vater)

**UI-Auswirkungen:**
- `neu.php` / `bearbeiten.php`: Zustand-Dropdown + Vater-Suche → Auto-Artikelnummer
- `liste.php`: Zustandsartikel eingerückt unter Vater-Zeile + "!"-Badge auf Vater wenn Zustandsartikel existieren
- `detail.php` Lager-Tab: Card "Zustandsartikel" zwischen Bestandstabelle und Bewegungslog (nur sichtbar wenn vorhanden)
- Kasse (später): Bestätigungsdialog wenn `zustand != 'neu'`

**Why:** In bisheriger WAWI verschwanden B-Ware-Artikel "irgendwo" und fielen erst bei der Inventur auf. Durch visuelle Verknüpfung bleiben sie im Blick ohne den Bestand zu vermischen.

## Meterware / Teilbare Artikel (Feedback Barbara, 2026-06-12)

Stoffe, Meterware, alles was in nicht-ganzen Einheiten verkauft werden kann (0.5m, 1.5m, ...).

**`einheiten`-Tabelle** (bereits geplant, Priority 2) deckt die Einheit ab: Eintrag `kuerzel='m', typ='laenge'`.

**Noch fehlend — auf `artikel`:**
```sql
ALTER TABLE artikel
  ADD COLUMN mengen_schritt DECIMAL(10,3) NOT NULL DEFAULT 1.000,
  -- 0.5 = nur 0.5er-Schritte erlaubt; 0.1 = jede 10cm-Stufe
  ADD COLUMN mindestmenge   DECIMAL(10,3) NOT NULL DEFAULT 1.000;
  -- Kassa + Shop: weniger bestellen = Fehlermeldung
```

**Systemweite Prüfung beim Bauen der jeweiligen Module:**
- `auftrag_positionen.menge` → muss `DECIMAL(10,3)` sein (nicht INT)
- `kassenbon_positionen.menge` → muss `DECIMAL(10,3)` sein
- `lagerbestand.bestand` → muss `DECIMAL(10,3)` sein
- `bestellpositionen.menge_bestellt / menge_geliefert` → bereits DECIMAL ✓

**Shop:** WooCommerce unterstützt Decimal quantities (Einstellung `woocommerce_allow_decimal_quantities`) + custom quantity step per Produkt → wird beim Shop-Adapter berücksichtigt.

## Artikel-Bilder: Vater vs. Kind (2026-06-12)

**Vater-Artikel:** 1 Bild mit mehreren Knäueln = "Stimmungsbild" der Serie → macht Lust
**Kind-Artikel:** Je 1 Bild mit einem Knäuel in der exakten Varianten-Farbe

**Shop-Verhalten:** Besucher wählt Vater-Artikel (Übersicht), dann Farbe/Variante → Bild wechselt automatisch auf das Kind-Bild. Standard E-Commerce-Verhalten, muss beim Shop-Connect so übergeben werden.

**Für Umsetzung:** Die Thumbnail-Spalte in der Artikelliste soll:
- Bei Vater-Zeile: Stimmungsbild
- Bei Kind-Zeile: Einzel-Farbbild des jeweiligen Kinds (hat echte Bilder, keine abstrakten Farbfelder)

## VarKombi-Generator — Upgrade für später (2026-06-12)

- **Konfigurierbarer Artikelnummer/Name-Builder** — wie JTL: Bausteine per Drag&Drop wählen (Vater-Artikelnummer, Variationswert-Name, Variationswert-Artikelnummer, fortlaufende Nummer), Trennzeichen konfigurierbar, Live-Vorschau. Derzeit: fix Vater-Nr + "-" + Wertnamen.
- **Mehr Felder vom Vater vererben** — derzeit erben Kind-Artikel nur Minimum (steuerklasse_id, artikeltyp_id, einheit_id, charge_pflicht, hat_eigenen_lagerstand). JTL vererbt auch: Verkaufspreise, Versandklasse, Lieferanten-Zuordnung, Merkmale. Wenn Praxis-Feedback kommt ("alle Varianten haben falschen Preis"), hier ansetzen.

Stand: 2026-06-07

## liste.php — Kinder/Varianten in der Listenansicht

- **Kind-Artikel in Liste anzeigen (wie JTL)** — Vater-Artikel wird als Zeile angezeigt, darunter (oder daneben) die Kinder mit eigener Artikelnummer. Karl will das wenn die echte View gebaut wird. Referenz: JTL zeigt Varianten mit eigenem Artnr. direkt in der Artikelliste eingerückt unter dem Vater.

## Detail-Page UX (geplant, noch nicht umgesetzt)

- **"Ungespeicherte Änderungen"-Banner** — Anwender-Wunsch (2026-06-15): Im Artikel-Detail-Mockup war oben ein Hinweis-Banner sichtbar wenn Formular-Felder geändert wurden aber noch nicht gespeichert. Implementierung: JS `change`-Event auf alle Inputs im `#stammdaten-form` → kleines Banner/Alert-Strip einblenden (z.B. "Ungespeicherte Änderungen – bitte speichern!"). Gilt wahrscheinlich für alle Tabs mit Formularen. Kommt beim nächsten Design-Feinschliff.

## Jetzt (vor nächsten Modulen abschließen)

- **Tab-State detail.php** — nach ?inaktive=1 Toggle landet man auf Stammdaten statt Varianten-Tab (deferred bis Frontend-Refactor)
- **Filterung in der Liste** — nach Typ, Hersteller, Kategorie, "nur mit Bestand" (derzeit nur Freitext)
- **Kind-Artikel in Artikelliste** — derzeit gefiltert (vaterartikel_id IS NULL), sollen eingerückt unter Vater erscheinen wie in JTL — kommt beim UI-Redesign
- ~~Artikel kopieren~~ ✅ (kopieren.php + kopieren_speichern.php bereits fertig)
- ~~Stray Label-Bug variante_bearbeiten.php~~ ✅ (existiert in aktueller Version nicht mehr)

## Shop-Pflicht: "ab"-Preis bei Varianten-Artikeln

**Regel:** Wenn mind. ein Kind-Artikel einen höheren Preis hat als der Vater → Vaterpreis mit "ab" prefix anzeigen (z.B. "ab 12,90 €").

**Artikelliste (bereits eingebaut 2026-06-16):** `<span style="font-size:10px;color:muted">ab </span>` vor dem Preis wenn `$hatTeureresKind = true`. Logik: foreach $kinder prüft ob `brutto_vk > vater.brutto_vk`.

**Shop-Modul (MUSS rein):** Beim Produkt-Listing und Produkt-Detail im Shop zwingend "ab X,XX €" wenn Varianten teurer sind. Standard-Shopware/WooCommerce-Pattern. Muss beim Shop-Adapter berücksichtigt werden.

**Why:** Kundentäuschung vermeiden — "12,90 €" wäre irreführend wenn die meisten Varianten 15,90 € kosten. "ab 12,90 €" ist rechtlich korrekt.

## Bald (vor / mit Shop-Anbindung)

- **Merkmale-UI** — Tabellen sind da (Nadelstärke, Garngruppe, Maschenprobe), aber kein Formular. Spätestens mit Shop wichtig.
- **Preistabellen-UI** — artikel_preise hat mehrere Kundengruppen (Händler, Kleingewerblich, Vertriebspartner). UI kennt bisher nur Endkunde. Inkl. Staffelpreise (ab Menge X günstigerer Preis).
- ~~**Lieferanten-Tab in detail.php**~~ ✅ Fertig (2026-06-13): Tabelle + Modal Neu/Bearbeiten, AJAX-Save via artikel_lieferant_speichern.php, SQL-Injection-safe, data-Attribute Edit-Pattern.
- **Qualitätslisten** — "Welche Varianten haben keine EAN?", "Welche Artikel haben keinen Bestand?", "Doppelte EAN systemweit?" — hatten wir schon mal besprochen.
- **SEO-Felder** — meta_titel, meta_description pro Artikel (+ Variante?). Wird im Shop wichtig, aber gehört zum Artikel. Schema-Erweiterung vorbereiten.

## Beim Einkaufsmodul (vorbereiten, dann vollenden)

- **Staffelpreise Lieferanten-EK** — `artikel_lieferanten_staffelpreise (id, artikel_lieferant_id, menge_ab, preis)`. Derzeit: ein einzelner `netto_ek` pro Lieferant-Artikel-Kombi. Entschieden 2026-06-13: bewusst weggelassen im Lieferanten-Tab, kommt mit dem Einkaufsmodul wenn Bestellvorschläge mit EK-Kalkulation kommen.

- **Bestellvorschläge** — Artikel unter Mindestbestand. MeaLana-spezifisch: Saisonware berücksichtigen, Berechnung anhand Verkaufszahlen der letzten Zeit / Saison vorbereiten (wird besser wenn mehr Daten da). Schnittstelle zum Bestellwesen/Einkaufsmodul.
  **Why:** Saisonware-Logik ist MeaLana-spezifisch (Wolle hat Saisonpeaks). Formel vorbereiten, auch wenn Daten noch fehlen.

## Zukunft / Nice-to-have

- **Mehrsprachigkeit** — vorbereiten (artikel_translations Tabelle anlegen), aber nicht vertieft einbauen. Relevant wenn Shop in mehreren Sprachen.

## Geplant/Bekannt (aus Roadmap)

- Varianten-System neu (Achsen + Werte) — als kritisch markiert, farbe_name/farbe_hex raus
- VarKombi-System
- Seriennummern
- Bilder-Upload / artikel_dateien

## Wareneingang-Erweiterungen (Lager-Modul, 2026-06-08)

- **Inaktiver Artikel gescannt (Feature 1)** — Dialog: "Artikel X wurde deaktiviert – reaktivieren und buchen?" Ja → aktiv=1 + ist_auslaufartikel=1 setzen (fällt dann unter Jarvis-Schema), geloggt unter eingeloggtem User. Gilt für EAN-Scan UND manuelle Artikelnummer-Eingabe.
- **Doppelte EAN (Feature 2)** — DEFERRED bis Frontend-Refactor. Ist rein frontend-seitig: variante_suche.php liefert bereits mehrere Treffer, wareneingang.php muss bei Exact-Match mit mehreren Ergebnissen einen Auswahl-Dialog zeigen.
- **Inaktiv-Dialog Anzeige verbessern** — DEFERRED bis Frontend-Refactor. Aktuell wird nur `artikel_name` angezeigt. Bei Varianten auch Variantenname (farbe_name) + Artikelnummer mit anzeigen.

## Neue Lücken aus WAWI-Benchmark (2026-06-08)

Vollständige Gap-Liste → [[project-wawi-gaps]]

Artikel-spezifisch noch zu planen:
- **Artikel-Texte** — kurzbeschreibung, beschreibung, technische_details (HOCH — ohne das kein Shop)
- **Gewicht + Maße** — gewicht_gramm, laenge_mm, breite_mm, hoehe_mm (HOCH — Versand)
- **Versandklasse** — neue Tabelle `versandklassen`, FK auf artikel
- **Meldebestand / Sicherheitsbestand / Standardbestellmenge** — auf artikel, JTL auch Override pro lagerbestand
- **Lagerplatz** — `lagerplaetze (id, lager_id, bezeichnung)` + lagerplatz_id auf lagerbestand
- **Tags** — artikel_tags + artikel_tag_zuordnung
- **Artikel-Zustand** — Neu / B-Ware / Sonderposten (VARCHAR auf artikel)
- **MPN** (Herstellerartikelnummer) — Google Shopping, Preisvergleich
- **Farbcode des Herstellers** — Schachenmayr/Lang Yarns Farbbezeichnung (MeaLana-spezifisch)
- **Nadel-Kompatibilität** — Cross-Selling-Basis (MeaLana-spezifisch)

## Bewusst ausgelassen

- Allergeninformationen — nicht relevant für MeaLana (Wolle)
- Pfand (Pfandflaschen etc.) — nicht relevant für Wolle
- Max. Bestellmenge, Verfügbar-ab-Datum — niedrige Priorität, beim Shop-Modul
