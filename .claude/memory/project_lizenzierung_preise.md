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

## ✅ Lizenz-Bau: Entscheidungen Jacky 2026-11-01
1. Packplatz/Versand gehört ins **Grundmodul** (kein eigenes Modul).
2. Nicht lizenzierte Module: im Menü **sichtbar, ausgegraut mit Schloss**; vorhandene Daten bleiben lesbar.
3. Wartung abgelaufen: alles läuft weiter, nur neuere Updates nicht (Update-Datum > wartung_bis). In der **Kasse oben klarer Hinweis "Wartung abgelaufen!"**. Zusätzlich bei aktiver RKSV (unabhängig von der Wartung) Hinweis auf **ablaufendes Signaturzertifikat**: BFR /state liefert nur die Seriennr. (SC), kein Ablaufdatum → Feld "Zertifikat gültig bis" in der Kassen-Registrierung, vorbelegt zertifikat_gemeldet_am + 5 Jahre, überschreibbar; Warnung ab 60 Tage vorher (Claude-Vorschlag).
4. **Ohne Lizenzdatei nur Grundmodul** (unbefristet, Hinweis "nicht lizenziert") — keine Testphase mit allen Modulen, weil eine aktivierte RKSV-Kasse nicht mehr abgedreht werden darf.
5. Bindung Firmenname + UID (ersatzweise Steuernummer); bei Neuausstellung (z.B. Umfirmierung) wird die **bestehende Wartungs-Restlaufzeit übernommen**.
6. Generator: Jacky will eine abgespeckte ERP-Instanz (≈ Grundmodul + Generator) für seine eigene Firma (wird vermutlich **Kleinunternehmer**, nicht MeaLana) — Rechnungen an Lizenzkunden dort. Claude-Vorschlag (Antwort offen): Generator als verstecktes Modul "Lizenzverwaltung" im ERP selbst — Kunden = ERP-Kunden, Module = Artikel, Lizenzverkauf = Auftrag/Rechnung, Button "Lizenzdatei erstellen" aus dem Auftrag, Lizenz-Historie je Kunde, Hauslizenz-Option; privater Schlüssel nur als Datei außerhalb Webroot, Generator-Code nicht in Kunden-Updates. Echte KU-Rechnungen erst nach Steuerprofil ([[project-steuerprofil]]).
7. Onboarding vor 1.0, jetzt nur Minimum (Lizenz einspielen + Superadmin-Übergabe).
8. Hauslizenz (alle Module, unbegrenzte Wartung) als Auswahl im Generator.
Vorgeschlagene Bau-Reihenfolge: 1 Lizenz prüfen/Schloss/Mengen → 2 Hinweise Wartung + Zertifikat → 3 Superadmin-Übergabe → 4 Lizenzverwaltung (Generator) → 5 Hauslizenz MeaLana + Klicktest. Offen: Generator-im-ERP abnehmen, finale Modulliste mit Mengen (Kassen, Shops).

