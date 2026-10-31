---
name: project-lizenzierung-preise
description: "Lizenzmodell NAHTLOS — Kauf + Wartung entschieden (2026-10-30), Technik Offline-Signatur, Preisvorschlag Module/Wartung noch nicht abgenommen"
metadata:
  node_type: memory
  type: project
  originSessionId: 52d0d776-925d-4411-9501-10515ff2b29e
  modified: 2026-10-30T16:10:27.543Z
---

**Entschieden (Jacky 2026-10-30):** Kauf + Wartung (wie JTL früher/SelectLine), kein Abo. Grundmodul + Erweiterungsmodule, Module nachträglich zubuchbar.

**Technik-Vorschlag (noch nicht gebaut):** Lizenzdatei JSON + Ed25519-Signatur (PHP sodium), privater Schlüssel nur auf Jackys Rechner (Ebene 2), ERP hat nur Public Key (Ebene 1). Bindung an Firmenname + UID aus Einstellungen. Lizenz läuft nie ab, nur "Wartung bis". Nie komplett sperren (7 Jahre Aufbewahrung, DEP-Export). Tabelle `modul_lizenzen` existiert NICHT (alte Planung in [[project-rechte-rollen]] überholt).

**Claude-Vorschlag Preise (netto, 2026-10-30, nicht abgenommen):** Grundmodul 790 €, Verkauf 390, Versand/Packplatz 290, Kasse 590 inkl. 1 Kasse (+190 je weitere), Shop-Anbindung 490 (+190 je weiterer Shop), Partner/Mietfächer 390, Händler-Konsignation 290, Buchhaltungs-Export 190, Konfigurator 190. Pakete: "Laden" 1.490, "Komplett" 2.490. Wartung 20 %/Jahr vom Listenwert aller Module, nur gesamt (Update ist immer ein Paket), Wiedereinstieg mit Nachzahlung. Einrichtung/Datenübernahme extra.
Marktanker: ready2order ~36 €/Monat + 12 € Signatur, JTL Advanced ~99 €/Monat, Xentral ab 99 €/Monat, Kasse Speedy 230 € Kauf + 6 €/Monat, Wartung branchenüblich 18–22 %.

**Nachtrag 2026-10-30:** Jacky will "Lagerplätze + Profi-Inventur" (mehrere Zähler/Tablets) als eigenes Modul (JTL verlangt für WMS viel, Inventur dort schwach). Claude-Vorschlag: natürlicher Schnitt = Mehrzähler-Live-Sperre hängt an Lagerplätzen; Grundmodul behält einfache Inventur (Scope Lager/Kategorie/Artikel, 1 Zähler, Druckliste) weil Inventur Pflicht ist; Vollinventur-Buchungssperre (Kasse/WE) bleibt immer aktiv. Preisidee ~390 €. Betrieb: Kunde betreibt selbst (Server, Backup, Erreichbarkeit beim Kunden) — kein Hosting durch Jacky. Lizenz aktivieren nur Superadmin (create_admin.php vergibt superadmin automatisch, CLI; kein Web-Setup-Assistent vorhanden → für Selbstinstallation nötig).

**Superadmin-Übergabe (Jacky-Idee 2026-10-30, Konzept, nicht gebaut):** NUR per Übergabe-Schlüssel von Jacky (gleiche Ed25519-Signatur wie Lizenz), nie "einfach so". Schlüssel gebunden an Installation (Firmenname+UID), einmalig (Nonce in DB als verbraucht), ca. 7 Tage gültig. Eingabe auch ohne Login möglich (Fall: niemand kommt mehr rein). Ablauf: Key → aktive Benutzer anzeigen oder neuen anlegen → neuer Superadmin; alter wird degradiert (Rolle wählbar) oder deaktiviert (nie gelöscht, Benutzer-"Löschen" ist schon heute aktiv=0, wegen Logs/Belegen). Log-Eintrag. Jacky prüft vor Ausstellung die Identität (schriftlich vom Inhaber/Geschäftsführer). Gehört zusammen mit Web-Einrichtungsassistent in den Lizenz-Bau.

**Dokument für Babsi (2026-10-30):** Claude Doc "NAHTLOS – Modul- und Preisvorschlag" https://claude.ai/artifact/RcJiAgNhLiCQhnzoRvJio5 — dort aktualisierter Stand: Modul Lagerplätze & Profi-Inventur 390 €, Listenwert gesamt 4.000 €, "Komplett" jetzt 2.790 €, Wartung 20 % vom bezahlten Preis, 1. Jahr inklusive (Annahme), Checkliste offener Fragen.

**Offen:** Jacky/Babsi Preise + Modulschnitt abnehmen; Frage wer hostet (beim Kunden vs. Jacky); dann Lizenz-Ebene 1 + Generator bauen. Verwandt: [[project-update-mechanismus]], [[project-whitelabel-branding]].

**✅ Entscheidungen Modulgespräch Jacky + Babsi (2026-10-30, notiert 2026-10-31):**
- **Grundmodul + Verkauf verschmolzen → Grundmodul 790 €** (Verkauf ist kein eigenes Modul mehr), ABER ohne Gutscheine.
- **Gutscheine = eigenes Modul 190 €.**
- **Dropshipping = eigenes Modul 190 €**, muss noch komplett gebaut werden, siehe [[project-dropshipping]].
- **"Zahlungskontrolle"** im alten Verkaufs-Paket war unklar. Gemeint ist die Seite Buchhaltung → Zahlungs-Kontrolle (`public/buchhaltung/zahlungskontrolle.php`, `kontrollListe()`): Bons + Aufträge mit Zahlungen je Tag, Aufteilung auf Konten (Bar/Karte/PayPal …), Differenzen orange. Claude-Empfehlung: ins Grundmodul (Kassensturz-/Abgleich-Werkzeug für jeden Kunden), nicht in den Buchhaltungs-Export. Noch nicht von Jacky entschieden.
- Folge: Listenwert/Paketpreise im Claude Doc (RcJiAgNhLiCQhnzoRvJio5) sind veraltet (Verkauf 390 entfällt, +Gutscheine 190, +Dropshipping 190) → Doc bei Gelegenheit nachziehen, Pakete "Laden"/"Komplett" neu rechnen.
