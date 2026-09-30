---
name: project-zahlung-erp-an-shop
description: "2026-09-30 gebaut (uncommitted) — im ERP gebuchte Zahlung wird an WooCommerce gemeldet (Status processing + Notiz), WC-Mail per Snippet unterdrückt; erste Gegenrichtung ERP→Shop für Bestellungen"
metadata:
  node_type: memory
  type: project
  originSessionId: 3204dc86-54bf-4800-9ff8-19338a0b843e
  modified: 2026-09-30T17:10:10.134Z
---

Jacky 2026-09-30: "wäre ja kompliziert jede Buchung an 2 Stellen pflegen zu müssen" — Überweisungen werden im ERP gebucht (Auftrag → Zahlung buchen), der Shop blieb bisher für immer "In Wartestellung" (Bestellungs-Sync war rein Shop→ERP, siehe [[project_shop_sync]]). Folge u.a.: online gekaufte Gutscheine per Überweisung wären nie entstanden ([[project_gutscheine]]).

**Gebaut:** `ShopBestellungSyncService::meldeZahlungAnShop()`, aufgerufen in `public/auftraege/zahlung_buchen.php` nach `bucheZahlung()` für kanal=woocommerce:
- vollständig bezahlt → WC `PUT /orders/{id}` status=processing + Meta `_mealana_erp_zahlung`=Buchungsdatum; Teilzahlung → nur Notiz
- Notiz (nur wp-admin): "Zahlung eingegangen: 50,00 € am 30.09.2026 per Überweisung (gebucht im ERP, A-2026-00041) — Notiz: …"
- danach sofort `syncBestellungen()` → Gutschein etc. entsteht sofort
- Fehler → Zahlung bleibt gebucht, JS zeigt alert mit Hinweis "im Shop von Hand setzen"
- WooCommerceClient: `aktualisiereBestellung()`, `erstelleBestellNotiz()`
- Zahlungsart-Labels in zahlung_buchen.php zusammengeführt (vorher doppelt)

**Mails:** ERP verschickt bei Zahlung buchen schon eigene (schönere) Mail "Zahlung eingegangen"; die WC-Mail "In Bearbeitung" wird per neuem Snippet `shop/wp-snippets/erp_zahlung_ohne_shop_mail.php` (Filter woocommerce_email_enabled_customer_processing_order) nur für ERP-markierte Bestellungen unterdrückt. Jacky: "die ganzen Mails werden also vom Shop geschickt... das erklärt warum die so unschön sind" → mögliches Folgethema: WC-Mails optisch angleichen / mehr über ERP senden.

**Test 2026-09-30 bestanden:** Jacky buchte 50 € auf A-2026-00041 → WC #34190 processing + Notiz, KEINE WC-Mail (Snippet 34191 aktiv), Gutschein MEA-HB8T-4NXY-HZ9Z 9 s später erzeugt + versendet.

**Erweitert (gleicher Tag, Jacky: "sonst bleiben die Aufträge im Shop offen"):** `meldeStatusAnShop()` mit Soll-Status-Regel `sollShopStatus()`: bezahlt → processing, bezahlt+versendet/abgeschlossen → completed (+ Versand-Notiz mit Datum/Dienstleister/Sendungsnr bzw. Abholung), versendet aber unbezahlt → NUR Notiz (versand_notiz) — sonst würde der Rück-Abgleich (completed = bezahlt) unbezahlte Rechnungs-/Nachnahme-Aufträge fälschlich auf bezahlt setzen. Nie zurückstufen (Rang). Migration 182 `auftraege.shop_status_gemeldet` (bekannter WC-Status, beim Import/Poll gemerkt, Altbestand vorbelegt). `meldeOffeneStatusAnShop()` im Cron + Komplettabgleich (sammelt alle Wege: Packplatz, Kasse-Abholung, Gutschein-only). Rück-Abgleich stuft ERP-Zahlungsstatus nicht mehr herunter (ausstehend<teilbezahlt<bezahlt; Storno/Erstattung aus Shop gelten weiter). Snippet 34191 unterdrückt jetzt auch customer_completed_order bei `_mealana_erp_versand` — **Jacky muss den erweiterten Code in WPCode 34191 übernehmen.**

**Nachbesserung nach Jackys Test (gleicher Tag):** (1) auftraege/detail.php zeigte gekaufte Shop-Gutscheine als "Als Gutschein erstattet" (beide hängen über auftrag_id_ursprung) → getrennt: kanal_line_item_id gesetzt = "Gekaufte Gutscheine" (mit versendet_am/Zustellung), sonst Erstattung. (2) Kunde sah Zahlung im Kundenkonto nicht (Notizen waren privat) → jetzt Kunden-Notiz ("Zahlung eingegangen: 50,00 € am … per Überweisung. Vielen Dank!" / "Ihre Bestellung wurde am … versendet mit …, Sendungsnummer …") + separate interne Notiz mit Auftragsnr/Buchungsvermerk. Kunden-Notizen enden auf U+200B (`KUNDENNOTIZ_MARKE`), Snippet 34191 unterdrückt dafür `woocommerce_email_enabled_customer_note` (von Hand geschriebene Kunden-Notizen mailen weiter).

**Von Jacky getestet + committed 2026-09-30:** Snippet 34191 mit 3 Filtern aktiv, "Fertiggestellt"-Meldung, Kundennotizen, Gutschein-Coupon im Shop — "funktioniert alles".