## ✅ Finale Modulliste (Jacky 2026-11-01, netto) — gilt vor allen älteren Preisangaben oben
Grundmodul 790 (inkl. Verkauf, Picklisten, Packplatz) · Gutscheine 190 · Versand = nur noch PLC-Anbindung 50 · Lagerplätze & Profi-Inventur 390 · Kasse 590 (1 Kasse) + zusätzliche Kasse 190 je · Shop 490 (1 Shop) + weiterer Shop 190 je · Partner 390 · Händler 290 · Dropshipping 190 · Buchhaltung(-Export) 190 · Konfigurator 190.
Fremdprodukt BFR (für RKSV nötig, extern zugekauft): 189 € einmalig je RKSV-Kasse (inkl. Zertifikat 5 Jahre) + 7,99 €/Monat (jährlich verrechnet). **BFR/RKSV geht nur mit UID**, nicht mit Steuernummer. Claude-Vorschlag: BFR kein Lizenzmodul, nur Artikel in der Lizenzverwaltung; UID-Warnung in der Kassen-Registrierung.
Generator: Jacky 2026-11-01 einverstanden → Modul im ERP, Einstieg ist der vorhandene ausgegraute Menüpunkt "🔑 Lizenzverwaltung" (shell_top.php, ···-Menü): in jeder Installation eigene Lizenz ansehen/einspielen, Generator-Teil nur mit Hauslizenz + Schlüsseldatei.
Nachtrag Jacky 2026-11-01: **Kundenanzeige = eigenes Modul 99 €**, **Paperless (QR-Rechnung, noch nicht gebaut) = Modul 99 €**, Offline-/Messe-Kasse gehört zur Kasse, BFR = Fremdartikel (nur weiterverrechnet).
JTL-Import: sehr speziell, später entscheiden (evtl. zeitlich begrenzte Freischaltung). Idee für später: Modul **"Importer"** mit mehreren bekannten Importen (JTL, Lieferanten-Listen wie Lang …), evtl. konfigurierbare Feldzuordnung. Vorher JTL-Import nochmal durchgehen: Jacky zweifelt, ob mehrere Preise je Artikel (Fall eines befreundeten Händlers) schon sauber gelesen werden. Bis dahin JTL-Import nicht lizenz-gesperrt.

## ✅ Lizenz-Bau Schritt 1 GEBAUT 2026-11-01 (nicht committed bis Jacky sagt)
- **Technik geändert:** ECDSA P-256 über OpenSSL statt Ed25519/sodium (sodium in XAMPP nicht aktiv, OpenSSL überall da → Kunden müssen nichts einschalten). Lizenzdatei = JSON {daten: JSON-Text, signatur: base64}, signiert wird der daten-Text.
- Privater Schlüssel: `D:/ERP/lizenz_schluessel/nahtlos_lizenz_privat.pem` (außerhalb Repo, .gitignore *privat*.pem) — **Jacky muss ihn sichern, Verlust = keine neuen Lizenzen**. Öffentlich: `erp/config/lizenz_public.pem` (im Repo). Ausgestellte Dateien: `D:/ERP/lizenz_schluessel/ausgestellt/` (L-2026-0001 = MEALANA KG Hauslizenz, alle Module, Kasse/Shop 99, Wartung unbegrenzt — auf Dev eingespielt).
- Migration 216 `lizenz_dateien` (Historie, eine aktiv). `src/core/Lizenz.php`: MODULE (Codes+Preise), SEITEN (Pfad→Modul) + LESEND (Lese-Seiten), hat/anzahl/status/wartungBis/wartungAbgelaufen/seiteErlaubt/linkGesperrt, pruefeInhalt (Signatur + Firma normalisiert + UID bzw. Steuernummer), einspielen. Auth::pruefeSeite sperrt → `lizenz/nicht_lizenziert.php` bzw. JSON 403. Menü (shell_top) ausgegraut + 🔒 über dieselbe Tabelle. `public/lizenz/index.php` + `hochladen.php` (lizenz.verwalten = Superadmin), Menüpunkt ··· → 🔑 Lizenzverwaltung aktiv.
- Inline: Kassen-Anzahl (kasse_speichern), Shop-Anzahl beim Aktivieren (einstellungen/speichern), Shop-Cron (nur lizenzierte Anzahl, älteste zuerst), PLC-Export (abschliessen), Dropshipping (Karte, Listenfilter, Pickliste-Badge).
- Werkzeuge (nicht im ERP-Ordner): `tools/lizenz/LizenzSigner.php`, `schluessel_erzeugen.php` (verweigert, wenn Schlüssel existiert), `ausstellen.php` (CLI, --haus).
- Tests: 23/23 Logik (Haus, manipuliert, fremde Firma, Schreibweise egal, eingeschränkt inkl. abgelaufene Wartung, keine Lizenz) + Render mit eingeschränkter Lizenz in der DB (danach Hauslizenz wiederhergestellt).
- **⚠ Live-Deploy:** mit Migration 216 MUSS gleich die Hauslizenz L-2026-0001 auf Live eingespielt werden, sonst läuft Live nur mit Grundmodul (Kasse gesperrt!). Live-Firmendaten müssen "MEALANA KG" + ATU68078304 sein.
- Offen in Schritt 1 (bewusst später): Gutschein-VERKAUF an Kasse/Shop ohne Modul, Konfigurator-Reiter im Artikel noch sichtbar (nur Ajax gesperrt), Profi-Inventur (Mehrzähler) noch nicht an lagerplaetze gekoppelt, Kundenanzeige-Link in Kassen-Einstellungen.
- Nächste Schritte: 2 Hinweise (Wartung abgelaufen oben in der Kasse, Zertifikat "gültig bis" + Warnung, UID-Warnung RKSV) → 3 Superadmin-Übergabe → 4 Generator-UI im ERP → 5 Klicktest.

