---
name: project-gutscheine
description: "Gutschein-Modul Design: ERP als Single Source of Truth, on+offline, WooCommerce-Sync als Slave"
metadata: 
  node_type: memory
  type: project
  originSessionId: 9a44da56-fbce-4da5-b4f6-17b472024d63
---

## Konzept: ERP = Single Source of Truth

WooCommerce hat kein natives Wertgutschein-System (nur Rabatt-Coupons, kein Restguthaben-Tracking).
→ ERP verwaltet alle Gutscheine zentral. WooCommerce ist Slave (gespiegelter Coupon per REST API).

**Kein Design/Text im WooCommerce möglich** — Kunde bekommt nur den Code.
Von/An-Felder, persönlicher Text, Gutschein-Layout → erst im eigenen Shop umsetzbar.
Bis dahin: Code per E-Mail, akzeptiert.

## DB-Tabellen

```sql
gutschein_vorlagen (id, name, betrag, gueltig_tage, aktiv)

gutscheine (
  id, code UNIQUE,
  vorlage_id FK NULL,
  betrag, restguthaben,
  gueltig_bis DATE NULL,
  status ENUM(aktiv, teilweise, eingeloest, abgelaufen, storniert),
  kunden_id FK NULL,         -- personalisierter Gutschein
  woo_coupon_id INT NULL,    -- gespiegelte WC-Coupon-ID
  kanal_erstellt ENUM(kasse, erp, woocommerce, manuell),
  ausgestellt_von FK benutzer,
  erstellt_am, aktualisiert_am
)

gutschein_transaktionen (
  id, gutschein_id FK,
  auftrag_id FK NULL,
  betrag,          -- negativ = Einlösung, positiv = Erstattung
  kanal ENUM(kasse, erp, woocommerce),
  notiz,
  benutzer_id FK, erstellt_am
)
```

## Ablauf: Im Geschäft kaufen → im Shop einlösen
1. Kasse verkauft Gutschein → ERP erzeugt Code + Betrag + Gültig-bis
2. WC REST API: Coupon anlegen (code, amount, discount_type=fixed_cart, usage_limit=1)
3. Kunde gibt Code im Shop ein → WC prüft Coupon normal
4. ERP importiert Auftrag → erkennt Coupon-Code → bucht in gutschein_transaktionen ab
5. Bei Teileinlösung: WC-Coupon deaktivieren, neuen Code für Restbetrag erzeugen → wieder zu WC spiegeln + Kunde per Mail informieren

## Ablauf: Im Shop kaufen → im Geschäft einlösen
1. Shop-Bestellung mit Gutschein-Produkt → ERP importiert → erzeugt Code → WC-Coupon anlegen
2. Kasse: Code scannen/eingeben → ERP-Abfrage (Restguthaben, gültig?) → einlösen → WC-Coupon deaktivieren

## Kasse-Erstattung via Gutschein (geplant)

Wenn Auftrag `abholbereit + bezahlt` und Kd. nimmt **weniger** als bestellt:
- Derzeit: Barauszahlung (Differenz bar zurück)
- **Sobald Gutschein-Modul fertig**: Kasse bietet automatisch Wahl an:
  - "Bar zurückgeben" ODER "Gutschein ausstellen"
  - Bei Gutschein-Wahl: automatisch neuen Gutschein über Differenzbetrag erstellen
  - Gutschein in `gutscheine` Tabelle, verknüpft mit Erstattungs-Bon (`kassen_bon_id`)
  - Gutschein personalisiert auf `kunden_id` wenn bekannt

→ **Muss mit der Abholbereit+bezahlt-Implementierung koordiniert werden**

## WooCommerce-Limitation (bewusst akzeptiert)
- Kein Design, kein Von/An-Feld, kein persönlicher Text im WC
- Wird mit eigenem Shop gelöst (Zukunft)
- Bis dahin: nackter Code per E-Mail reicht
