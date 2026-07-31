---
name: bug-shop-sync-term-pagination
description: "WooCommerce-Sync scheiterte 100% bei jedem Lauf (Achsenwerte-Terms) — BEHOBEN 2026-07-31, live bestätigt (erfolg:20/fehler:0 seit 21:35 Uhr)"
metadata: 
  node_type: memory
  type: project
  originSessionId: 85efc9a3-c1f8-4d31-89d2-a10e99128244
  modified: 2026-07-31T20:07:30.157Z
---

## Bug (gefunden 2026-07-31, während JTL-Import-Session)

`shop.sync_lauf` zeigte seit 20:04 Uhr bei jedem ~15-min-Cron-Durchlauf `erfolg:0, fehler:20` — 100% Fehlerquote, 235 `shop.sync_fehler`-Einträge an einem Tag. Betroffen: 10 Kind-Artikel von Vater 3047 (BC-011010119, Bio Shetland GOTS Farbvarianten).

**Root Cause:** `WooCommerceClient::listeAttributTerms()` holte nur Seite 1 (100 Terms) des globalen Attributs "Farbe" — das hat inzwischen 232+ Terms. Der Find-or-create-Abgleich in `ShopSyncService::syncWerteFuerDimension()` fand deshalb längst existierende Terms nicht und versuchte sie erneut anzulegen → WooCommerce lehnte mit `400 term_exists` ab. Zusätzlich brach die erste fehlgeschlagene Wert-Anlage die GESAMTE Methode ab (keine Pro-Wert-Fehlerisolierung, anders als beim Umbenennen-Pfad direkt daneben) — dadurch rissen 30 verwaiste Farbwerte (nie erfolgreich gesynct) bei JEDEM Lauf alle 10 Kind-Artikel mit runter, obwohl deren eigene Werte längst korrekt gesynct waren.

**Fix (im Code, 2026-07-31, NOCH NICHT COMMITTED):**
1. `WooCommerceClient::listeAttributTerms()` (`src/modules/shop/WooCommerceClient.php`) — paginiert jetzt über alle Seiten.
2. `ShopSyncService::syncWerteFuerDimension()` (`src/modules/shop/ShopSyncService.php`) — Create-Loop hat jetzt try/catch pro Wert (RateLimitException wird weiterhin durchgereicht, andere Fehler werden einzeln geloggt statt die ganze Methode abzubrechen).

**Live bestätigt (2026-07-31, 21:35 Uhr):** `shop.sync_lauf` zeigt seither `erfolg:20, fehler:0` statt durchgehend `fehler:20` — Fix wirkt. Committed am selben Tag zusammen mit dem JTL-Import-Batch.
