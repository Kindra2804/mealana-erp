---
name: reference-wp-claude-zugang
description: Eigener WordPress-Admin-Benutzer für Claude auf indra-design.at (MEALANA-Shop), Zugangsdaten-Fundort
metadata:
  node_type: memory
  type: reference
  originSessionId: 40a29a40-0c3b-483d-82e6-51045de0676d
  modified: 2026-08-29T10:19:50.690Z
---

Jacky hat einen eigenen wp-admin-Benutzer für Claude auf `indra-design.at` angelegt (Benutzername `claude`), damit Live-Diagnosen am Shop (WPCode-Snippets, Einstellungen) nicht auf Jackys eigenes Application-Password (siehe [[project_shop_sync]], nur REST-API-tauglich, kein wp-admin-Session-Login) angewiesen sind.

**Zugangsdaten liegen in:** `D:\ERP\mealana\import\zugang Woo Claude.txt` (Klartext, Benutzername + Passwort). Nicht in dieses Memory kopiert — bei Bedarf direkt aus der Datei lesen.

**Verwendung:** Login über `https://indra-design.at/wp-login.php` (Felder `#user_login`/`#user_pass`) per Playwright (siehe [[reference_browser_testing_tools]]), danach z.B. WPCode-Snippets unter `wp-admin/admin.php?page=wpcode-snippet-manager&snippet_id=…` bearbeiten. Für reine Frontend-/Checkout-Diagnosen (Store API, Warenkorb, Zahlungsarten) ist dieser Login NICHT nötig — die Store-API (`/wp-json/wc/store/v1/...`) ist als anonymer Shopper direkt nutzbar und meist aussagekräftiger als Server-Logs, siehe [[project_shop_sync]] Eintrag "Blocks-Payment-Method-Registrierung" vom 2026-08-29 (kompletter Checkout-Datenfluss inkl. Zahlungsart-Sichtbarkeit stand direkt im öffentlich ausgelieferten Checkout-HTML, kein Server-Log-Zugriff nötig).

**Why:** Vorherige Sessions konnten Live-Shop-Änderungen nur mit Jackys direkter Mithilfe machen (er musste selbst im Browser klicken/tippen). Der eigene Zugang erlaubt jetzt eigenständige Diagnose+Fixes am Shop, ähnlich wie der lokale Admin-Zugriff aufs ERP.
**How to apply:** Bei künftigen Shop-Frontend-Bugs (Checkout, Zahlungsarten, Snippets) zuerst prüfen, ob die Store-API allein reicht (kein Login nötig, schneller). Nur wenn wp-admin-Einstellungen/Snippets geändert werden müssen, den `claude`-Login aus der genannten Datei verwenden.
