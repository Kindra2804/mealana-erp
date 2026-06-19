---
name: project-bilder-modul
description: "Bilder-Modul: FERTIG (2026-06-19) — Upload, GD-Resize, Hauptbild-Swap, Reihenfolge, Alt-Text, Multi-Shop-Sync-Tabelle"
metadata: 
  node_type: memory
  type: project
  originSessionId: 34c5df69-81a4-4021-b25c-95e8cb12005b
---

## Status: FERTIG (2026-06-19)

## Was gebaut wurde

### DB (Migration 045)
- `artikel_bilder` — id UNSIGNED, artikel_id UNSIGNED FK, dateiname, alt_text, position, erstellt_am
- `artikel_bilder_shops` — bild_id + shop_id → external_id VARCHAR, sync_status ENUM, synced_at, fehler_meldung
- UNIQUE KEY (bild_id, shop_id) — ein Bild kann in mehreren Shops unterschiedliche external_ids haben

### Backend
- `BilderRepository.php` — findByArtikelId, insert, updateAltText, delete, setzeHauptbild, verschiebePosition
  - verschiebePosition schützt Position 0 (Hauptbild): ↑ nur wenn $pos > 1
- `bild_upload.php` — PHP GD Resize (max 1920px, JPEG 85%), MIME-Check, mkdir recursive
- `bild_ajax.php` — Sammel-Handler: aktion=alt_text|position|hauptbild
- `bild_loeschen.php` — unlink + DB delete

### Frontend
- `detail.php` Tab "Bilder": Drop-Zone, Bild-Grid (.bild-karte), Anzahl, Status
- `bilder.js` — Event Delegation auf #bild-grid (kein per-Karte Binding!)
  - aktualisiereAlleKarten() baut Overlay + Steuerbereich nach jeder Aktion komplett neu → kein DOM-Stapeln
  - ↑↓ Buttons: Karte bei Index 1 hat kein ↑ (direkt unter Hauptbild), letzte hat kein ↓
  - ☆ Hauptbild → setzt Karte an Position 0, alle anderen bekommen ☆-Button zurück

### Entscheidungen
- ERP speichert nur Original (clean, kein Wasserzeichen)
- Wasserzeichen: wird beim Shop-Sync aufgedrückt (PHP GD on-the-fly), konfigurierbar pro Shop im Admin-Menü
- Thumbnails: keine im ERP — WooCommerce generiert eigene Thumbnail-Größen nach Import
- Keine Vater→Kind-Vererbung bei Bildern (jeder Artikel hat eigene Bilder)
- WooCommerce-Sync: Bilder per API hochladen → external_id (WC attachment_id) in artikel_bilder_shops speichern → inkrementeller Sync (nur neue/geänderte)

## Noch offen
- WooCommerce API-Sync implementieren (braucht echten WC-Server zum Testen)
- Wasserzeichen-Upload im Admin-Menü
- "Keine Bilder" Warn-Chip in Artikelliste (gemerkt für später)
- Komplettabgleich im Admin-Menü (Bilder/Artikel/Kategorien pro Shop) — gemerkt für später
