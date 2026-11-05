---
name: project-newsletter-slider-ideen
description: "Newsletter + Hero-Slider aus dem ERP steuern (ERP = Single Point of Truth, auch für Babsi einfacher); heute JTL-Shop-Newsletter + JTL-Slider (statisch, von Jacky im Code gepatcht); Claude-Vorschlag: Slider zuerst über eigenes WP-Connector-Plugin, Newsletter mit Versanddienst"
metadata:
  node_type: memory
  type: project
  originSessionId: ef009f0b-163c-454c-9dcc-cdd9cc4fc4bf
  modified: 2026-11-04T11:53:12.287Z
---

Jacky 2026-11-04: Newsletter und Slider sollen ins ERP. Ziel: **ERP als Single Point of Truth**, damit nicht ein Teil im ERP und ein anderer im Shop gepflegt wird. Soll auch Babsi alles vereinfachen.

**Ist-Stand:** beides im JTL-Shop. Newsletter über den JTL-Newsletterversand („sehr statisch“, Jacky hat teils direkt im Code eingegriffen, „funktioniert eher schlecht als recht“). Slider über die JTL-Shop-Sliderverwaltung. Beides geht heute nur im Shop.

**Claude-Vorschlag (2026-11-04, noch nicht entschieden):**
- **Slider zuerst** (kleiner): im ERP je Kanal Slides pflegen (Bild Desktop/Mobil, Titel, Text, Button + Link, gültig von/bis, Reihenfolge, optional an eine Aktion gekoppelt). Übertragung per WP-REST. Woo hat keine Slider-API, deshalb ein **eigenes kleines WP-Plugin „NAHTLOS Connector“** mit eigenem Endpoint und Shortcode/Block. Das Plugin filtert die Zeiträume selbst, damit Slides auch ohne ERP-Sync pünktlich wechseln (das ERP ist nur per VPN erreichbar). Bilder über die bestehende Mediathek-Upload-Logik.
- **Newsletter** (größer, eher nach dem Go-Live): Inhalte und Empfänger im ERP, Bausteine aus den ERP-Daten (Artikel mit Bild/Preis/Link, Aktion, Gutschein). Den **Versand über einen Dienst** machen (z. B. Brevo, CleverReach), nicht über das eigene SMTP: Zustellbarkeit, Limits beim Hoster, Bounces. Rechtlich nötig: Double-Opt-in, Abmeldelink und Einwilligungsnachweis (DSGVO + TKG §174). Die Tabelle kunden_dsgvo_consent gibt es schon. Anmeldung/Abmeldung brauchen einen öffentlichen Endpunkt: Dienst, WP-Plugin oder beleg.mealana.at.

**Antworten Jacky 2026-11-04:**
- MeaLana hat **ca. 1.600 Empfänger**. Ziel ist ca. 1×/Monat, derzeit eher 1×/Quartal.
- Ein Newsletter **je Shop möglich** machen, derzeit nur MeaLana.
- **Slider schon zum Start von indra-design**: Jacky liefert Bildmaterial, Claude baut „was Schönes“ daraus.

**Bildmaterial indra-design (2026-11-04):** `E:\Laser\Bilder fertiger Teile\` auf dem Dev-PC: Fertige Untersetzer (28 Handyfotos, wechselnder Hintergrund), FertigeSchilder (68), Schlüsselanhänger (27, weißer Hintergrund), Untersetzer_Kork-Bilder (PNG-Renderings **freigestellt**, ideal für den Slider), Pappelsperrholz-Bilder KI (4 Renderings). Slider-Entwürfe als HTML→Playwright-Screenshot gebaut (Skript `.claude-browser-tools/slider_entwurf.js`): Untersetzer (Creme/Kork, „Masche für Masche. Schluck für Schluck.“) + Schilder (Petrol, „Willkommen im Wollzimmer.“). Texte live als HTML, nur der Hintergrund als Bild.

**Weiteres Material (2026-11-04):** Set-Vorlagen in `E:\Laser\Untersetzer\Untersetzer_Masterlook\Untersetzer_Masterlook\{Rund,Eckig}\Set_X_*\` (8 Sets A Strickkränzchen, B Teerunde, C Kaffeeklatsch, D Wollige Gesellschaft, F Häkelrunde, G Spinnstube, H Strickweihnacht, I Sockenliebe; je Kanne 200 mm + 4 × 95 mm als SVG mit Ebene „Gravur“ + 1000-dpi-PNG, opak weiß → per multiply auf Kork rendern). Entwurf `slider/weihnacht.*` (Tannengrün, „Leise rieselt die Wolle.“) — die Korkstruktur ist noch zu glatt. Neue Fotos `Bilder fertiger Teile\Neuer fertige Untersetzer\{Kork 24, Kunstleder 15, Schiefer 9}` + `Klebepads` (8 sechseckige Kork-Pads „Wollvorrat“-Serie). Kork-Fotos einheitlich und gut. Schiefer und schwarzes Kunstleder sind zu kontrastarm, dort braucht es Streiflicht oder eine Ansicht aus der Vorlage.

**Neubewertung Versand (Claude):** Bei 1.600 Empfängern im Monat ist **eigener Versand realistisch**: gedrosselt per Cron in Paketen über das Mail-Konto des Shops, mit SPF/DKIM/DMARC. Keine Kosten, kein Auftragsverarbeiter. Vorher die Versandlimits von Hostech prüfen. Ein Dienst wie Brevo bleibt die Ausweichlösung. Anmeldung mit Double-Opt-in und die Abmeldung laufen über das WP-Connector-Plugin, das ERP holt sie per Polling.

**✅ Slider GEBAUT 2026-11-05** (Commits 2b5bf41, 2f17052, 6b8b5ea), Jackys Entscheidungen: Bild + Text getrennt und zusätzlich „Fertiges Bild“, Autoplay mit Pfeilen, Menü Artikel → Shop-Slider (Recht slider.verwalten), Kategorie-Banner gleich mitgebaut.
- ERP: Migration 224 (`shop_slides` + shops.slider_intervall_sek/_uebertragen_am/_fehler/_geaendert + Recht), `src/modules/slider/`, `public/slider/`, `js/slider.js`. Slides auch für Shops ohne Anbindung vorbereitbar (SOW). Kopieren in andere Shops, Kopie ist aus. Übertragen nur per Klick, der Cron überträgt Aktions-Slides täglich neu, aber nur wenn nichts offen ist.
- WP-Plugin `shop/wp-plugin/nahtlos-connector/` 1.0.0: REST nahtlos/v1/status + slider, Shortcode `[nahtlos_slider]`, Kategorie-Banner über woocommerce_before_main_content, Cache-Leeren per WP-Cron zu von/bis. Lokal mit WP-Stub getestet, ERP-Klicktest 13/13 ok (Skript `.claude-browser-tools/slider_erp_test.js`).
- **Offen:** Plugin auf indra-design.at installieren (Jacky fragen) + Startseiten-Cover durch den Shortcode ersetzen, echter End-to-End-Test, Klicktest Jacky/Babsi, Migration 224 auf Live.

**How to apply:** Vor dem Bauen [[feedback-modul-vorgehen]] (Referenz-Check) + [[feedback-scope-ohne-bedarf]]. Theme-Kontext: [[project-shop-theme]], Sync-Mechanik: [[project-shop-sync]].
