---
name: project_kuvert_etikett
description: "Kuvert-Etikett für fertige Schilder (A4, 2 × A5) — gebaut 2026-11-12, Artikelnummer als Code 128 statt EAN, Kasse scan-tolerant"
metadata:
  node_type: memory
  type: project
  originSessionId: 75ec7183-986d-47ce-871c-d2765ed5c06b
  modified: 2026-11-12T15:42:43.042Z
---

**Gebaut 2026-11-12 (Jacky + Entwurf `D:\ERP\kuvert_etikett_mockup.svg`):** Schilder ≤ 20 cm liegen in A4-Kuverts, größere Einzelanfertigungen in Tüten/Kartons, dasselbe Etikett für alle.
- A4 mit 2 Etiketten (210 × 148 mm): links Bild groß, rechts Layout-Name, Größe (Ø, zentriert), Aufbau (übrige Konfig-Werte), Preis (zentriert, Standardpreis ohne Aktion), Artikelnummer als **Code 128**. Kein EAN nötig, weil die Kasse auch per Artikelnummer findet.
- Bewusst weggelassen: Inhalt-Feld (immer nur 1 Schild je Kuvert), Lagerplatz (Schilder sind ausgestellt oder auf Messen unterwegs).
- Start oben/unten (Restblatt nutzen), Schnittlinie ein/aus (wird im Browser gemerkt). Klebeetiketten 2×A5 wären später möglich.
- Code: `src/modules/konfigurator/KuvertEtikettService.php`, `templates/dokumente/kuvert_etikett/`, `public/konfigurator/kuvert_etikett.php`, `public/js/kuvert_etikett.js`. Aufrufstellen: Artikelliste-Massenaktion, Artikel-Detail (nur fertige Schilder), Fertiges Schild (Erfolgsmeldung + 🖨 je Zeile). `PdfGenerator::alsString()` neu, `BelegBilder::vorschau(..., $pixel)`.
- **Kasse:** `kasse/ajax_artikel.php` + Offline-Kasse suchen tolerant (ß/?→-, Y↔Z), weil Scanner mit US-Belegung am deutschen PC „ß“ statt „-“ tippen.
- Kein Migrations-Bedarf. Offen: echter Scan-Test mit Jackys Scanner, Live-Einspielen (nächstes Paket).

**How to apply:** Wird zur ersten Vorlage des Preisschilder-Moduls [[project_preisschilder]]; Fertige Schilder siehe [[project-konfigurator-modul]].
