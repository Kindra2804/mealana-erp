---
name: projekt-artikel-features
description: "Offene und geplante Features im Artikel-Modul — was fertig ist, was noch offen"
metadata:
  node_type: memory
  type: project
  originSessionId: c77183af-9ab6-4b3e-aba9-4dde1a826b7c
---

Stand: 2026-06-17 (Vollständig neu abgeglichen mit tatsächlichem Code-Stand)

## ✅ FERTIG — bereits vollständig implementiert

- **Artikel-CRUD** — neu.php, bearbeiten.php, detail.php (7 Tabs), kopieren.php, delete.php
- **Varianten-System mit Achsen** — varianten_achsen, varianten_achse_werte, artikel_achsen, varianten_kombination_werte (Migrations 022-027); VarKombi-Generator mit kartesischem Produkt, Achsen-Modal in detail.php
- **Preise komplett** — Kundengruppen-Preise, Staffelpreise, UVP, Preis-Aktionen (Migrations 028-031), Tab "Preise" in detail.php
- **SEO-Felder** — meta_titel, meta_description, url_slug auf artikel (Migration 017), Tab "SEO" + seo_speichern.php
- **Artikel-Texte** — kurzbeschreibung, beschreibung, technische_details, beschreibung_intern (Migration 017), in Stammdaten-Tab
- **Gewicht + Maße** — laenge, breite, hoehe, gewicht_artikel, gewicht_versand, versandklasse_id (Migration 018)
- **Zustandsartikel** — zustand VARCHAR(30) DEFAULT 'neu' (Migration 028), zustand_vater_id (Migration 033), alle 8 Zustände (neu/gebraucht/generalueberholt/beschaedigt/retour/demo/muster/ausstellungsstueck), Zustandsartikel in liste.php eingerückt unter Vater
- **Auslaufartikel** — ist_auslaufartikel, auslauf_mit_vater (Migrations 016, 035), Kaskaden-Logik
- **Überverkauf-System** — ueberverkauf_erlaubt, reservierungen-Tabelle (Migrations 020-021)
- **Einheiten** — einheiten-Tabelle inkl. teilbar-Flag (Migrations 013, 032)
- **Lieferanten-Tab** — CRUD, Modal, AJAX-Save, standard_lieferant Flag (detail.php Tab "Lieferanten")
- **Artikel-Liste** — Spalten-Picker (user-spezifisch), Massenauswahl, Sticky-Spalten, Loop-basiertes Rendering
- **Chargen-Tracking** — charge_pflicht, lager_bewegungen mit charge-Spalte
- **Lager & Wareneingang** — wareneingang.php, EAN-Scan, LagerService, Bewegungslog
- **deaktiviert_mit_vater / auslauf_mit_vater** — Kaskaden-Logik bei Vater-Deaktivierung (Migrations 034-035)
- **Hersteller** — hersteller-Tabelle, FK auf artikel
- **Kategorien** — Baum-Manager (AJAX CRUD, Drag-Drop Sort), Viele-zu-Viele auf artikel
- **Lieferanten-Modul** — lieferanten/, CRUD + Vertreter
- **Achsen-Modul** — achsen/, globale Achsenverwaltung

## ⚠ PLATZHALTER — Tabellen da, UI fehlt noch

- **Merkmale-UI** — merkmal_gruppen + merkmale Tabellen vorhanden, Tab "Merkmale" in detail.php zeigt nur Platzhalter. Spätestens vor Shop.
- **Bilder-Upload** — Tab "Bilder" in detail.php ist Platzhalter. Backend fehlt komplett.

## 🔴 NOCH OFFEN im Artikel-Modul (vor nächstem Modul abschließen)

- **Filterung in der Liste** — derzeit nur Freitext-Suche. Fehlend: Filter nach Artikeltyp, Hersteller, Kategorie, "nur mit Bestand". Artikeltyp soll auch als sortierbare Spalte im Spalten-Picker (wie Hersteller).
- **Qualitätslisten** — "Welche Varianten haben keine EAN?", "Welche Artikel haben keinen Bestand?", "Doppelte EAN systemweit?" — geplant, noch nicht gebaut.
- **"Ungespeicherte Änderungen"-Banner** — JS change-Event auf Formular-Inputs → kleines Banner einblenden. Gilt für alle Tabs mit Formularen.

## 🔜 MIT ANDEREN MODULEN (nicht jetzt)

- **Staffelpreise Lieferanten-EK** — kommt mit Einkaufsmodul
- **Bestellvorschläge** — Artikel unter Mindestbestand, Saisonware-Logik
- **Shop-Export** — url_slug, meta_*, Bilder → WooCommerce/Shopware-Adapter
- **Kasse** — Bestätigungsdialog für zustand != 'neu'
- **Inventur** — eigenes Modul, Platzhalter-Spalte in Artikelliste bereits vorbereitet
- **Seriennummern** — geplant
- **Mehrsprachigkeit** — vorbereiten (artikel_translations), aber nicht jetzt

## Spalten-Picker: Platzhalter-Status

| Schlüssel | Status |
|---|---|
| merkmale | ⏳ Platzhalter bis Merkmale-UI fertig |
| lagerplatz | ⏳ Platzhalter bis lagerplaetze-Tabelle + Modul |
| letzte_inventur | ⏳ Platzhalter bis Inventur-Modul |
| artikeltyp | 🔴 Noch nicht eingebaut — kommt mit Filterung |