## ✅ Lizenz-Bau Schritt 2 GEBAUT 2026-11-01 (Hinweise)
- `src/modules/kasse/KassenHinweise.php` + Partial `public/kasse/_hinweise.php` (in kasse/shell_top.php + bon.php; bon.php verkleinert .pos-layout um 36px je Zeile, Zeile nowrap mit Tooltip): rot "Wartung abgelaufen!", Signaturzertifikat gelb ab 60 Tage vorher / rot abgelaufen — nur Kassen mit bfr_aktiv_seit.
- Migration 217 `bfr_kassen_registrierungen.zertifikat_gueltig_bis` (NULL = gemeldet + 5 Jahre, berechnet). Formular in "Aktive RKSV-Registrierung" → `einstellungen/kasse_zertifikat_speichern.php` (kasse.verwaltung).
- UID-Warnung (blaues !) auf der Registrierungsseite, wenn Firmen-UID leer.
- ERP: dezenter Wartungshinweis oben im Inhalt nur für lizenz.verwalten.
- Getestet: Logik (30/61 Tage, abgelaufen, Wartung weg, Kasse ohne RKSV) per Rollback; Registrierungsseite gerendert. Banner in der echten Kasse noch nicht im Browser angesehen → Klicktest.

## ✅ Lizenz-Bau Schritt 3 GEBAUT 2026-11-01 (Superadmin-Übergabe)
- Migration 218: `superadmin_uebergaben` (nonce UNIQUE = einmalig) + `benutzer.sitzung_ungueltig_ab`. Auth: Login merkt `$_SESSION['angemeldet_am']`, heartbeat() beendet Anmeldung, wenn Benutzer inaktiv ODER sitzung_ungueltig_ab >= angemeldet_am (**neu: auch deaktivierte Benutzer fliegen jetzt beim nächsten Klick raus**).
- Lizenz.php: pruefeSignatur() + firmaPasst() herausgezogen; Lizenzdateien tragen typ 'lizenz' (fehlt = lizenz), Übergabe-Schlüssel typ 'superadmin_uebergabe' — gegenseitig nicht verwechselbar.
- `src/modules/benutzer/SuperadminUebergabe.php` (pruefeSchluessel, durchfuehren: Ziel bestehend/neu, jeder alte Superadmin → rolle:<id> oder deaktivieren, sonst Abbruch; Log warn), Seite `public/superadmin_uebergabe.php` ohne Login, Link auf login.php.
- Werkzeug `tools/lizenz/uebergabe_ausstellen.php --nr --firma --uid [--tage=7]`.
- Test 12/12 (gültig, fremde Firma, abgelaufen, Typ-Verwechslung, ohne Entscheidung → Rollback, Übergabe, einmalig) mit Sicherung/Wiederherstellung der echten Superadmin-Konten; Seite gerendert.

